<?php
// api/routes/vendors.php
// Robust vendor routes dispatcher
// Replaces previous version that assumed static controller methods.
// Place as: /api/routes/vendors.php

// Optional: enable verbose errors for development (comment out in production)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// CORS preflight (adjust origin in production)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    http_response_code(204);
    exit;
}

// Helpers: make sure these files exist
$baseDir = realpath(__DIR__ . '/..'); // points to /api
$controllerFile = $baseDir . '/controllers/VendorController.php';
$dbFile = $baseDir . '/config/db.php';
$responseHelper = $baseDir . '/helpers/response.php';

if (!is_readable($dbFile)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>"DB config not found: {$dbFile}"]);
    exit;
}
require_once $dbFile; // must provide connectDB()

// require response helper if exists
if (is_readable($responseHelper)) require_once $responseHelper;

if (!is_readable($controllerFile)) {
    // If VendorController missing, return error
    http_response_code(500);
    $msg = "VendorController not found at {$controllerFile}";
    if (function_exists('error_log')) error_log($msg);
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}
require_once $controllerFile;

// create DB connection if available
$conn = null;
if (function_exists('connectDB')) {
    try {
        $conn = connectDB();
    } catch (Throwable $e) {
        http_response_code(500);
        $msg = "Database connection failed: " . $e->getMessage();
        error_log($msg);
        echo json_encode(['success'=>false,'message'=>$msg]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'connectDB() not found in config/db.php']);
    exit;
}

// instantiate controller (if class exists)
$ctrlInstance = null;
$controllerClass = 'VendorController';
if (!class_exists($controllerClass)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>"Controller class {$controllerClass} not found"]);
    exit;
}

