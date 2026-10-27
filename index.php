<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

$pdo = Database::getConnection();
$countStmt = $pdo->query("SELECT COUNT(*) AS total FROM rag_document");
$totalDocs = (int) $countStmt->fetch()['total'];

header("Location: search.php");
exit;
