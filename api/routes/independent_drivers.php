<?php
// api/routes/independent_drivers.php
// Production router for Independent Drivers endpoints
declare(strict_types=1);

$LOG_DIR = __DIR__ . '/../logs';
$LOG_FILE = $LOG_DIR . '/independent_drivers.log';
$FALLBACK = __DIR__ . '/../error_debug.log';
if (!is_dir($LOG_DIR)) @mkdir($LOG_DIR, 0755, true);

function idrv_log($m, $context = []){ 
    global $LOG_FILE, $FALLBACK; 
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $line = '['.date('c').'] '.trim($m) . $contextStr . PHP_EOL;
    @file_put_contents($LOG_FILE, $line, FILE_APPEND|LOCK_EX) ?: @file_put_contents($FALLBACK, $line, FILE_APPEND|LOCK_EX); 
}

// Log script start
idrv_log('=== SCRIPT START ===', [
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'action' => $_REQUEST['action'] ?? null
]);

// Include bootstrap FIRST to ensure session and auth are properly loaded
$bootstrap = __DIR__ . '/../bootstrap.php';
if (is_readable($bootstrap)) {
    try {
        require_once $bootstrap;
        idrv_log('Bootstrap loaded successfully');
    } catch (Throwable $e) {
        idrv_log('FATAL ERROR: Bootstrap loading failed', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success'=>false,'message'=>'Bootstrap error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    idrv_log('ERROR: Bootstrap file not readable', ['path' => $bootstrap]);
}

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) @session_start();

// Acquire DB
$db = null;
if (function_exists('acquire_db')) {
    try { $db = acquire_db(); } catch (Throwable $e) { 
        idrv_log('ERROR: acquire_db failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]); 
    }
}
if (!($db instanceof mysqli)) {
    if (!empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) $db = $GLOBALS['conn'];
    if (!($db instanceof mysqli)) {
        $cfg = __DIR__ . '/../config/db.php';
        if (is_readable($cfg)) {
            require_once $cfg;
            idrv_log('DB config loaded', ['file' => $cfg]);
        }
        if (!empty($conn) && $conn instanceof mysqli) {
            $db = $conn;
            idrv_log('DB connection acquired from config');
        }
    }
}
if (!($db instanceof mysqli)) {
    idrv_log('FATAL ERROR: No DB connection available');
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Database connection error'], JSON_UNESCAPED_UNICODE);
    exit;
}

idrv_log('DB connection established', ['host' => $db->host_info ?? 'unknown']);

// Log database and table info
try {
    $result = $db->query("SELECT DATABASE()");
    if ($result) {
        $row = $result->fetch_row();
        $dbName = $row[0] ?? 'unknown';
        $result->free();
    } else {
        $dbName = 'query_failed';
        idrv_log('WARNING: Could not get database name', ['error' => $db->error]);
    }
    idrv_log('Database info', ['db_name' => $dbName, 'table' => 'independent_drivers']);
} catch (Throwable $e) {
    idrv_log('ERROR: Failed to get database name', ['error' => $e->getMessage()]);
    idrv_log('Database info', ['db_name' => 'error', 'table' => 'independent_drivers']);
}

// Ensure files
$model = __DIR__ . '/../models/IndependentDriver.php';
$validator = __DIR__ . '/../validators/IndependentDriver.php';
$controller = __DIR__ . '/../controllers/IndependentDriverController.php';

idrv_log('Checking required files', [
    'model' => $model,
    'validator' => $validator,
    'controller' => $controller
]);

if (!is_readable($model) || !is_readable($validator) || !is_readable($controller)) {
    $missing = [];
    if (!is_readable($model)) $missing[] = 'model';
    if (!is_readable($validator)) $missing[] = 'validator';
    if (!is_readable($controller)) $missing[] = 'controller';
    
    idrv_log('FATAL ERROR: Missing files', ['missing' => $missing]);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Server misconfiguration'], JSON_UNESCAPED_UNICODE);
    exit;
}

idrv_log('All required files found');

try {
    require_once $model;
    idrv_log('Model loaded');
} catch (Throwable $e) {
    idrv_log('FATAL ERROR: Model loading failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Model loading error'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once $validator;
    idrv_log('Validator loaded');
} catch (Throwable $e) {
    idrv_log('FATAL ERROR: Validator loading failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Validator loading error'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once $controller;
    idrv_log('Controller loaded');
} catch (Throwable $e) {
    idrv_log('FATAL ERROR: Controller loading failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Controller loading error'], JSON_UNESCAPED_UNICODE);
    exit;
}

idrv_log('All files loaded successfully');

try {
    $controllerObj = new IndependentDriverController($db);
    idrv_log('Controller initialized successfully');
} catch (Throwable $e) {
    idrv_log('FATAL ERROR: Controller init failed', [
        'error' => $e->getMessage(), 
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Server error'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Log comprehensive request info
idrv_log('REQUEST INFO', [
    'method' => $method,
    'action' => $action,
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'session_id' => session_id() ?: 'none',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Log POST data (excluding sensitive info and files)
if ($method === 'POST' && !empty($_POST)) {
    $postData = $_POST;
    if (isset($postData['csrf_token'])) $postData['csrf_token'] = '***';
    idrv_log('POST DATA', ['data' => $postData]);
}

// Log FILES data
if (!empty($_FILES)) {
    $filesInfo = [];
    foreach ($_FILES as $key => $file) {
        $filesInfo[$key] = [
            'name' => $file['name'] ?? '',
            'type' => $file['type'] ?? '',
            'size' => $file['size'] ?? 0,
            'error' => $file['error'] ?? -1,
            'tmp_name' => !empty($file['tmp_name']) ? 'present' : 'missing'
        ];
    }
    idrv_log('FILES UPLOAD', ['files' => $filesInfo]);
}

// Log session data for debugging
if (!empty($_SESSION)) {
    idrv_log('SESSION DATA', [
        'user_id' => $_SESSION['user_id'] ?? 'none',
        'username' => $_SESSION['username'] ?? 'none',
        'role' => $_SESSION['role'] ?? 'none',
        'permissions' => $_SESSION['permissions'] ?? []
    ]);
} else {
    idrv_log('WARNING: No session data found');
}

try {
    if ($method === 'GET' && ($action === null || $action === 'list')) {
        idrv_log('ACTION: list');
        $controllerObj->listAction();
    } elseif ($method === 'GET' && $action === 'get') {
        idrv_log('ACTION: get', ['id' => $_GET['id'] ?? 'none']);
        $controllerObj->getAction();
    } elseif ($method === 'POST' && $action === 'create') {
        idrv_log('ACTION: create - START', [
            'table' => 'independent_drivers',
            'fields' => ['user_id', 'full_name', 'phone', 'email', 'vehicle_type', 'vehicle_number', 'license_number', 'license_photo_url', 'id_photo_url', 'status'],
            'upload_dir' => '/uploads/independent_drivers/{id}/'
        ]);
        $controllerObj->createAction();
        idrv_log('ACTION: create - COMPLETED');
    } elseif ($method === 'POST' && $action === 'update') {
        idrv_log('ACTION: update - START', ['id' => $_POST['id'] ?? 'none', 'table' => 'independent_drivers']);
        $controllerObj->updateAction();
        idrv_log('ACTION: update - COMPLETED');
    } elseif ($method === 'POST' && $action === 'delete') {
        idrv_log('ACTION: delete', ['id' => $_POST['id'] ?? 'none', 'table' => 'independent_drivers']);
        $controllerObj->deleteAction();
    } else {
        idrv_log('ERROR: Invalid action', ['method' => $method, 'action' => $action]);
        header('Content-Type: application/json', true, 400);
        echo json_encode(['success'=>false,'message'=>'Invalid action'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    idrv_log('FATAL ERROR: Router exception', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success'=>false,'message'=>'Server error'], JSON_UNESCAPED_UNICODE);
}

idrv_log('=== SCRIPT END ===');