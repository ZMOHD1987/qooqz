<?php
declare(strict_types=1);
//api/bootstrap_public_ui.php
/**
 * Bootstrap Public UI
 * Platform: QOOQZ
 * Loads config, DB, helpers
 * SAFE version (no assumptions)
 */

/* ===============================
 * BASE PATH
 * =============================== */
define('API_BASE_PATH', realpath(__DIR__));

/* ===============================
 * ERROR LOGGING
 * =============================== */
$logFile = API_BASE_PATH . '/error_debug_index.log';

set_exception_handler(function (Throwable $e) use ($logFile) {
    $msg = sprintf(
        "[%s] UNCAUGHT EXCEPTION: %s | %s:%d\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($msg, 3, $logFile);

    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal Server Error'
    ]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) use ($logFile) {
    if (!(error_reporting() & $severity)) {
        return;
    }

    $msg = sprintf(
        "[%s] ERROR: %s | %s:%d\n",
        date('Y-m-d H:i:s'),
        $message,
        $file,
        $line
    );
    error_log($msg, 3, $logFile);
});

/* ===============================
 * LOAD CONFIG FILES
 * =============================== */
$configFiles = [
    API_BASE_PATH . '/config/constants.php',
    API_BASE_PATH . '/config/config.php',
    API_BASE_PATH . '/config/cors.php',
    API_BASE_PATH . '/config/db.php',
];

foreach ($configFiles as $file) {
    if (is_readable($file)) {
        require_once $file;
    }
}

/* ===============================
 * DATABASE CHECK (USING $conn)
 * =============================== */
if (!function_exists('connectDB')) {
    throw new Exception('connectDB() not defined');
}

$conn = connectDB();

if (!$conn instanceof mysqli) {
    throw new Exception('Database connection ($conn) not initialized');
}

/* ===============================
 * AUTOLOADER (Controllers / Services / Repositories / Helpers)
 * =============================== */
spl_autoload_register(function ($class) {
    $paths = [
        API_BASE_PATH . '/controllers/',
        API_BASE_PATH . '/services/',
        API_BASE_PATH . '/repositories/',
        API_BASE_PATH . '/models/',
        API_BASE_PATH . '/helpers/',
        API_BASE_PATH . '/middleware/',
        API_BASE_PATH . '/validators/',
    ];

    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (is_readable($file)) {
            require_once $file;
            return;
        }
    }
});

/* ===============================
 * BASIC RESPONSE HELPERS
 * =============================== */
if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ===============================
 * LANGUAGE DETECTION (OPTIONAL)
 * =============================== */
$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = substr($lang, 0, 2);

if (!in_array($lang, ['en', 'ar'], true)) {
    $lang = 'en';
}

define('APP_LANG', $lang);

/* ===============================
 * BOOTSTRAP DONE
 * =============================== */
