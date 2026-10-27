<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/CosineSimilarity.php';
require_once __DIR__ . '/EmbeddingGenerator.php';

$pdo = Database::getConnection();

$message = null;
$messageType = 'info';

// Handle Single Record Insertion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_single') {
    $context = trim($_POST['context'] ?? '');
    if ($context === '') {
        $message = "Please provide context text to generate embeddings.";
        $messageType = "deleted"; // using status colors
    } else {
        $vector = EmbeddingGenerator::generate($context, 1024);
        $jsonVector = json_encode($vector);

        $stmt = $pdo->prepare("INSERT INTO rag_document (context, embeddings) VALUES (:context, :embeddings)");
        $stmt->execute([
            ':context' => $context,
            ':embeddings' => $jsonVector
        ]);

        $lastId = $pdo->lastInsertId();
        $message = "Document #{$lastId} added successfully with 1024-dimensional embedding vector!";
        $messageType = "active";
    }
}

// Handle Batch Upload from Sample Dictionary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'load_dictionary') {
    $dictionary = require __DIR__ . '/sample_dictionary.php';
    $inserted = 0;

    $stmt = $pdo->prepare("INSERT INTO rag_document (context, embeddings) VALUES (:context, :embeddings)");

    foreach ($dictionary as $item) {
        $text = $item['context'];
        $vector = EmbeddingGenerator::generate($text, 1024);
        $jsonVector = json_encode($vector);

        $stmt->execute([
            ':context' => $text,
            ':embeddings' => $jsonVector
        ]);
        $inserted++;
    }

    $message = "Successfully loaded {$inserted} sample dictionary records with 1024-dim embeddings into rag_document!";
    $messageType = "active";
}

// Handle Delete Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_record') {
    $ragId = (int) ($_POST['rag_id'] ?? 0);
    if ($ragId > 0) {
        $stmt = $pdo->prepare("DELETE FROM rag_document WHERE rag_id = :id");
        $stmt->execute([':id' => $ragId]);
        $message = "Document #{$ragId} deleted successfully.";
        $messageType = "suspended";
    }
}

// Fetch document count and recent records
$countStmt = $pdo->query("SELECT COUNT(*) AS total FROM rag_document");
$totalDocs = (int) $countStmt->fetch()['total'];

