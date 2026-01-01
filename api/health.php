<?php
// htdocs/api/health.php
// Simple health check endpoint.
// Place this file in htdocs/api/ and call GET /api/health

declare(strict_types=1);

// try to include bootstrap if it exists so we reuse DB/container setup
$base = __DIR__;
$apiLog = $base . '/error_log.txt';
if (!file_exists($apiLog)) {
    @touch($apiLog);
    @chmod($apiLog, 0664);
}

$dbOk = false;
$containerAvailable = false;
$currentUser = null;

try {
    if (is_readable($base . '/bootstrap.php')) {
        require_once $base . '/bootstrap.php';
        // container() helper provided by bootstrap.php
        if (function_exists('container')) {
            $containerAvailable = true;
            $container = container();
            if (!empty($container['db']) && $container['db'] instanceof mysqli) {
                $db = $container['db'];
                $res = @$db->query('SELECT 1');
                $dbOk = $res !== false;
            }
            $currentUser = $container['current_user'] ?? null;
        }
    } else {
        // fallback: try to read config and do quick mysqli check
        if (is_readable($base . '/config/db.php')) {
            $cfg = require $base . '/config/db.php';
            $host = $cfg['host'] ?? getenv('DB_HOST');
            $user = $cfg['user'] ?? getenv('DB_USER');
            $pass = $cfg['pass'] ?? getenv('DB_PASS');
            $name = $cfg['name'] ?? getenv('DB_NAME');
            $port = $cfg['port'] ?? (int)(getenv('DB_PORT') ?: 3306);
            if ($host && $user && $name) {
                $mysqli = @new mysqli($host, $user, $pass, $name, (int)$port);
                if (!$mysqli->connect_errno) {
                    $res = @$mysqli->query('SELECT 1');
                    $dbOk = $res !== false;
                    $mysqli->close();
                }
            }
        }
    }
} catch (Throwable $e) {
    // log to api/error_log.txt and continue
    $msg = "[" . date('c') . "] health.php EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    @file_put_contents($apiLog, $msg . $e->getTraceAsString() . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Prepare response
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

echo json_encode([
    'success' => true,
    'data' => [
        'status' => 'ok',
        'db' => $dbOk,
        'container_loaded' => $containerAvailable,
        'current_user' => $currentUser ? $currentUser : null,
        'time' => date('c')
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;