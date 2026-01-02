<?php
// api/routes/independent_drivers.php
// Production router for Independent Drivers endpoints
declare(strict_types=1);

$LOG_DIR = __DIR__ . '/../logs';
$LOG_FILE = $LOG_DIR . '/independent_drivers.log';
$FALLBACK = __DIR__ . '/../error_debug.log';
if (!is_dir($LOG_DIR)) @mkdir($LOG_DIR, 0755, true);
function idrv_log($m){ global $LOG_FILE, $FALLBACK; @file_put_contents($LOG_FILE, '['.date('c').'] '.trim($m).PHP_EOL, FILE_APPEND|LOCK_EX) ?: @file_put_contents($FALLBACK, '['.date('c').'] '.trim($m).PHP_EOL, FILE_APPEND|LOCK_EX); }

// Include bootstrap FIRST to ensure session and auth are properly loaded
$bootstrap = __DIR__ . '/../bootstrap.php';
if (is_readable($bootstrap)) {
    require_once $bootstrap;
    idrv_log('Bootstrap loaded');
}

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) @session_start();

// Acquire DB
$db = null;
if (function_exists('acquire_db')) {
    try { $db = acquire_db(); } catch (Throwable $e) { idrv_log('acquire_db error: '.$e->getMessage()); }
}
if (!($db instanceof mysqli)) {
    if (!empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) $db = $GLOBALS['conn'];
    if (!($db instanceof mysqli)) {
        $cfg = __DIR__ . '/../config/db.php';
        if (is_readable($cfg)) require_once $cfg;
        if (!empty($conn) && $conn instanceof mysqli) $db = $conn;
    }
}
if (!($db instanceof mysqli)) {
    idrv_log('No DB connection');
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Database connection error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ensure files
$model = __DIR__ . '/../models/IndependentDriver.php';
$validator = __DIR__ . '/../validators/IndependentDriver.php';
$controller = __DIR__ . '/../controllers/IndependentDriverController.php';

if (!is_readable($model) || !is_readable($validator) || !is_readable($controller)) {
    idrv_log('Missing files for IndependentDriver');
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Server misconfiguration'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $model;
require_once $validator;
require_once $controller;

try {
    $controllerObj = new IndependentDriverController($db);
    idrv_log('Controller initialized successfully');
} catch (Throwable $e) {
    idrv_log('Controller init error: '.$e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Server error'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

idrv_log('Dispatch: method='.$method.' action='.$action.' uri=' . ($_SERVER['REQUEST_URI'] ?? '') . ' session_id=' . (session_id() ?: 'none'));

// Log session data for debugging
if (!empty($_SESSION)) {
    idrv_log('Session data present: user_id=' . ($_SESSION['user_id'] ?? 'none') . ' permissions=' . json_encode($_SESSION['permissions'] ?? []));
} else {
    idrv_log('No session data found');
}

try {
    if ($method === 'GET' && ($action === null || $action === 'list')) {
        $controllerObj->listAction();
    } elseif ($method === 'GET' && $action === 'get') {
        $controllerObj->getAction();
    } elseif ($method === 'POST' && $action === 'create') {
        $controllerObj->createAction();
    } elseif ($method === 'POST' && $action === 'update') {
        $controllerObj->updateAction();
    } elseif ($method === 'POST' && $action === 'delete') {
        $controllerObj->deleteAction();
    } else {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success'=>false,'message'=>'Invalid action'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    idrv_log('Router exception: '.$e->getMessage());
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Server error'], JSON_UNESCAPED_UNICODE);
}