<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CosineSimilarity.php';
require_once __DIR__ . '/EmbeddingGenerator.php';

$pdo = Database::getConnection();

$queryText = trim($_GET['q'] ?? '');
$hasSearched = ($queryText !== '');
$results = [];
$searchTime = 0.0;
$queryVectorStats = null;
$totalChecked = 0;

if ($hasSearched) {
    $startTime = microtime(true);

    // 1. Convert user search query into 1024-dimensional embedding vector
    $queryVector = EmbeddingGenerator::generate($queryText, 1024);

    // Calculate quick stats on query vector
    $nonzeroCount = count(array_filter($queryVector, fn($val) => abs($val) > 0.000001));
    $queryVectorStats = [
        'dimension' => count($queryVector),
        'active_features' => $nonzeroCount,
        'preview' => array_slice($queryVector, 0, 8)
    ];

    // 2. Fetch all stored embeddings from rag_document
    $stmt = $pdo->query("SELECT rag_id, context, embeddings, created_at FROM rag_document");
    $documents = $stmt->fetchAll();
    $totalChecked = count($documents);

    $scoredDocs = [];

    // 3. Compute cosine similarity for each document using CosineSimilarity.php
    foreach ($documents as $doc) {
        $docVector = json_decode($doc['embeddings'], true);
        if (!is_array($docVector) || count($docVector) !== 1024) {
            continue;
        }

        try {
            // Use CosineSimilarity::calculate($vecA, $vecB)
            $score = CosineSimilarity::calculate($queryVector, $docVector);
            $percentage = CosineSimilarity::toPercentage($score, true);

            $scoredDocs[] = [
                'rag_id' => $doc['rag_id'],
                'context' => $doc['context'],
                'score' => $score,
                'percentage' => $percentage,
                'created_at' => $doc['created_at'],
                'vector_preview' => array_slice($docVector, 0, 6)
            ];
        } catch (Exception $e) {
            continue;
        }
    }

    // 4. Sort descending by cosine similarity score
    usort($scoredDocs, function ($a, $b) {
        if ($b['score'] == $a['score']) return 0;
        return ($b['score'] > $a['score']) ? 1 : -1;
    });

    // 5. Select Top 5 results
    $results = array_slice($scoredDocs, 0, 5);
    $searchTime = round((microtime(true) - $startTime) * 1000, 2);
}

