<?php
// htdocs/api/health.php
// Health check with shared/ integration.
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php'; // تحميل shared/

use DatabaseConnection;
use ResponseFormatter;
use Logger;

// Checks
$dbOk = false;
$cacheOk = false;
$containerAvailable = isset($GLOBALS['CONTAINER']);
$currentUser = $GLOBALS['ADMIN_USER'] ?? null;

try {
    // DB check عبر DatabaseConnection
    $pdo = DatabaseConnection::getConnection();
    $stmt = $pdo->query('SELECT 1');
    $dbOk = $stmt->fetchColumn() === 1;
} catch (Throwable $e) {
    Logger::error("Health check DB error: " . $e->getMessage());
}

try {
    // Cache check (إذا كان متوفرًا)
    if (function_exists('CacheManager::get')) {
        CacheManager::set('health_test', 'ok', 10);
        $cacheOk = CacheManager::get('health_test') === 'ok';
    }
} catch (Throwable $e) {
    Logger::warning("Health check cache error: " . $e->getMessage());
}

// Log the check
Logger::info("Health check performed: DB=$dbOk, Cache=$cacheOk");

// Response عبر ResponseFormatter
$data = [
    'status' => 'ok',
    'db_connected' => $dbOk,
    'cache_working' => $cacheOk,
    'container_loaded' => $containerAvailable,
    'current_user' => $currentUser ? ['id' => $currentUser['id'], 'username' => $currentUser['username']] : null,
    'time' => date('c'),
    'version' => '1.0' // من constants إذا أردت
];

ResponseFormatter::success($data, 'Health check passed');