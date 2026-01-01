<?php
// htdocs/api/controllers/BannerController.php
// Controller for banners API (list, get, save, delete, toggle, translations)
// Expects:
// - helpers/response.php defining respond(), respond_success(), respond_error(), json_input()
// - models/Banner.php with Banner::... methods
// - validators/BannerValidator.php with BannerValidator::validate()
// - session started by wrapper and optional require_manage_users() for permissions

// Load dependencies (paths relative to this file)
if (!defined('BANNER_CONTROLLER_LOADED')) {
    define('BANNER_CONTROLLER_LOADED', true);

    require_once __DIR__ . '/../helpers/response.php';
    require_once __DIR__ . '/../models/Banner.php';
    require_once __DIR__ . '/../validators/BannerValidator.php';
}

/**
 * BannerController
 */
class BannerController
{
    // Optional permission check helper
    protected static function checkPermission()
    {
        if (function_exists('require_manage_users')) {
            try {
                return require_manage_users();
            } catch (Throwable $e) {
                return false;
            }
        }
        // if no permission helper, allow by default (wrapper may have already checked)
        return true;
    }

    // Validate CSRF token for POST requests (returns true/false)
    protected static function validateCsrf($input)
    {
        if (!isset($_SESSION)) session_start();
        $token = isset($input['csrf_token']) ? (string)$input['csrf_token'] : '';
        if (empty($token)) return false;
        if (empty($_SESSION['csrf_token'])) return false;
        return hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    // GET / list
    // $input can be $_GET
    public static function list($input = [])
    {
        if (!self::checkPermission()) return respond_error('Unauthorized', HTTP_UNAUTHORIZED);

        $opts = [];
        if (isset($input['position'])) $opts['position'] = $input['position'];
        if (isset($input['is_active'])) $opts['is_active'] = $input['is_active'];
        if (isset($input['q'])) $opts['q'] = $input['q'];
        if (isset($input['limit'])) $opts['limit'] = (int)$input['limit'];
        if (isset($input['offset'])) $opts['offset'] = (int)$input['offset'];

        try {
            $rows = Banner::all($opts);
            $count = is_array($rows) ? count($rows) : 0;
            respond(['success' => true, 'count' => $count, 'data' => $rows]);
        } catch (Throwable $e) {
            respond_error('DB error', HTTP_INTERNAL_SERVER_ERROR, null, ERROR_CODE_DATABASE);
        }
    }

    // GET single
    public static function get($input = [])
    {
        if (!self::checkPermission()) return respond_error('Unauthorized', HTTP_UNAUTHORIZED);

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        $lang = isset($input['lang']) ? $input['lang'] : null;
        if (!$id) return respond_error('Missing id', HTTP_BAD_REQUEST);

        try {
            $b = Banner::find($id, $lang);
            if (!$b) return respond_not_found('Banner not found');
            respond(['success' => true, 'data' => $b]);
        } catch (Throwable $e) {
            respond_error('DB error', HTTP_INTERNAL_SERVER_ERROR, null, ERROR_CODE_DATABASE);
        }
    }

    // POST save (create or update)
    // $input is assoc array (from json_input() or $_POST)
    public static function save($input = [])
    {
        // permission + csrf
        if (!self::checkPermission()) return respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        if (!self::validateCsrf($input)) return respond_error('Invalid CSRF', HTTP_FORBIDDEN);

        // ensure translations field is decoded if sent as JSON string
        if (isset($input['translations']) && is_string($input['translations'])) {
            $decoded = @json_decode($input['translations'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input['translations'] = $decoded;
            } else {
                // If it's an invalid JSON string, we ignore translations rather than fail validation here
                $input['translations'] = null;
            }
        }

        // validate input
        $v = BannerValidator::validate($input);
        if ($v !== true) {
            return respond(['success' => false, 'message' => 'Validation failed', 'errors' => $v], HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $id = Banner::save($input);
            // save translations if provided as array
            if (!empty($input['translations']) && is_array($input['translations'])) {
                foreach ($input['translations'] as $lang => $tr) {
                    // ensure $tr is array
                    if (!is_array($tr)) continue;
                    Banner::upsertTranslation($id, $lang, $tr);
                }
            }
            $banner = Banner::find($id);
            respond(['success' => true, 'message' => (!empty($input['id']) ? 'Updated' : 'Created'), 'data' => $banner]);
        } catch (Throwable $e) {
            // log if available
            error_log('BannerController::save exception: ' . $e->getMessage());
            respond_error('DB error', HTTP_INTERNAL_SERVER_ERROR, null, ERROR_CODE_DATABASE);
        }
    }

    // POST delete
    public static function delete($input = [])
    {
        if (!self::checkPermission()) return respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        if (!self::validateCsrf($input)) return respond_error('Invalid CSRF', HTTP_FORBIDDEN);

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        if (!$id) return respond_error('Invalid id', HTTP_BAD_REQUEST);

        try {
            $ok = Banner::delete($id);
            if ($ok) respond(['success' => true, 'message' => 'Deleted']);
            else respond_error('Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        } catch (Throwable $e) {
            error_log('BannerController::delete exception: ' . $e->getMessage());
            respond_error('DB error', HTTP_INTERNAL_SERVER_ERROR, null, ERROR_CODE_DATABASE);
        }
    }

    // POST toggle_active
    public static function toggleActive($input = [])
    {
        if (!self::checkPermission()) return respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        if (!self::validateCsrf($input)) return respond_error('Invalid CSRF', HTTP_FORBIDDEN);

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        if (!$id) return respond_error('Invalid id', HTTP_BAD_REQUEST);

        try {
            $row = Banner::toggleActive($id);
            if ($row) respond(['success' => true, 'message' => 'Toggled', 'data' => $row]);
            else respond_error('Toggle failed', HTTP_INTERNAL_SERVER_ERROR);
        } catch (Throwable $e) {
            error_log('BannerController::toggleActive exception: ' . $e->getMessage());
            respond_error('DB error', HTTP_INTERNAL_SERVER_ERROR, null, ERROR_CODE_DATABASE);
        }
    }

    // POST translations (list translations for banner)
    public static function translations($input = [])
    {
        if (!self::checkPermission()) return respond_error('Unauthorized', HTTP_UNAUTHORIZED);
        if (!self::validateCsrf($input)) return respond_error('Invalid CSRF', HTTP_FORBIDDEN);

        $id = !empty($input['id']) ? (int)$input['id'] : 0;
        if (!$id) return respond_error('Invalid id', HTTP_BAD_REQUEST);

        try {
            $t = Banner::getTranslations($id);
            respond(['success' => true, 'data' => $t]);
        } catch (Throwable $e) {
            error_log('BannerController::translations exception: ' . $e->getMessage());
            respond_error('DB error', HTTP_INTERNAL_SERVER_ERROR, null, ERROR_CODE_DATABASE);
        }
    }
}