// Try to instantiate with $conn if constructor expects it
try {
    $rc = new ReflectionClass($controllerClass);
    $ctor = $rc->getConstructor();
    if ($ctor && $ctor->getNumberOfParameters() > 0) {
        // pass $conn if constructor requires parameters
        $ctrlInstance = $rc->newInstance($conn);
    } else {
        $ctrlInstance = $rc->newInstance();
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Controller instantiation failed: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Controller instantiation failed']);
    exit;
}

// parse request URI relative to /api/vendors
$baseSegment = '/api/vendors';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$actionPath = '/';
if (strpos($path, $baseSegment) !== false) {
    $actionPath = substr($path, strpos($path, $baseSegment) + strlen($baseSegment));
}
$actionPath = trim($actionPath, '/');
$actionParts = $actionPath === '' ? [] : explode('/', $actionPath);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$first = $actionParts[0] ?? '';
$isNumericFirst = isset($actionParts[0]) && is_numeric($actionParts[0]);
$id = $isNumericFirst ? (int)$actionParts[0] : null;
$second = $actionParts[1] ?? null;

// small helper to call controller method (instance or static) and output JSON
function call_ctrl($instanceOrClass, $methodName, $args = []) {
    try {
        if (is_object($instanceOrClass) && method_exists($instanceOrClass, $methodName)) {
            $res = call_user_func_array([$instanceOrClass, $methodName], $args);
        } elseif (is_string($instanceOrClass) && method_exists($instanceOrClass, $methodName)) {
            $res = call_user_func_array([$instanceOrClass, $methodName], $args);
        } else {
            http_response_code(404);
            echo json_encode(['success'=>false,'message'=>"Method {$methodName} not implemented"]);
            return;
        }
        // If controller returns array/response, encode it
        if (is_array($res) || is_object($res)) {
            echo json_encode(['success'=>true,'data'=>$res]);
        } elseif ($res === true) {
            echo json_encode(['success'=>true]);
        } else {
            // if controller already echoed/handled response, do nothing; otherwise return it
            echo json_encode(['success'=>true,'result'=>$res]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        error_log("Controller method {$methodName} exception: " . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'Server error','exception'=>$e->getMessage()]);
    }
}

// route dispatch
try {
    // GET /api/vendors -> index/list
    if (($first === '' || $first === null) && $method === 'GET') {
        // prefer instance->index or instance->list/index
        if (method_exists($ctrlInstance, 'index')) call_ctrl($ctrlInstance, 'index', [$_GET]);
        elseif (method_exists($ctrlInstance, 'list')) call_ctrl($ctrlInstance, 'list', [$_GET]);
        else call_ctrl($ctrlInstance, 'getAll', [$_GET]);
        exit;
    }

    // POST /api/vendors -> apply/create/save
    if (($first === '' || $first === null) && $method === 'POST') {
        if (method_exists($ctrlInstance, 'apply')) call_ctrl($ctrlInstance, 'apply', [$_POST]);
        elseif (method_exists($ctrlInstance, 'save')) call_ctrl($ctrlInstance, 'save', [$_POST]);
        else call_ctrl($ctrlInstance, 'create', [$_POST]);
        exit;
    }

    // POST /api/vendors/apply
    if ($first === 'apply' && $method === 'POST') {
        call_ctrl($ctrlInstance, 'apply', [$_POST]);
        exit;
    }

    // GET /api/vendors/{id}  (numeric id)
    if ($isNumericFirst && $method === 'GET' && !$second) {
        if (method_exists($ctrlInstance, 'show')) call_ctrl($ctrlInstance, 'show', [$id]);
        elseif (method_exists($ctrlInstance, 'get')) call_ctrl($ctrlInstance, 'get', [$id]);
        else call_ctrl($ctrlInstance, 'findById', [$id]);
        exit;
    }

    // GET /api/vendors/{slug} (non-numeric)
    if (!$isNumericFirst && $first && $method === 'GET') {
        if (method_exists($ctrlInstance, 'show')) call_ctrl($ctrlInstance, 'show', [$first]);
        elseif (method_exists($ctrlInstance, 'getBySlug')) call_ctrl($ctrlInstance, 'getBySlug', [$first]);
        else call_ctrl($ctrlInstance, 'findBySlug', [$first]);
        exit;
    }

    // PUT /api/vendors/{id} or POST update
    if ($isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second) {
        // parse input
        $input = [];
        if ($method === 'PUT') {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?: [];
        } else {
            $input = $_POST;
        }
        if (method_exists($ctrlInstance, 'update')) call_ctrl($ctrlInstance, 'update', [$id, $input]);
        else call_ctrl($ctrlInstance, 'save', [$input]);
        exit;
    }

    // POST /api/vendors/{id}/documents
    if ($isNumericFirst && $second === 'documents' && $method === 'POST') {
        call_ctrl($ctrlInstance, 'uploadDocument', [$id, $_FILES, $_POST]);
        exit;
    }

    // POST /api/vendors/{id}/approve
    if ($isNumericFirst && $second === 'approve' && $method === 'POST') {
        call_ctrl($ctrlInstance, 'approve', [$id, $_POST]);
        exit;
    }

    // POST /api/vendors/{id}/reject
    if ($isNumericFirst && $second === 'reject' && $method === 'POST') {
        call_ctrl($ctrlInstance, 'reject', [$id, $_POST]);
        exit;
    }

    // GET /api/vendors/{id}/stats
    if ($isNumericFirst && $second === 'stats' && $method === 'GET') {
        call_ctrl($ctrlInstance, 'stats', [$id]);
        exit;
    }

    // POST /api/vendors/{id}/payouts/request
    if ($isNumericFirst && $second === 'payouts' && isset($actionParts[2]) && $actionParts[2] === 'request' && $method === 'POST') {
        call_ctrl($ctrlInstance, 'requestPayout', [$id, $_POST]);
        exit;
    }

    // GET /api/vendors/stats (global)
    if ($first === 'stats' && $method === 'GET') {
        call_ctrl($ctrlInstance, 'stats', [null]);
        exit;
    }

    // fallback
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Endpoint not found']);
    exit;

} catch (Throwable $e) {
    error_log("Vendors route exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','exception'=>$e->getMessage()]);
    exit;
}