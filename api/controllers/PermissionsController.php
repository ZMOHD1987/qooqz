<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// api/controllers/PermissionsController.php
// Permissions controller (RBAC compatible, Banner-style)

// Dependencies
require_once __DIR__ . '/../models/Permissions.php';
require_once __DIR__ . '/../validators/PermissionsValidator.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

if (is_readable(__DIR__ . '/../helpers/RBAC.php')) {
    require_once __DIR__ . '/../helpers/RBAC.php';
}

/* =========================================================
   Helper: check if current user can manage permissions
========================================================= */
function permissions_check_permission($container = [])
{
    start_session_safe();

    // Super admin shortcuts
    $user = $_SESSION['user'] ?? ($container['current_user'] ?? null);
    if ($user) {
        $role_id = (int)($user['role_id'] ?? 0);
        if ($role_id === 1) return true;

        $roles = $user['roles'] ?? [];
        if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) {
            return true;
        }

        $perms = $user['permissions'] ?? [];
        if (in_array('manage_permissions', $perms, true)) return true;
    }

    // Fallback session check
    $session_perms = $_SESSION['permissions'] ?? [];
    if (in_array('manage_permissions', $session_perms, true)) return true;

    return false;
}

/* =========================================================
   CSRF check
========================================================= */
function permissions_validate_csrf()
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], (string)$token);
}

/* =========================================================
   Log errors
========================================================= */
function permissions_log_error($msg)
{
    $file = __DIR__ . '/../error_log.txt';
    $line = '[' . date('c') . '] PermissionsController: ' . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/* =========================================================
   Helper response functions
========================================================= */
function respond($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function respond_error($msg, $status = 500)
{
    respond(['success' => false, 'message' => $msg], $status);
}

function respond_not_found($msg = 'Not Found')
{
    respond(['success' => false, 'message' => $msg], 404);
}

/* =========================================================
   GET list / single permission
========================================================= */
function Permissions_index($container = [])
{
    if (!permissions_check_permission($container)) {
        respond_error('Unauthorized', 401);
    }

    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            Permissions_show($container, $id);
            return;
        }

        $opts = [];
        if (isset($_GET['q'])) $opts['q'] = $_GET['q'];
        if (isset($_GET['limit'])) $opts['limit'] = (int)$_GET['limit'];
        if (isset($_GET['offset'])) $opts['offset'] = (int)$_GET['offset'];

        $rows = Permissions::all($opts);
        respond(['success' => true, 'count' => count($rows), 'data' => $rows]);
    } catch (Throwable $e) {
        permissions_log_error($e->getMessage());
        respond_error('Database error', 500);
    }
}

function Permissions_show($container = [], $id)
{
    if (!permissions_check_permission($container)) {
        respond_error('Unauthorized', 401);
    }

    try {
        $row = Permissions::find((int)$id);
        if (!$row) {
            respond_not_found('Permission not found');
        }
        respond(['success' => true, 'data' => $row]);
    } catch (Throwable $e) {
        permissions_log_error($e->getMessage());
        respond_error('Database error', 500);
    }
}

/* =========================================================
   POST store: save / delete / assign / remove
========================================================= */
function Permissions_store($container = [])
{
    if (!permissions_check_permission($container)) {
        respond_error('Unauthorized', 401);
    }

    $action = $_POST['action'] ?? 'save';

    if (!permissions_validate_csrf()) {
        respond_error('Invalid CSRF token', 403);
    }

    try {
        switch ($action) {
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) respond_error('Invalid permission ID', 400);
                Permissions::delete($id);
                respond(['success' => true, 'message' => 'Deleted successfully']);
                break;

            case 'assign':
                Permissions::assignToRole((int)($_POST['permission_id'] ?? 0), (int)($_POST['role_id'] ?? 0));
                respond(['success' => true, 'message' => 'Assigned successfully']);
                break;

            case 'remove':
                Permissions::removeFromRole((int)($_POST['permission_id'] ?? 0), (int)($_POST['role_id'] ?? 0));
                respond(['success' => true, 'message' => 'Removed successfully']);
                break;

            case 'save':
            default:
                $input = $_POST;
                $validation = PermissionsValidator::validate($input);
                if ($validation !== true) {
                    respond(['success' => false, 'errors' => $validation], 422);
                }

                $id = Permissions::save($input);
                $row = Permissions::find($id);

                respond([
                    'success' => true,
                    'message' => empty($input['id']) ? 'Created successfully' : 'Updated successfully',
                    'data' => $row
                ]);
                break;
        }
    } catch (Throwable $e) {
        permissions_log_error($e->getMessage());
        respond_error('Database error', 500);
    }
}

/* =========================================================
   PermissionsController wrapper (Banner-style)
========================================================= */
if (!class_exists('PermissionsController')) {
    class PermissionsController
    {
        private static function container()
        {
            return $GLOBALS['CONTAINER'] ?? [];
        }

        public static function list($input = [])
        {
            Permissions_index(self::container());
        }

        public static function get($input = [])
        {
            Permissions_show(self::container(), $input['id'] ?? 0);
        }

        public static function save($input = [])
        {
            $_POST['action'] = 'save';
            Permissions_store(self::container());
        }

        public static function delete($input = [])
        {
            $_POST['action'] = 'delete';
            Permissions_store(self::container());
        }

        public static function assign($input = [])
        {
            $_POST['action'] = 'assign';
            Permissions_store(self::container());
        }

        public static function remove($input = [])
        {
            $_POST['action'] = 'remove';
            Permissions_store(self::container());
        }
    }
}