$listStmt = $pdo->query("SELECT rag_id, context, created_at FROM rag_document ORDER BY rag_id DESC LIMIT 10");
$recentDocs = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload & Manage Documents | 1024-Dim Embeddings</title>
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

        /* Banner Alerts with Status Colors */
        .banner-alert {
            padding: 0.85rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--panel-border);
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-color);
            box-shadow: 0 2px 6px var(--card-shadow);
        }

        .banner-alert.active {
            background-color: var(--status-active);
            border-color: #86efac;
            color: #065f46;
        }

        .banner-alert.suspended {
            background-color: var(--status-suspended);
            border-color: #fcd34d;
            color: #92400e;
        }

        .banner-alert.deleted {
            background-color: var(--status-deleted);
            border-color: #fca5a5;
            color: #991b1b;
        }

        .banner-alert.inactive {
            background-color: var(--status-inactive);
            border-color: #cbd5e1;
            color: #334155;
        }

        /* Stats Strip */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .stats-strip {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 8px var(--card-shadow);
            border-left: 4px solid var(--primary-color);
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .stat-card:nth-child(2) {
            border-left-color: var(--secondary-color);
        }

        .stat-card:nth-child(3) {
            border-left-color: var(--button-background);
        }

        .stat-lbl {
            font-size: 0.78rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .stat-val {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.02em;
        }

        /* Cards and Grid */
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        @media (max-width: 860px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: #ffffff;
            border: 1px solid var(--panel-border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px var(--card-shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--panel-border);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-desc {
            color: #4b5563;
            font-size: 0.88rem;
            margin-bottom: 1.25rem;
        }

        .badge-pill {
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            background: var(--status-inactive);
            color: var(--primary-color);
            font-weight: 700;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .badge-active {
            background: var(--status-active);
            color: #065f46;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        textarea {
            width: 100%;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: var(--button-background);
            box-shadow: 0 0 0 3px rgba(38, 84, 158, 0.2);
        }

        /* Buttons matching requested colors */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 2px 6px var(--card-shadow);
        }

        .btn-primary {
            background-color: var(--button-background);
            color: var(--text-light);
        }

        .btn-primary:hover {
            background-color: var(--button-hover-background);
            box-shadow: 0 4px 12px var(--button-hover-shadow);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--primary-color);
            color: var(--text-light);
        }

        .btn-secondary:hover {
            background-color: var(--secondary-color);
            box-shadow: 0 4px 12px var(--button-hover-shadow);
            transform: translateY(-1px);
        }

        .btn-danger-outline {
            background: transparent;
            color: #b91c1c;
            border: 1px solid #f87171;
            padding: 0.3rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-danger-outline:hover {
            background-color: var(--status-deleted);
            color: #7f1d1d;
        }

        .dict-preview-box {
            background: var(--bg-color);
            border: 1px solid var(--panel-border);
            border-radius: 8px;
            padding: 0.85rem;
            max-height: 220px;
            overflow-y: auto;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
        }

        .dict-item {
            padding: 0.45rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .dict-item:last-child {
            border-bottom: none;
        }

        .dict-category {
            font-weight: 700;
            color: var(--secondary-color);
            margin-right: 0.4rem;
        }

        /* Table styles */
        .table-responsive {
            overflow-x: auto;
            margin-top: 1rem;
            border-radius: 8px;
            border: 1px solid var(--panel-border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            text-align: left;
            background: #ffffff;
        }

        th {
            background: #f8fafc;
            color: var(--text-heading);
            font-size: 0.78rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--panel-border);
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--panel-border);
            color: var(--text-color);
            vertical-align: middle;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
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
            <a href="upload.php" class="nav-btn active">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Upload / Add Records
            </a>
            <a href="search.php" class="nav-btn">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Cosine Similarity Search
            </a>
        </nav>
    </div>
</header>

<main class="main-container">

    <?php if ($message): ?>
        <div class="banner-alert <?= htmlspecialchars($messageType) ?>">
            <span><?= htmlspecialchars($message) ?></span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;font-weight:bold;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Stats Section -->
    <div class="stats-strip">
        <div class="stat-card">
            <div class="stat-lbl">Total Documents</div>
            <div class="stat-val"><?= number_format($totalDocs) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Vector Dimension</div>
            <div class="stat-val" style="color:var(--secondary-color);">1,024</div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Database Port</div>
            <div class="stat-val" style="color:var(--button-background); font-size:1.35rem; display:flex; align-items:center; gap:0.5rem;">
                <span style="display:inline-block;width:10px;height:10px;background:var(--secondary-color);border-radius:50%;"></span>
                localhost:3307
            </div>
        </div>
    </div>

    <div class="grid-layout">
        <!-- Form 1: Add Custom Document -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="var(--button-background)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Add Single Document
                </div>
                <span class="badge-pill badge-active">1024-dim Vector</span>
            </div>
            <p class="card-desc">Type or paste document context. It will be converted into a normalized 1024-dimensional float vector and saved to the database.</p>

            <form action="upload.php" method="POST">
                <input type="hidden" name="action" value="add_single">
                <div class="form-group">
                    <label for="context">Document Context / Text</label>
                    <textarea id="context" name="context" rows="5" placeholder="e.g. High-dimensional vector spaces allow deep neural representations to be compared using angular distance..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Generate 1024-D Vector & Save to Database
                </button>
            </form>
        </div>

        <!-- Form 2: Batch Upload Sample Dictionary -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="var(--secondary-color)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Upload Sample Dictionary
                </div>
                <span class="badge-pill">sample_dictionary.php</span>
            </div>
            <p class="card-desc">Batch upload the built-in dictionary records with automatic 1024-dimension vector calculation.</p>

            <?php $dict = require __DIR__ . '/sample_dictionary.php'; ?>
            <div class="dict-preview-box">
                <?php foreach (array_slice($dict, 0, 5) as $sample): ?>
                    <div class="dict-item">
                        <span class="dict-category">[<?= htmlspecialchars($sample['category']) ?>]</span>
                        <span><?= htmlspecialchars(mb_substr($sample['context'], 0, 85)) ?>...</span>
                    </div>
                <?php endforeach; ?>
                <div style="color:#64748b; text-align:center; padding-top:0.4rem; font-size:0.75rem;">
                    + <?= count($dict) - 5 ?> more predefined knowledge entries
                </div>
            </div>

            <form action="upload.php" method="POST">
                <input type="hidden" name="action" value="load_dictionary">
                <button type="submit" class="btn btn-secondary" style="width:100%;">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Batch Upload All Dictionary Records (<?= count($dict) ?> Items)
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Records in Database -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <div class="card-title">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="var(--button-background)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7s0 5 16 5M4 12c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3"/></svg>
                Recent Records in `rag_document`
            </div>
            <span class="badge-pill">Latest 10</span>
        </div>

        <?php if (empty($recentDocs)): ?>
            <p style="color:#64748b; text-align:center; padding:2rem 0;">No records found in database yet. Use the upload forms above to add entries.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:75px;">RAG ID</th>
                            <th>Context</th>
                            <th style="width:130px;">Embeddings</th>
                            <th style="width:160px;">Created At</th>
                            <th style="width:85px; text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDocs as $row): ?>
                            <tr>
                                <td class="mono" style="color:var(--button-background); font-weight:700;">#<?= $row['rag_id'] ?></td>
                                <td><?= htmlspecialchars($row['context']) ?></td>
                                <td>
                                    <span class="badge-pill badge-active mono">1024 dims</span>
                                </td>
                                <td class="mono" style="font-size:0.8rem; color:#64748b;"><?= $row['created_at'] ?></td>
                                <td style="text-align:right;">
                                    <form action="upload.php" method="POST" onsubmit="return confirm('Delete document #<?= $row['rag_id'] ?>?');">
                                        <input type="hidden" name="action" value="delete_record">
                                        <input type="hidden" name="rag_id" value="<?= $row['rag_id'] ?>">
                                        <button type="submit" class="btn-danger-outline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<footer>
    Copyright CODERSTATION 2025. Alrights Reserved
</footer>

</body>
</html>
