<?php
// api/controllers/RolesController.php
// Controller for roles management (list, show, save, delete)
declare(strict_types=1);

// include helpers if present
if (is_readable(__DIR__ . '/../bootstrap.php')) require_once __DIR__ . '/../bootstrap.php';
if (is_readable(__DIR__ . '/../helpers/auth_helper.php')) require_once __DIR__ . '/../helpers/auth_helper.php';
if (is_readable(__DIR__ . '/../helpers/RBAC.php')) require_once __DIR__ . '/../helpers/RBAC.php';

require_once __DIR__ . '/../models/Roles.php';
require_once __DIR__ . '/../validators/RolesValidator.php';

// Small helpers (if missing in bootstrap)
if (!function_exists('respond')) {
    function respond($payload = [], $status = 200) {
        if (!headers_sent()) {
            http_response_code((int)$status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
if (!function_exists('respond_error')) {
    function respond_error($message = 'Error', $status = 500) {
        respond(['success' => false, 'message' => $message], $status);
    }
}
if (!function_exists('respond_not_found')) {
    function respond_not_found($message = 'Not Found') {
        respond(['success' => false, 'message' => $message], 404);
    }
}
if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = @file_get_contents('php://input');
        if (!$raw) return [];
        $d = @json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}

function start_session_safe() { if (session_status() === PHP_SESSION_NONE) @session_start(); }

function roles_check_permission($container = [])
{
    start_session_safe();

    $sessionUser = $_SESSION['user'] ?? null;
    if ($sessionUser) {
        if (!empty($sessionUser['role_id']) && (int)$sessionUser['role_id'] === 1) return true;
        if (!empty($sessionUser['roles']) && is_array($sessionUser['roles']) &&
            (in_array('super_admin', $sessionUser['roles'], true) || in_array('admin', $sessionUser['roles'], true))) return true;
        if (!empty($sessionUser['permissions']) && is_array($sessionUser['permissions']) &&
            in_array('manage_roles', $sessionUser['permissions'], true)) return true;
    }

    if (function_exists('get_authenticated_user_with_permissions')) {
        try {
            $u = get_authenticated_user_with_permissions();
            if ($u) {
                if (!empty($u['role_id']) && (int)$u['role_id'] === 1) return true;
                if (!empty($u['permissions']) && in_array('manage_roles', $u['permissions'], true)) return true;
            }
        } catch (Throwable $e) {}
    }

    if (function_exists('has_permission') && has_permission('manage_roles')) return true;
    if (!empty($_SESSION['permissions']) && in_array('manage_roles', $_SESSION['permissions'], true)) return true;

    $user = $container['current_user'] ?? ($container['user'] ?? null);
    if ($user) {
        if (!empty($user['role_id']) && (int)$user['role_id'] === 1) return true;
        if (!empty($user['permissions']) && in_array('manage_roles', $user['permissions'], true)) return true;
    }

    return false;
}

function roles_validate_csrf()
{
    start_session_safe();
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals((string)$_SESSION['csrf_token'], (string)$token);
}

function roles_log_error($msg)
{
    $file = __DIR__ . '/../error_log.txt';
    @file_put_contents($file, '[' . date('c') . '] RolesController: ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/* Controller functions */
function Roles_index($container = [])
{
    if (!roles_check_permission($container)) {
        respond_error('Unauthorized', 401);
        return;
    }
    try {
        $rows = Roles::all();
        respond(['success' => true, 'count' => count($rows), 'data' => $rows]);
    } catch (Throwable $e) {
        roles_log_error($e->getMessage());
        respond_error('Database error', 500);
    }
}

function Roles_show($container = [], $id = 0)
{
    if (!roles_check_permission($container)) {
        respond_error('Unauthorized', 401);
        return;
    }
    try {
        $row = Roles::find((int)$id);
        if (!$row) { respond_not_found('Role not found'); return; }
        respond(['success' => true, 'data' => $row]);
    } catch (Throwable $e) {
        roles_log_error($e->getMessage());
        respond_error('Database error', 500);
    }
}

function Roles_store($container = [])
{
    if (!roles_check_permission($container)) {
        respond_error('Unauthorized', 401);
        return;
    }

    // Accept JSON or form data
    $input = get_json_input();
    if (!is_array($input) || empty($input)) $input = $_POST ?: $input;
    if (!empty($_GET)) $input = array_merge($input, $_GET);

    if (!roles_validate_csrf()) {
        respond_error('Invalid CSRF token', 403);
        return;
    }

    $action = strtolower(trim((string)($input['action'] ?? 'save')));

    try {
        switch ($action) {
            case 'delete':
                $id = isset($input['id']) ? (int)$input['id'] : 0;
                if ($id <= 0) { respond(['success' => false, 'errors' => ['id' => 'Invalid id']]); return; }
                $ok = Roles::delete($id);
                respond(['success' => (bool)$ok, 'message' => $ok ? 'Deleted' : 'Delete failed']);
                return;

            case 'save':
            default:
                $validation = RolesValidator::validate($input);
                if ($validation !== true) { respond(['success' => false, 'errors' => $validation], 422); return; }
                $id = Roles::save($input);
                $row = Roles::find((int)$id);
                respond(['success' => true, 'message' => empty($input['id']) ? 'Created' : 'Updated', 'data' => $row]);
                return;
        }
    } catch (Throwable $e) {
        roles_log_error($e->getMessage());
        respond_error('Database error', 500);
    }
}

/* Controller wrapper class */
if (!class_exists('RolesController')) {
    class RolesController
    {
        private static function container() { return $GLOBALS['CONTAINER'] ?? []; }

        public static function list($input = []) { Roles_index(self::container()); }
        public static function get($input = []) { Roles_show(self::container(), $input['id'] ?? 0); }
        public static function save($input = []) { if (!empty($input)) foreach ($input as $k=>$v) if (!isset($_POST[$k])) $_POST[$k]=$v; Roles_store(self::container()); }
        public static function delete($input = []) { if (!empty($input)) foreach ($input as $k=>$v) if (!isset($_POST[$k])) $_POST[$k]=$v; $_POST['action']='delete'; Roles_store(self::container()); }
    }
}