// Fetch total records count for information
$countStmt = $pdo->query("SELECT COUNT(*) AS total FROM rag_document");
$totalInDb = (int) $countStmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cosine Similarity Search | 1024-Dim Vector RAG</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #062E52;
            --secondary-color: #FF5C0A;
            --accent-color: #6366f1;
            --bg-color: #f4f6f8;
            --text-color: #111827;
            --text-heading: #062E52;
            --text-light: #f8fafc;

            --panel-border: #e5e7eb;
            --header-height: 40px;
            --footer-height: 30px;

            --card-shadow: rgba(0, 0, 0, 0.08);
            --button-hover-shadow: rgba(0, 0, 0, 0.1);
            --button-background: #26549E;
            --button-hover-background: #FF5C0A;
            --header-footer-bg: #26549E;

            /* Status Colors */
            --status-active: #BCFADD;
            --status-inactive: #D3E3DC;
            --status-suspended: #F0D6B1;
            --status-deleted: #F59E93;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
        }

        /* Header matching requested --header-height: 40px and --header-footer-bg */
        header {
            min-height: var(--header-height);
            background: var(--header-footer-bg);
            color: var(--text-light);
            border-bottom: 2px solid var(--secondary-color);
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px var(--card-shadow);
        }

        .nav-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
            color: var(--text-light);
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: -0.01em;
        }

        .brand-logo {
            width: 26px;
            height: 26px;
            object-fit: contain;
            border-radius: 4px;
        }

        .brand-tag {
            background: var(--secondary-color);
            color: var(--text-light);
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .nav-links {
            display: flex;
            gap: 0.35rem;
            align-items: center;
        }

        .nav-btn {
            text-decoration: none;
            color: #dbeafe;
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .nav-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-btn.active {
            color: #ffffff;
            background: var(--primary-color);
            border-bottom: 2px solid var(--secondary-color);
        }

        .main-container {
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 1.25rem;
            width: 100%;
            flex: 1;
        }

        .hero-section {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .hero-title {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-heading);
            margin-bottom: 0.4rem;
        }

        .hero-sub {
            color: #4b5563;
            font-size: 0.95rem;
            max-width: 680px;
            margin: 0 auto;
        }

        /* Search Card */
        .search-card {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 14px var(--card-shadow);
            margin-bottom: 1.5rem;
        }

        .search-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        @media (max-width: 700px) {
            .search-form {
                flex-direction: column;
            }
        }

        .input-wrap {
            position: relative;
            flex: 1;
            width: 100%;
        }

        .search-input {
            width: 100%;
            background: #ffffff;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            padding: 0.85rem 1.25rem 0.85rem 2.85rem;
            font-size: 1rem;
            color: var(--text-color);
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--button-background);
            box-shadow: 0 0 0 3px rgba(38, 84, 158, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }

        /* Search Button */
        .btn-search {
            background: var(--button-background);
            color: var(--text-light);
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px var(--card-shadow);
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-search:hover {
            background: var(--button-hover-background);
            box-shadow: 0 4px 12px var(--button-hover-shadow);
            transform: translateY(-1px);
        }

        .sample-prompts {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--panel-border);
            font-size: 0.85rem;
            color: #64748b;
        }

        .prompt-tag {
            background: var(--bg-color);
            border: 1px solid var(--panel-border);
            border-radius: 20px;
            padding: 0.2rem 0.65rem;
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .prompt-tag:hover {
            color: #ffffff;
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        /* Results Display */
        .results-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .results-meta-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-pill {
            font-size: 0.78rem;
            padding: 0.25rem 0.6rem;
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: 6px;
            color: #4b5563;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
        }

        .query-vector-box {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-left: 4px solid var(--button-background);
            border-radius: 8px;
            padding: 0.85rem 1.15rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            box-shadow: 0 2px 6px var(--card-shadow);
        }

        .query-vector-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
            color: var(--text-heading);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .vector-values {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            color: var(--button-background);
            word-break: break-all;
            background: var(--bg-color);
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
        }

        .result-item {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px var(--card-shadow);
            position: relative;
        }

        .result-item:hover {
            border-color: var(--button-background);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px var(--button-hover-shadow);
        }

        .result-item.rank-1 {
            border-left: 5px solid var(--secondary-color);
            background: linear-gradient(to right, rgba(255, 92, 10, 0.02), #ffffff);
        }

        .result-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 800;
            font-size: 0.8rem;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
        }

        .rank-1 .rank-badge {
            background: var(--secondary-color);
            color: var(--text-light);
        }

        .rank-2 .rank-badge, .rank-3 .rank-badge {
            background: var(--button-background);
            color: var(--text-light);
        }

        .rank-4 .rank-badge, .rank-5 .rank-badge {
            background: var(--status-inactive);
            color: var(--primary-color);
        }

        .score-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .percentage-score {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .score-progress-wrap {
            width: 120px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .score-progress-bar {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--button-background), var(--secondary-color));
            transition: width 0.5s ease;
        }

        .result-text {
            font-size: 0.98rem;
            color: var(--text-color);
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }

        .result-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.78rem;
            color: #64748b;
            border-top: 1px solid var(--panel-border);
            padding-top: 0.6rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: #ffffff;
            border: 1px dashed var(--panel-border);
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--card-shadow);
        }

        .empty-state-icon {
            font-size: 2.2rem;
            margin-bottom: 0.75rem;
            color: var(--button-background);
        }

        /* Footer matching requested --footer-height: 30px and text */
        footer {
            min-height: var(--footer-height);
            background: var(--header-footer-bg);
            color: var(--text-light);
            border-top: 2px solid var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.02em;
            padding: 0.35rem 1rem;
            margin-top: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>

<header>
    <div class="nav-container">
        <a href="index.php" class="brand">
            <img src="favicon.png" alt="Logo" class="brand-logo">
            <span>RAG Semantic Search</span>
            <span class="brand-tag">1024-D</span>
        </a>
        <nav class="nav-links">
            <a href="upload.php" class="nav-btn">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Upload / Add Records
            </a>
            <a href="search.php" class="nav-btn active">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Cosine Similarity Search
            </a>
        </nav>
    </div>
</header>

<main class="main-container">

    <div class="hero-section">
        <h1 class="hero-title">Cosine Similarity Vector Search</h1>
        <p class="hero-sub">Enter any search query. The system converts it to a 1024-dimensional embedding vector and matches it against indexed documents using CosineSimilarity.php.</p>
    </div>

    <!-- Search Box Card -->
    <div class="search-card">
        <form action="search.php" method="GET" class="search-form">
            <div class="input-wrap">
                <svg class="search-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input 
                    type="text" 
                    name="q" 
                    class="search-input" 
                    placeholder="Search query, e.g. 'What is machine learning and artificial intelligence?'" 
                    value="<?= htmlspecialchars($queryText) ?>"
                    required 
                    autofocus
                >
            </div>
            <button type="submit" class="btn-search">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Compute & Search
            </button>
        </form>

        <div class="sample-prompts">
            <span style="font-weight:700; color:var(--text-heading);">Quick samples:</span>
            <a href="search.php?q=how+does+artificial+intelligence+learn" class="prompt-tag">AI learning process</a>
            <a href="search.php?q=retrieval+augmented+generation+and+knowledge+base" class="prompt-tag">RAG & Vector search</a>
            <a href="search.php?q=high+dimensional+vector+embeddings+and+similarity" class="prompt-tag">Vector embeddings</a>
            <a href="search.php?q=relational+sql+database+management+systems" class="prompt-tag">MySQL database</a>
            <a href="search.php?q=server+side+scripting+dynamic+web+pages" class="prompt-tag">PHP web scripting</a>
        </div>
    </div>

    <?php if ($hasSearched): ?>
        
        <!-- Query Vector Conversion Info -->
        <?php if ($queryVectorStats): ?>
            <div class="query-vector-box">
                <div class="query-vector-header">
                    <span>Query Embedding: "<?= htmlspecialchars($queryText) ?>"</span>
                    <span>Dimension: <?= $queryVectorStats['dimension'] ?> &bull; Active: <?= $queryVectorStats['active_features'] ?></span>
                </div>
                <div class="vector-values">
                    Vector Sample [0..7]: [<?= implode(', ', array_map(fn($v) => sprintf("%.4f", $v), $queryVectorStats['preview'])) ?>, ...]
                </div>
            </div>
        <?php endif; ?>

        <!-- Results Header -->
        <div class="results-meta">
            <div class="results-meta-title">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="var(--button-background)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Top 5 Cosine Similarity Results
            </div>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <span class="meta-pill">Matched: <?= $totalChecked ?> documents</span>
                <span class="meta-pill">Time: <?= $searchTime ?> ms</span>
                <span class="meta-pill">CosineSimilarity.php</span>
            </div>
        </div>

        <!-- Result Cards List -->
        <?php if (empty($results)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128269;</div>
                <h3 style="margin-bottom:0.5rem; color:var(--text-heading);">No Matching Results Found</h3>
                <p style="color:#64748b; font-size:0.9rem;">
                    Ensure records exist in the database. 
                    <a href="upload.php" style="color:var(--button-background); font-weight:700;">Upload sample dictionary</a> to populate data.
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($results as $index => $item): 
                $rank = $index + 1;
                $pct = $item['percentage'];
                $scoreColor = $pct >= 50 ? 'var(--secondary-color)' : ($pct >= 20 ? 'var(--button-background)' : 'var(--primary-color)');
            ?>
                <article class="result-item rank-<?= $rank ?>">
                    <div class="result-top-bar">
                        <div style="display:flex; align-items:center; gap:0.6rem;">
                            <span class="rank-badge">
                                <?php if ($rank === 1): ?>&#127942; Top Match #1<?php else: ?>Rank #<?= $rank ?><?php endif; ?>
                            </span>
                            <span class="meta-pill" style="font-size:0.75rem;">Doc ID: #<?= $item['rag_id'] ?></span>
                        </div>

                        <div class="score-container">
                            <div class="score-progress-wrap" title="Similarity: <?= $pct ?>%">
                                <div class="score-progress-bar" style="width: <?= min(100, max(5, $pct)) ?>%;"></div>
                            </div>
                            <div class="percentage-score" style="color: <?= $scoreColor ?>;">
                                <?= number_format($pct, 2) ?>%
                            </div>
                        </div>
                    </div>

                    <p class="result-text"><?= htmlspecialchars($item['context']) ?></p>

                    <div class="result-footer">
                        <div style="font-family:'JetBrains Mono', monospace; font-size:0.75rem;">
                            Raw Cosine: <span style="color:var(--text-heading); font-weight:700;"><?= sprintf("%.6f", $item['score']) ?></span>
                        </div>
                        <div>Indexed: <?= $item['created_at'] ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <!-- Initial Blank State -->
        <div class="empty-state">
            <div class="empty-state-icon">&#128302;</div>
            <h3 style="margin-bottom:0.5rem; color:var(--text-heading);">Ready for Semantic Search</h3>
            <p style="color:#64748b; font-size:0.9rem; max-width:520px; margin:0 auto 1.25rem auto;">
                Currently <?= number_format($totalInDb) ?> records indexed in database table <code style="color:var(--button-background); font-weight:700;">rag_document</code>. Type a question or query above to search.
            </p>
            <?php if ($totalInDb === 0): ?>
                <a href="upload.php" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:0.5rem; text-decoration:none; background:var(--button-background); color:#ffffff; padding:0.65rem 1.25rem; border-radius:8px; font-weight:700;">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Upload Initial Records & Dictionary
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</main>

<footer>
    Copyright CODERSTATION 2025. Alrights Reserved
</footer>

</body>
</html>
