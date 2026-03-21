<?php
declare(strict_types=1);

/**
 * /api/get_ad_stats.php
 * Returns aggregated view/click/CTR stats for one or multiple ads.
 *
 * Query parameters:
 *   ad_id   (int)    — single ad ID
 *   ad_ids  (string) — comma-separated ad IDs (e.g. "1,2,3")
 *   days    (int)    — limit to last N days (default: all time)
 *
 * Response: { success: true, data: [ { ad_id, views, clicks, ctr } ] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache');

$baseDir = __DIR__;
require_once $baseDir . '/shared/config/db.php';

// ── Parse & validate ad IDs ────────────────────────────────────
$rawIds = [];
if (!empty($_GET['ad_id']) && ctype_digit((string)$_GET['ad_id'])) {
    $rawIds[] = (int)$_GET['ad_id'];
} elseif (!empty($_GET['ad_ids'])) {
    foreach (explode(',', $_GET['ad_ids']) as $part) {
        $part = trim($part);
        if (ctype_digit($part) && (int)$part > 0) {
            $rawIds[] = (int)$part;
        }
    }
}

if (empty($rawIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ad_id or ad_ids required']);
    exit;
}

$days = isset($_GET['days']) && ctype_digit((string)$_GET['days']) && (int)$_GET['days'] > 0
    ? min((int)$_GET['days'], 730)
    : 0;

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

// ── Build query ────────────────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($rawIds), '?'));
$sql = "SELECT ad_id,
               COALESCE(SUM(views),  0) AS views,
               COALESCE(SUM(clicks), 0) AS clicks
        FROM ad_stats
        WHERE ad_id IN ($placeholders)";
$binds = $rawIds;

if ($days > 0) {
    $sql .= " AND date >= CURDATE() - INTERVAL ? DAY";
    $binds[] = $days;
}

$sql .= " GROUP BY ad_id";

$stmt = $pdo->prepare($sql);
$stmt->execute($binds);
$rows = $stmt->fetchAll();

// ── Calculate CTR and return ───────────────────────────────────
$data = [];
foreach ($rows as $row) {
    $views  = (int)$row['views'];
    $clicks = (int)$row['clicks'];
    $ctr    = $views > 0 ? round(($clicks / $views) * 100, 2) : 0.0;
    $data[] = [
        'ad_id'  => (int)$row['ad_id'],
        'views'  => $views,
        'clicks' => $clicks,
        'ctr'    => $ctr,
    ];
}

// Fill in zeros for IDs with no stats yet
$found = array_column($data, 'ad_id');
foreach ($rawIds as $id) {
    if (!in_array($id, $found, true)) {
        $data[] = ['ad_id' => $id, 'views' => 0, 'clicks' => 0, 'ctr' => 0.0];
    }
}

echo json_encode(['success' => true, 'data' => $data]);
