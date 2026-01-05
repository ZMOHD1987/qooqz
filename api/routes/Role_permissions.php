<?php
// api/routes/Role_permissions.php
// Router entry for Role-Permissions API
declare(strict_types=1);

// basic debug flags (disable in production)
if (defined('ROLE_PERMS_ROUTE_DEV') && ROLE_PERMS_ROUTE_DEV) {
    ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
}

// start session
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) session_start();

// CORS allow (adjust allowed origins as needed)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowed = ['http://localhost','http://127.0.0.1'];
    if (defined('ADMIN_UI_ORIGIN')) $allowed[] = ADMIN_UI_ORIGIN;
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// require controller
$ctrl = dirname(__DIR__) . '/controllers/Role_permissionsController.php';
if (!is_readable($ctrl)) {
    http_response_code(500); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'controller missing']); exit;
}
require_once $ctrl;

// parse id from query or path
$id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) $id = (int)$_GET['id'];
else {
    $path = $_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? '');
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script && strpos($path, $script) === 0) {
        $tail = trim(substr($path, strlen($script)), '/');
        if ($tail !== '' && is_numeric($tail)) $id = (int)$tail;
    }
}

// read JSON body if present
$raw = @file_get_contents('php://input');
$body = [];
if ($raw) {
    $dec = @json_decode($raw, true);
    if (is_array($dec)) $body = $dec;
}
$input = array_merge($_GET ?? [], $body ?? []);

// route
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    if ($id !== null) Role_permissionsController::get(['id' => $id]); else Role_permissionsController::list($input);
    exit;
}
if ($method === 'POST') {
    $action = strtolower(trim((string)($input['action'] ?? $_POST['action'] ?? 'save')));
    switch ($action) {
        case 'delete': Role_permissionsController::delete($input); break;
        case 'assign': Role_permissionsController::assign($input); break;
        case 'remove': Role_permissionsController::remove($input); break;
        default: Role_permissionsController::save($input); break;
    }
    exit;
}
http_response_code(405); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;