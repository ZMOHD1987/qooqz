<?php
declare(strict_types=1);

/**
 * /api/track_click.php
 * Public ad click tracking endpoint.
 * Records a click in ad_stats (no deduplication — clicks should always be counted).
 *
 * Usage: GET /api/track_click.php?id=AD_ID
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache');

$baseDir = __DIR__;
require_once $baseDir . '/shared/config/db.php';

$adId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($adId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ad id']);
    exit;
}

// ── DB connection ──────────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

// ── Verify the ad exists ───────────────────────────────────────
$check = $pdo->prepare("SELECT id FROM ads WHERE id = ? LIMIT 1");
$check->execute([$adId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ad not found']);
    exit;
}

// ── Upsert daily stats ─────────────────────────────────────────
$stmt = $pdo->prepare(
    "INSERT INTO ad_stats (ad_id, date, views, clicks)
     VALUES (?, CURDATE(), 0, 1)
     ON DUPLICATE KEY UPDATE clicks = clicks + 1"
);
$stmt->execute([$adId]);

echo json_encode(['success' => true, 'tracked' => true]);
