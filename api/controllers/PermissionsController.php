<?php
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
   Permission Check
========================================================= */
function permissions_check_permission($container)
{
    start_session_safe();

    // Super admin shortcuts
    if (!empty($_SESSION['user']['role_id']) && (int)$_SESSION['user']['role_id'] === 1) return true;
    if (!empty($_SESSION['user']['role']) && (int)$_SESSION['user']['role'] === 1) return true;

    if (!empty($_SESSION['user']['roles']) && is_array($_SESSION['user']['roles'])) {
        if (in_array('super_admin', $_SESSION['user']['roles'], true) ||
            in_array('admin', $_SESSION['user']['roles'], true)) {
            return true;
        }
    }

    // Auth helper
    if (function_exists('get_authenticated_user_with_permissions')) {
        $user = get_authenticated_user_with_permissions();
        if ($user) {
            if (!empty($user['role_id']) && (int)$user['role_id'] === 1) return true;

            if (!empty($user['roles']) && is_array($user['roles'])) {
                if (in_array('super_admin', $user['roles'], true) ||
                    in_array('admin', $user['roles'], true)) {
                    return true;
                }
            }

            if (empty($user['permissions']) && function_exists('load_user_permissions_into_session')) {
                load_user_permissions_into_session((int)$user['id']);
            }

            if (!empty($user['permissions']) && in_array('manage_permissions', $user['permissions'], true)) {
                return true;
            }
        }
    }

    if (function_exists('has_permission') && has_permission('manage_permissions')) return true;
    if (function_exists('user_has') && user_has('manage_permissions')) return true;
    if (function_exists('is_superadmin') && is_superadmin()) return true;

    // Container fallback
    $user = $container['current_user'] ?? null;
    if ($user) {
        if (!empty($user['role_id']) && (int)$user['role_id'] === 1) return true;
        if (!empty($user['permissions']) && in_array('manage_permissions', $user['permissions'], true)) {
            return true;
        }
    }

    // Session fallback
    if (!empty($_SESSION['permissions']) &&
        in_array('manage_permissions', $_SESSION['permissions'], true)) {
        return true;
    }

    return false;
}

/* =========================================================
   CSRF
========================================================= */
function permissions_validate_csrf()
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    return !empty($token) &&
           !empty($_SESSION['csrf_token']) &&
           hash_equals((string)$_SESSION['csrf_token'], (string)$token);
}

/* =========================================================
   Logger
========================================================= */
function permissions_log_error($msg)
{
    $file = __DIR__ . '/../error_log.txt';
    $line = '[' . date('c') . '] PermissionsController: ' . $msg . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/* =========================================================
   GET /api/permissions
========================================================= */
function Permissions_index($container)
{
    if (!permissions_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }

    try {
        if (!empty($_GET['id'])) {
            Permissions_show($container, (int)$_GET['id']);
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
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/* =========================================================
   GET /api/permissions/{id}
========================================================= */
function Permissions_show($container, $id)
{
    if (!permissions_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }

    try {
        $row = Permissions::find((int)$id);
        if (!$row) {
            respond_not_found('Permission not found');
            return;
        }
        respond(['success' => true, 'data' => $row]);
    } catch (Throwable $e) {
        permissions_log_error($e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/* =========================================================
   POST /api/permissions
   action = save | delete | assign | remove
========================================================= */
function Permissions_store($container)
{
    if (!permissions_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }

    $action = $_POST['action'] ?? 'save';

    if (!permissions_validate_csrf()) {
        respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
        return;
    }

    try {
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            Permissions::delete($id);
            respond(['success' => true, 'message' => 'Deleted successfully']);
            return;
        }

        if ($action === 'assign') {
            Permissions::assignToRole(
                (int)$_POST['permission_id'],
                (int)$_POST['role_id']
            );
            respond(['success' => true]);
            return;
        }

        if ($action === 'remove') {
            Permissions::removeFromRole(
                (int)$_POST['permission_id'],
                (int)$_POST['role_id']
            );
            respond(['success' => true]);
            return;
        }

        // SAVE
        $input = function_exists('get_json_input') ? get_json_input() : $_POST;
        $validation = PermissionsValidator::validate($input);
        if ($validation !== true) {
            respond(['success' => false, 'errors' => $validation], HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        $id = Permissions::save($input);
        $row = Permissions::find($id);

        respond([
            'success' => true,
            'message' => empty($input['id']) ? 'Created successfully' : 'Updated successfully',
            'data' => $row
        ]);
    } catch (Throwable $e) {
        permissions_log_error($e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/* =========================================================
   Controller Wrapper (Banner-style)
========================================================= */
if (!class_exists('PermissionsController')) {
    class PermissionsController
    {
        private static function container()
        {
            return $GLOBALS['CONTAINER'] ?? [];
        }

        public static function list($input)
        {
            Permissions_index(self::container());
        }

        public static function get($input)
        {
            Permissions_show(self::container(), $input['id'] ?? 0);
        }

        public static function save($input)
        {
            $_POST['action'] = 'save';
            Permissions_store(self::container());
        }

        public static function delete($input)
        {
            $_POST['action'] = 'delete';
            Permissions_store(self::container());
        }

        public static function assign($input)
        {
            $_POST['action'] = 'assign';
            Permissions_store(self::container());
        }

        public static function remove($input)
        {
            $_POST['action'] = 'remove';
            Permissions_store(self::container());
        }
    }
}
