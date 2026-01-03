<?php
// api/controllers/BannerController.php
// Controller for Banners API with proper error handling and permissions

class BannerController
{
    /**
     * Check if user has permission to manage banners
     * Checks session permissions or role_id == 1 (ADMIN)
     */
    private static function checkPermission()
    {
        // Check session permissions
        if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            if (in_array('manage_banners', $_SESSION['permissions'], true)) {
                return true;
            }
        }

        // Check if user is admin (role_id == 1)
        if (!empty($_SESSION['user']['role_id']) && (int)$_SESSION['user']['role_id'] === 1) {
            return true;
        }

        // Check RBAC if available
        if (isset($GLOBALS['rbac']) && is_object($GLOBALS['rbac'])) {
            if (method_exists($GLOBALS['rbac'], 'hasPermission')) {
                return (bool)$GLOBALS['rbac']->hasPermission('manage_banners');
            }
        }

        // Check current_user in container
        if (function_exists('container')) {
            $user = container('current_user');
            if ($user && !empty($user['role_id']) && (int)$user['role_id'] === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate CSRF token
     */
    private static function validateCsrf($input)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $token = isset($input['csrf_token']) ? (string)$input['csrf_token'] : '';
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    /**
     * Log error to api/error_log.txt
     */
    private static function logError($message)
    {
        $logFile = __DIR__ . '/../error_log.txt';
        $line = '[' . date('c') . '] BannerController: ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * List banners
     * GET request with optional filters
     */
    public static function list($input = [])
    {
        if (!self::checkPermission()) {
            respond_error('Unauthorized', HTTP_UNAUTHORIZED);
            return;
        }

        try {
            $opts = [];
            if (isset($input['position'])) $opts['position'] = $input['position'];
            if (isset($input['is_active'])) $opts['is_active'] = $input['is_active'];
            if (isset($input['q'])) $opts['q'] = $input['q'];
            if (isset($input['limit'])) $opts['limit'] = (int)$input['limit'];
            if (isset($input['offset'])) $opts['offset'] = (int)$input['offset'];

            $rows = Banner::all($opts);
            $count = is_array($rows) ? count($rows) : 0;

            respond(['success' => true, 'count' => $count, 'data' => $rows]);
        } catch (Throwable $e) {
            self::logError('list error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get single banner
     * GET request with id parameter
     */
    public static function get($input = [])
    {
        if (!self::checkPermission()) {
            respond_error('Unauthorized', HTTP_UNAUTHORIZED);
            return;
        }

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        $lang = isset($input['lang']) ? $input['lang'] : null;

        if (!$id) {
            respond_error('Missing id parameter', HTTP_BAD_REQUEST);
            return;
        }

        try {
            $banner = Banner::find($id, $lang);
            if (!$banner) {
                respond_not_found('Banner not found');
                return;
            }

            respond(['success' => true, 'data' => $banner]);
        } catch (Throwable $e) {
            self::logError('get error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Save (create or update) banner
     * POST request with banner data
     */
    public static function save($input = [])
    {
        if (!self::checkPermission()) {
            respond_error('Unauthorized', HTTP_UNAUTHORIZED);
            return;
        }

        if (!self::validateCsrf($input)) {
            respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
            return;
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

            // Save translations if provided
            if (!empty($input['translations']) && is_array($input['translations'])) {
                foreach ($input['translations'] as $lang => $tr) {
                    if (!is_array($tr)) continue;
                    Banner::upsertTranslation($id, $lang, $tr);
                }
            }

            $banner = Banner::find($id);
            $message = !empty($input['id']) ? 'Updated successfully' : 'Created successfully';

            respond(['success' => true, 'message' => $message, 'data' => $banner]);
        } catch (Throwable $e) {
            self::logError('save error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete banner
     * POST request with id parameter
     */
    public static function delete($input = [])
    {
        if (!self::checkPermission()) {
            respond_error('Unauthorized', HTTP_UNAUTHORIZED);
            return;
        }

        if (!self::validateCsrf($input)) {
            respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
            return;
        }

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
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
            self::logError('delete error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Toggle active status
     * POST request with id parameter
     */
    public static function toggleActive($input = [])
    {
        if (!self::checkPermission()) {
            respond_error('Unauthorized', HTTP_UNAUTHORIZED);
            return;
        }

        if (!self::validateCsrf($input)) {
            respond_error('Invalid CSRF token', HTTP_FORBIDDEN);
            return;
        }

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
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
            self::logError('toggleActive error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get translations for a banner
     * POST request with id parameter
     */
    public static function translations($input = [])
    {
        if (!self::checkPermission()) {
            respond_error('Unauthorized', HTTP_UNAUTHORIZED);
            return;
        }

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        if (!$id) {
            respond_error('Invalid id parameter', HTTP_BAD_REQUEST);
            return;
        }

        try {
            $translations = Banner::getTranslations($id);
            respond(['success' => true, 'data' => $translations]);
        } catch (Throwable $e) {
            self::logError('translations error: ' . $e->getMessage());
            respond_error('Database error', HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
