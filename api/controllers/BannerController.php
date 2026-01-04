<?php
// api/controllers/BannerController.php
// Banner controller functions for the routing system
// Each function receives $container as first parameter

// Load dependencies
require_once __DIR__ . '/../models/Banner.php';
require_once __DIR__ . '/../validators/BannerValidator.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

// Also load RBAC helper for permission functions
if (is_readable(__DIR__ . '/../helpers/RBAC.php')) {
    require_once __DIR__ . '/../helpers/RBAC.php';
}

/**
 * Helper: Check if user has permission to manage banners
 * Uses auth_helper.php functions for proper session and permission checking
 */
function banner_check_permission($container) {
    // Start session safely
    start_session_safe();
    
    // First check: Super admin by role_id
    if (!empty($_SESSION['user']['role_id']) && (int)$_SESSION['user']['role_id'] === 1) {
        return true;
    }
    if (!empty($_SESSION['user']['role']) && (int)$_SESSION['user']['role'] === 1) {
        return true;
    }
    
    // Check if user has super_admin role in roles array
    if (!empty($_SESSION['user']['roles']) && is_array($_SESSION['user']['roles'])) {
        if (in_array('super_admin', $_SESSION['user']['roles'], true) || in_array('admin', $_SESSION['user']['roles'], true)) {
            return true;
        }
    }
    
    // Try to get authenticated user with permissions using auth helper
    if (function_exists('get_authenticated_user_with_permissions')) {
        $user = get_authenticated_user_with_permissions();
        if ($user) {
            // Check if superadmin (role_id == 1 or role == 1)
            if (!empty($user['role_id']) && (int)$user['role_id'] === 1) {
                return true;
            }
            if (!empty($user['role']) && (int)$user['role'] === 1) {
                return true;
            }
            
            // Check roles array
            if (!empty($user['roles']) && is_array($user['roles'])) {
                if (in_array('super_admin', $user['roles'], true) || in_array('admin', $user['roles'], true)) {
                    return true;
                }
            }
            
            // If permissions are empty but user exists, try to load them from DB
            if (empty($user['permissions']) || !is_array($user['permissions']) || count($user['permissions']) === 0) {
                // Try to reload permissions from database using RBAC helper
                if (function_exists('load_user_permissions_into_session') && !empty($user['id'])) {
                    $perms = load_user_permissions_into_session((int)$user['id']);
                    if (!empty($perms)) {
                        $user['permissions'] = $perms;
                        $_SESSION['permissions'] = $perms;
                    }
                }
                // Also try get_current_user from RBAC which loads permissions
                if (function_exists('get_current_user') && (empty($user['permissions']) || count($user['permissions']) === 0)) {
                    $rbacUser = get_current_user();
                    if ($rbacUser && !empty($rbacUser['permissions'])) {
                        $user['permissions'] = $rbacUser['permissions'];
                        $_SESSION['permissions'] = $rbacUser['permissions'];
                    }
                }
            }
            
            // Check if user has manage_banners permission
            if (!empty($user['permissions']) && is_array($user['permissions'])) {
                if (in_array('manage_banners', $user['permissions'], true)) {
                    return true;
                }
            }
        }
    }
    
    // Fallback: Check has_permission function if available
    if (function_exists('has_permission')) {
        if (has_permission('manage_banners')) {
            return true;
        }
    }
    
    // Fallback: Check user_has function from RBAC if available
    if (function_exists('user_has')) {
        if (user_has('manage_banners')) {
            return true;
        }
    }
    
    // Fallback: Check is_superadmin function if available
    if (function_exists('is_superadmin')) {
        if (is_superadmin()) {
            return true;
        }
    }
    
    // Fallback: Check container current_user
    $user = $container['current_user'] ?? null;
    if ($user) {
        if (!empty($user['role_id']) && (int)$user['role_id'] === 1) {
            return true;
        }
        if (!empty($user['roles']) && is_array($user['roles'])) {
            if (in_array('super_admin', $user['roles'], true) || in_array('admin', $user['roles'], true)) {
                return true;
            }
        }
        if (!empty($user['permissions']) && is_array($user['permissions'])) {
            if (in_array('manage_banners', $user['permissions'], true)) {
                return true;
            }
        }
    }
    
    // Final fallback: Check session permissions array
    if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        if (in_array('manage_banners', $_SESSION['permissions'], true)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Helper: Get user's preferred language
 * Uses auth helper and session to determine user language
 */
function banner_get_user_language($container) {
    // Try auth helper first
    if (function_exists('get_authenticated_user_with_permissions')) {
        $user = get_authenticated_user_with_permissions();
        if ($user && !empty($user['preferred_language'])) {
            return $user['preferred_language'];
        }
    }
    
    // Check container current_user
    $user = $container['current_user'] ?? null;
    if ($user && !empty($user['preferred_language'])) {
        return $user['preferred_language'];
    }
    
    // Check session user
    if (!empty($_SESSION['user']['preferred_language'])) {
        return $_SESSION['user']['preferred_language'];
    }
    
    // Check session preferred_language
    if (!empty($_SESSION['preferred_language'])) {
        return $_SESSION['preferred_language'];
    }
    
    // Check GET parameter
    if (!empty($_GET['lang'])) {
        return $_GET['lang'];
    }
    
    return 'en'; // default
}

/**
 * Helper: Validate CSRF token
 */
function banner_validate_csrf() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals((string)$_SESSION['csrf_token'], $token);
}

/**
 * Helper: Log error
 */
function banner_log_error($message) {
    $logFile = __DIR__ . '/../error_log.txt';
    $line = '[' . date('c') . '] BannerController: ' . $message . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * GET /api/banners - List all banners OR get single banner
 * Supports: ?format=json, ?position=..., ?is_active=..., ?q=...
 * Also supports: ?_fetch_row=1&id=X (legacy parameter for single banner)
 */
function Banner_index($container) {
    if (!banner_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }
    
    // Check if this is a single banner request (legacy format)
    if (!empty($_GET['_fetch_row']) && !empty($_GET['id'])) {
        Banner_show($container, $_GET['id']);
        return;
    }
    
    // Also check for direct id parameter
    if (!empty($_GET['id'])) {
        Banner_show($container, $_GET['id']);
        return;
    }
    
    try {
        $opts = [];
        if (isset($_GET['position'])) $opts['position'] = $_GET['position'];
        if (isset($_GET['is_active'])) $opts['is_active'] = $_GET['is_active'];
        if (isset($_GET['q'])) $opts['q'] = $_GET['q'];
        if (isset($_GET['limit'])) $opts['limit'] = (int)$_GET['limit'];
        if (isset($_GET['offset'])) $opts['offset'] = (int)$_GET['offset'];
        
        $lang = banner_get_user_language($container);
        $rows = Banner::all($opts);
        
        // Apply language overlay if requested
        if ($lang && $lang !== 'en') {
            foreach ($rows as &$row) {
                if (!empty($row['id'])) {
                    $translations = Banner::getTranslations($row['id']);
                    if (!empty($translations[$lang])) {
                        $t = $translations[$lang];
                        if (!empty($t['title'])) $row['title'] = $t['title'];
                        if (isset($t['subtitle'])) $row['subtitle'] = $t['subtitle'];
                        if (isset($t['link_text'])) $row['link_text'] = $t['link_text'];
                    }
                }
            }
            unset($row);
        }
        
        $count = is_array($rows) ? count($rows) : 0;
        respond(['success' => true, 'count' => $count, 'data' => $rows]);
    } catch (Throwable $e) {
        banner_log_error('list error: ' . $e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * GET /api/banners/{id} - Get single banner
 */
function Banner_show($container, $id) {
    if (!banner_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }
    
    try {
        $lang = banner_get_user_language($container);
        $banner = Banner::find((int)$id, $lang);
        
        if (!$banner) {
            respond_not_found('Banner not found');
            return;
        }
        
        respond(['success' => true, 'data' => $banner]);
    } catch (Throwable $e) {
        banner_log_error('get error: ' . $e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * POST /api/banners - Create banner OR handle action-based requests
 * Supports: action=save, action=delete, action=toggle_active
 */
function Banner_store($container) {
    if (!banner_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }
    
    // Check for action parameter (admin UI compatibility)
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        // Handle delete action
        if (!banner_validate_csrf()) {
            respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
            return;
        }
        
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            respond_error('Invalid id parameter', HTTP_BAD_REQUEST);
            return;
        }
        
        try {
            $ok = Banner::delete($id);
            if ($ok) {
                respond(['success' => true, 'message' => 'Deleted successfully']);
            } else {
                respond_error('Delete failed', HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (Throwable $e) {
            banner_log_error('delete error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
        return;
    }
    
    if ($action === 'toggle_active') {
        // Handle toggle active action
        if (!banner_validate_csrf()) {
            respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
            return;
        }
        
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) {
            respond_error('Invalid id parameter', HTTP_BAD_REQUEST);
            return;
        }
        
        try {
            $row = Banner::toggleActive($id);
            if ($row) {
                respond(['success' => true, 'message' => 'Toggled successfully', 'data' => $row]);
            } else {
                respond_error('Toggle failed', HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (Throwable $e) {
            banner_log_error('toggle error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
        return;
    }
    
    // Default: handle save action (create or update)
    if (!banner_validate_csrf()) {
        respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
        return;
    }
    
    // Get input (JSON or POST)
    $input = function_exists('get_json_input') ? get_json_input() : [];
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Handle translations if sent as JSON string
    if (isset($input['translations']) && is_string($input['translations'])) {
        $decoded = @json_decode($input['translations'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input['translations'] = $decoded;
        }
    }
    
    // Validate
    $validation = BannerValidator::validate($input);
    if ($validation !== true) {
        respond(['success' => false, 'message' => 'Validation failed', 'errors' => $validation], HTTP_UNPROCESSABLE_ENTITY);
        return;
    }
    
    try {
        $id = Banner::save($input);
        $isUpdate = !empty($input['id']);
        
        // Save translations if provided
        if (!empty($input['translations']) && is_array($input['translations'])) {
            foreach ($input['translations'] as $lang => $tr) {
                if (!is_array($tr)) continue;
                Banner::upsertTranslation($id, $lang, $tr);
            }
        }
        
        $lang = banner_get_user_language($container);
        $banner = Banner::find($id, $lang);
        
        $message = $isUpdate ? 'Updated successfully' : 'Created successfully';
        $code = $isUpdate ? HTTP_OK : HTTP_CREATED;
        respond(['success' => true, 'message' => $message, 'data' => $banner], $code);
    } catch (Throwable $e) {
        banner_log_error('save error: ' . $e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * PUT /api/banners/{id} - Update banner
 */
function Banner_update($container, $id) {
    if (!banner_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }
    
    if (!banner_validate_csrf()) {
        respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
        return;
    }
    
    // Get input (JSON or POST)
    $input = function_exists('get_json_input') ? get_json_input() : [];
    if (empty($input)) {
        $input = $_POST;
    }
    
    $input['id'] = (int)$id;
    
    // Handle translations if sent as JSON string
    if (isset($input['translations']) && is_string($input['translations'])) {
        $decoded = @json_decode($input['translations'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input['translations'] = $decoded;
        }
    }
    
    // Validate
    $validation = BannerValidator::validate($input);
    if ($validation !== true) {
        respond(['success' => false, 'message' => 'Validation failed', 'errors' => $validation], HTTP_UNPROCESSABLE_ENTITY);
        return;
    }
    
    try {
        Banner::save($input);
        
        // Save translations if provided
        if (!empty($input['translations']) && is_array($input['translations'])) {
            foreach ($input['translations'] as $lang => $tr) {
                if (!is_array($tr)) continue;
                Banner::upsertTranslation((int)$id, $lang, $tr);
            }
        }
        
        $lang = banner_get_user_language($container);
        $banner = Banner::find((int)$id, $lang);
        
        respond(['success' => true, 'message' => 'Updated successfully', 'data' => $banner]);
    } catch (Throwable $e) {
        banner_log_error('update error: ' . $e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * DELETE /api/banners/{id} - Delete banner
 */
function Banner_delete($container, $id) {
    if (!banner_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }
    
    if (!banner_validate_csrf()) {
        respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
        return;
    }
    
    try {
        $ok = Banner::delete((int)$id);
        if ($ok) {
            respond(['success' => true, 'message' => 'Deleted successfully']);
        } else {
            respond_error('Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    } catch (Throwable $e) {
        banner_log_error('delete error: ' . $e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * GET /api/banners/{id}/translations - Get translations
 */
function Banner_translations($container, $id) {
    if (!banner_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }
    
    try {
        $translations = Banner::getTranslations((int)$id);
        respond(['success' => true, 'data' => $translations]);
    } catch (Throwable $e) {
        banner_log_error('translations error: ' . $e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * POST /api/banners/{id}/translations - Add/update translation
 */
function Banner_add_translation($container, $id) {
    if (!banner_check_permission($container)) {
        respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        return;
    }
    
    if (!banner_validate_csrf()) {
        respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
        return;
    }
    
    // Get input
    $input = function_exists('get_json_input') ? get_json_input() : [];
    if (empty($input)) {
        $input = $_POST;
    }
    
    $lang = $input['language_code'] ?? '';
    if (empty($lang)) {
        respond_error('Missing language_code', HTTP_BAD_REQUEST);
        return;
    }
    
    try {
        Banner::upsertTranslation((int)$id, $lang, $input);
        $translations = Banner::getTranslations((int)$id);
        respond(['success' => true, 'message' => 'Translation saved', 'data' => $translations]);
    } catch (Throwable $e) {
        banner_log_error('add translation error: ' . $e->getMessage());
        respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * PUT /api/banner_translations/{translation_id} - Update specific translation
 * Note: This is a simplified version, actual implementation would need translation_id lookup
 */
function Banner_update_translation($container, $translation_id) {
    respond_error('Not implemented yet', HTTP_NOT_IMPLEMENTED);
}

/**
 * DELETE /api/banner_translations/{translation_id} - Delete specific translation
 * Note: This is a simplified version, actual implementation would need translation_id lookup
 */
function Banner_delete_translation($container, $translation_id) {
    respond_error('Not implemented yet', HTTP_NOT_IMPLEMENTED);
}

/**
 * BannerController class wrapper for compatibility with routes
 * This class provides static methods that wrap the function-based controller above
 */
if (!class_exists('BannerController')) {
    class BannerController {
        /**
         * Helper to get container using consistent naming
         */
        private static function getContainer() {
            // Try to get container from global scope
            if (isset($GLOBALS['CONTAINER']) && is_array($GLOBALS['CONTAINER'])) {
                return $GLOBALS['CONTAINER'];
            }
            
            // Return empty array as fallback (functions will handle missing DB)
            return [];
        }
        
        /**
         * List all banners OR get single banner
         */
        public static function list($input) {
            Banner_index(self::getContainer());
        }
        
        /**
         * Get single banner
         */
        public static function get($input) {
            $id = $input['id'] ?? 0;
            Banner_show(self::getContainer(), $id);
        }
        
        /**
         * Save (create or update) banner
         */
        public static function save($input) {
            // Ensure action is set for save operation
            if (empty($_POST['action'])) {
                $_POST['action'] = 'save';
            }
            Banner_store(self::getContainer());
        }
        
        /**
         * Delete banner
         */
        public static function delete($input) {
            $id = $input['id'] ?? 0;
            
            // Check if this is action-based delete (via POST with action parameter)
            if (!empty($_POST['action']) && $_POST['action'] === 'delete') {
                // Already set correctly, just call store
                Banner_store(self::getContainer());
            } elseif (!empty($_POST['id'])) {
                // Set action to delete for POST-based delete
                $_POST['action'] = 'delete';
                Banner_store(self::getContainer());
            } else {
                // Direct DELETE method call
                Banner_delete(self::getContainer(), $id);
            }
        }
        
        /**
         * Toggle active status
         */
        public static function toggleActive($input) {
            // Check if action is already set
            if (empty($_POST['action'])) {
                $_POST['action'] = 'toggle_active';
            }
            Banner_store(self::getContainer());
        }
        
        /**
         * Get or update translations
         */
        public static function translations($input) {
            $id = $input['id'] ?? 0;
            
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                Banner_translations(self::getContainer(), $id);
            } else {
                Banner_add_translation(self::getContainer(), $id);
            }
        }
    }
}
