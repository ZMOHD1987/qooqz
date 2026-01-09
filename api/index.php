<?php
declare(strict_types=1);

/**
 * Unified Public API Entry Point
 * Web + Mobile
 * Platform: QOOQZ
 */

/* ===============================
 * ERROR LOGGING (CRITICAL)
 * =============================== */
$logFile = __DIR__ . '/error_debug_index.log';

ini_set('log_errors', '1');
ini_set('error_log', $logFile);
ini_set('display_errors', '0'); // لا نعرض – فقط نكتب
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/* ===============================
 * GLOBAL ERROR HANDLERS
 * =============================== */
set_error_handler(function ($severity, $message, $file, $line) use ($logFile) {
    $entry = "[" . date('Y-m-d H:i:s') . "] PHP ERROR: $message | $file:$line\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
});

set_exception_handler(function ($e) use ($logFile) {
    $entry = "[" . date('Y-m-d H:i:s') . "] UNCAUGHT EXCEPTION: "
        . $e->getMessage()
        . " | " . $e->getFile()
        . ":" . $e->getLine()
        . "\n" . $e->getTraceAsString() . "\n\n";

    file_put_contents($logFile, $entry, FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal Server Error'
    ]);
});

register_shutdown_function(function () use ($logFile) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $entry = "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: "
            . $error['message']
            . " | " . $error['file']
            . ":" . $error['line'] . "\n";

        file_put_contents($logFile, $entry, FILE_APPEND);
    }
});

/* ===============================
 * HEADERS
 * =============================== */
header('Content-Type: application/json; charset=utf-8');
header('X-Powered-By: QOOQZ-API');

/* ===============================
 * BOOTSTRAP
 * =============================== */
$bootstrap = __DIR__ . '/bootstrap_public_ui.php';

if (!is_readable($bootstrap)) {
    error_log("Bootstrap file missing");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Bootstrap missing']);
    exit;
}

require_once $bootstrap;

/* ===============================
 * ROUTES
 * =============================== */
$routes = [];

$routesFile = __DIR__ . '/routes/public.php';
if (!is_readable($routesFile)) {
    error_log("Routes file missing");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Routes missing']);
    exit;
}

require $routesFile;

if (!is_array($routes) || empty($routes)) {
    error_log("Routes array empty");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No routes defined']);
    exit;
}

/* ===============================
 * DISPATCH
 * =============================== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$path = preg_replace('#^/api#', '', $uri);
$path = '/' . trim($path, '/');
$path = $path === '/' ? '/' : $path;

foreach ($routes as $route) {
    if ($method === $route['method'] && preg_match($route['pattern'], $path)) {
        call_user_func($route['handler']);
        exit;
    }
}

http_response_code(404);
echo json_encode([
    'status' => 'error',
    'message' => 'Route not found',
    'path' => $path
]);
