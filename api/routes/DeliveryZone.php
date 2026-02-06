<?php
// api/routes/DeliveryZone.php
// Router for DeliveryZone endpoints - minimal, defensive, ensures correct model/controller are used
// Endpoints:
//   GET  ?action=list
//   GET  ?action=get&id=...
//   POST ?action=create_zone
//   POST ?action=update_zone
//   POST ?action=delete_zone
declare(strict_types=1);
// مؤقت - تسجيل الملفات المضمنة وتشخيص من يطبع النتيجة
@file_put_contents(__DIR__ . '/../logs/include_debug.log', '['.date('c').'] START REQUEST URI=' . ($_SERVER['REQUEST_URI'] ?? '') . PHP_EOL, FILE_APPEND);
@file_put_contents(__DIR__ . '/../logs/include_debug.log', '['.date('c').'] Included files header:' . PHP_EOL . print_r(get_included_files(), true) . PHP_EOL, FILE_APPEND);

// Capture output buffer snapshot (if أي شيء طُبع قبل الإكمال)
$ob = @file_get_contents('php://input');
@file_put_contents(__DIR__ . '/../logs/include_debug.log', '['.date('c').'] php://input snippet: ' . substr($ob,0,200) . PHP_EOL, FILE_APPEND);
// Logging setup (writes to api/logs/deliveryzone.log or falls back to api/error_debug.log)
$LOG_DIR = __DIR__ . '/../logs';
$LOG_FILE = $LOG_DIR . '/deliveryzone.log';
$FALLBACK_LOG = __DIR__ . '/../error_debug.log';
if (!is_dir($LOG_DIR)) @mkdir($LOG_DIR, 0755, true);
if (!is_writable($LOG_DIR)) $LOG_FILE = $FALLBACK_LOG;
function dz_log(string $msg): void {
    global $LOG_FILE, $FALLBACK_LOG;
    $line = '[' . date('c') . '] ' . trim($msg) . PHP_EOL;
    $w = @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    if ($w === false && $LOG_FILE !== $FALLBACK_LOG) @file_put_contents($FALLBACK_LOG, $line, FILE_APPEND | LOCK_EX);
}

// Load central bootstrap if available (session, CORS, helpers, acquire_db)
$bootstrap = __DIR__ . '/../bootstrap.php';
if (is_readable($bootstrap)) {
    require_once $bootstrap;
}

// Minimal response helpers (use project's helpers if available)
if (!function_exists('json_ok')) {
    function json_ok($d = [], $c = 200) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $c);
        $out = is_array($d) ? array_merge(['success' => true], $d) : ['success' => true, 'data' => $d];
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('json_error')) {
    function json_error($m = 'Error', $c = 400, $e = []) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $c);
        $out = array_merge(['success' => false, 'message' => $m], $e);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// start session if not started by bootstrap
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) @session_start();

// Log incoming request summary (truncated fields)
try {
    $summary = [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'get' => $_GET,
        'post_keys' => array_keys($_POST),
        'remote' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    dz_log('Incoming: ' . json_encode($summary, JSON_UNESCAPED_UNICODE));
} catch (Throwable $t) {
    dz_log('Incoming log error: ' . $t->getMessage());
}

// Acquire DB (use acquire_db if present)
$db = null;
if (function_exists('acquire_db')) {
    try { $db = acquire_db(); } catch (Throwable $e) { dz_log('acquire_db() error: ' . $e->getMessage()); }
}
if (!($db instanceof mysqli)) {
    // fallback attempts
    if (function_exists('connectDB')) {
        try { $tmp = connectDB(); if ($tmp instanceof mysqli) $db = $tmp; } catch (Throwable $e) { dz_log('connectDB() error: '.$e->getMessage()); }
    }
    foreach (['conn','db','mysqli'] as $n) {
        if (!empty($GLOBALS[$n]) && $GLOBALS[$n] instanceof mysqli) { $db = $GLOBALS[$n]; break; }
    }
    if (!($db instanceof mysqli)) {
        $cfg = __DIR__ . '/../config/db.php';
        if (is_readable($cfg)) {
            try { require_once $cfg; } catch (Throwable $e) { dz_log('include config/db.php failed: '.$e->getMessage()); }
            if (!empty($conn) && $conn instanceof mysqli) $db = $conn;
            if (!empty($db) && $db instanceof mysqli) $db = $db;
        }
    }
}
if (!($db instanceof mysqli)) {
    dz_log('No DB available for DeliveryZone route');
    json_error('Database connection error', 500);
}

// Ensure we include the correct model / validator / controller for DeliveryZone specifically
$modelPath = __DIR__ . '/../models/DeliveryZone.php';
$validatorPath = __DIR__ . '/../validators/DeliveryZone.php';
$ctrlPath = __DIR__ . '/../controllers/DeliveryZoneController.php';

if (!is_readable($modelPath) || !is_readable($validatorPath) || !is_readable($ctrlPath)) {
    dz_log("Missing DeliveryZone files: model={$modelPath} readable=" . (is_readable($modelPath)?'yes':'no') .
           " validator={$validatorPath} readable=" . (is_readable($validatorPath)?'yes':'no') .
           " controller={$ctrlPath} readable=" . (is_readable($ctrlPath)?'yes':'no'));
    json_error('Server misconfiguration', 500);
}

// require files (these should be the DeliveryZone-specific files)
require_once $modelPath;
require_once $validatorPath;
require_once $ctrlPath;

// instantiate controller (defensive)
try {
    $controller = new DeliveryZoneController($db);
    dz_log('Instantiated controller: ' . get_class($controller));
} catch (Throwable $e) {
    dz_log('Controller instantiation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    dz_log($e->getTraceAsString());
    json_error('Server error', 500);
}

// routing
$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Accept only expected action prefixes (defensive)
$ALLOWED = ['list','get','create_zone','update_zone','delete_zone','create_company','update_company','delete_company'];
if ($action !== null) {
    if (preg_match('/^([a-z0-9_]+)/i', $action, $m)) {
        $action_clean = $m[1];
    } else {
        $action_clean = $action;
    }
    if (!in_array($action_clean, $ALLOWED, true)) {
        dz_log("Blocked unknown action: raw={$action} cleaned={$action_clean}");
        json_error('Unknown action', 400);
    }
    $action = $action_clean;
}

dz_log("Dispatching: method={$method} action={$action} id=" . ($_REQUEST['id'] ?? ''));

try {
    if ($method === 'GET' && ($action === 'list' || $action === null)) {
        $controller->listAction();
    } elseif ($method === 'GET' && $action === 'get') {
        $controller->getAction();
    } elseif ($method === 'POST' && in_array($action, ['create_zone','create_company'], true)) {
        $controller->createAction();
    } elseif ($method === 'POST' && in_array($action, ['update_zone','update_company'], true)) {
        $controller->updateAction();
    } elseif ($method === 'POST' && in_array($action, ['delete_zone','delete_company'], true)) {
        $controller->deleteAction();
    } else {
        dz_log("Invalid action or method: method={$method} action={$action}");
        json_error('Invalid action', 400);
    }
} catch (Throwable $e) {
    dz_log('DeliveryZone router exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    dz_log($e->getTraceAsString());
    try { dz_log('REQUEST_SNAPSHOT: ' . json_encode(['get'=>$_GET,'post'=>$_POST,'request'=>$_REQUEST], JSON_UNESCAPED_UNICODE)); } catch (Throwable $_) {}
    json_error('Server error', 500);
}