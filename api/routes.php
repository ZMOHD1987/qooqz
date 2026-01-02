<?php
// api/routes.php
// Robust routes dispatcher with error logging to api/error_log.txt
// Replace your existing routes.php with this file.

declare(strict_types=1);

$API_ERROR_LOG = __DIR__ . '/error_log.txt';
if (!file_exists($API_ERROR_LOG)) @touch($API_ERROR_LOG);
@chmod($API_ERROR_LOG, 0664);

// Safe logger helpers (fall back to PHP error_log if needed)
if (!function_exists('log_error')) {
    function log_error(string $msg): void {
        global $API_ERROR_LOG;
        $line = "[" . date('c') . "] ROUTES: $msg" . PHP_EOL;
        @file_put_contents($API_ERROR_LOG, $line, FILE_APPEND | LOCK_EX);
        @error_log($line);
    }
}
if (!function_exists('log_exception')) {
    function log_exception(Throwable $e): void {
        log_error("EXCEPTION: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\nTrace: " . $e->getTraceAsString());
    }
}

// Try to include helpers (no fatal if missing: log and continue)
$maybeHelpers = [
    __DIR__ . '/helpers/response.php',
    __DIR__ . '/helpers/utils.php',
];
foreach ($maybeHelpers as $hf) {
    if (is_readable($hf)) {
        try { require_once $hf; } catch (Throwable $e) {
            log_error("include failed: {$hf} -> " . $e->getMessage());
        }
    } else {
        log_error("helper missing: {$hf}");
    }
}

// Fallback JSON error responder if json_error/json_response not available
if (!function_exists('json_error')) {
    function json_error(string $msg = 'Server error', int $code = 500): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('json_response')) {
    function json_response($data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Define routes: [METHOD, REGEX_PATH, CONTROLLER_FILE (absolute), HANDLER_FUNCTION]
$routes = [
    // Health / meta
    ['GET',  '#^/api/health$#', null, 'api_health'],

    // Themes
    ['GET',  '#^/api/themes$#', __DIR__ . '/controllers/ThemeController.php', 'ThemeController_index'],
    ['POST', '#^/api/themes$#', __DIR__ . '/controllers/ThemeController.php', 'ThemeController_store'],
    ['GET',  '#^/api/themes/(\d+)$#', __DIR__ . '/controllers/ThemeController.php', 'ThemeController_show'],
    ['PUT',  '#^/api/themes/(\d+)$#', __DIR__ . '/controllers/ThemeController.php', 'ThemeController_update'],
    ['DELETE','#^/api/themes/(\d+)$#', __DIR__ . '/controllers/ThemeController.php', 'ThemeController_delete'],
    ['POST', '#^/api/themes/(\d+)/activate$#', __DIR__ . '/controllers/ThemeController.php', 'ThemeController_activate'],
    ['POST', '#^/api/themes/(\d+)/duplicate$#', __DIR__ . '/controllers/ThemeController.php', 'ThemeController_duplicate'],

    // Color settings
    ['GET',  '#^/api/themes/(\d+)/colors$#', __DIR__ . '/controllers/ColorSettingsController.php', 'ColorSettings_index'],
    ['POST', '#^/api/themes/(\d+)/colors$#', __DIR__ . '/controllers/ColorSettingsController.php', 'ColorSettings_store'],
    ['PUT',  '#^/api/colors/(\d+)$#', __DIR__ . '/controllers/ColorSettingsController.php', 'ColorSettings_update'],
    ['DELETE','#^/api/colors/(\d+)$#', __DIR__ . '/controllers/ColorSettingsController.php', 'ColorSettings_delete'],
    ['POST', '#^/api/themes/(\d+)/colors/bulk$#', __DIR__ . '/controllers/ColorSettingsController.php', 'ColorSettings_bulk'],
    ['GET',  '#^/api/themes/(\d+)/colors/export$#', __DIR__ . '/controllers/ColorSettingsController.php', 'ColorSettings_export'],
    ['POST', '#^/api/themes/(\d+)/colors/import$#', __DIR__ . '/controllers/ColorSettingsController.php', 'ColorSettings_import'],

    // Button styles
    ['GET',  '#^/api/themes/(\d+)/buttons$#', __DIR__ . '/controllers/ButtonStylesController.php', 'ButtonStyles_index'],
    ['POST', '#^/api/themes/(\d+)/buttons$#', __DIR__ . '/controllers/ButtonStylesController.php', 'ButtonStyles_store'],
    ['PUT',  '#^/api/buttons/(\d+)$#', __DIR__ . '/controllers/ButtonStylesController.php', 'ButtonStyles_update'],
    ['DELETE','#^/api/buttons/(\d+)$#', __DIR__ . '/controllers/ButtonStylesController.php', 'ButtonStyles_delete'],
    ['POST', '#^/api/themes/(\d+)/buttons/bulk$#', __DIR__ . '/controllers/ButtonStylesController.php', 'ButtonStyles_bulk'],
    ['GET',  '#^/api/themes/(\d+)/buttons/export$#', __DIR__ . '/controllers/ButtonStylesController.php', 'ButtonStyles_export'],
    ['POST', '#^/api/themes/(\d+)/buttons/import$#', __DIR__ . '/controllers/ButtonStylesController.php', 'ButtonStyles_import'],

    // Card styles
    ['GET',  '#^/api/themes/(\d+)/cards$#', __DIR__ . '/controllers/CardStylesController.php', 'CardStyles_index'],
    ['POST', '#^/api/themes/(\d+)/cards$#', __DIR__ . '/controllers/CardStylesController.php', 'CardStyles_store'],
    ['PUT',  '#^/api/cards/(\d+)$#', __DIR__ . '/controllers/CardStylesController.php', 'CardStyles_update'],
    ['DELETE','#^/api/cards/(\d+)$#', __DIR__ . '/controllers/CardStylesController.php', 'CardStyles_delete'],
    ['POST', '#^/api/themes/(\d+)/cards/bulk$#', __DIR__ . '/controllers/CardStylesController.php', 'CardStyles_bulk'],
    ['GET',  '#^/api/themes/(\d+)/cards/export$#', __DIR__ . '/controllers/CardStylesController.php', 'CardStyles_export'],
    ['POST', '#^/api/themes/(\d+)/cards/import$#', __DIR__ . '/controllers/CardStylesController.php', 'CardStyles_import'],

    // Font settings
    ['GET',  '#^/api/themes/(\d+)/fonts$#', __DIR__ . '/controllers/FontSettingsController.php', 'FontSettings_index'],
    ['POST', '#^/api/themes/(\d+)/fonts$#', __DIR__ . '/controllers/FontSettingsController.php', 'FontSettings_store'],
    ['PUT',  '#^/api/fonts/(\d+)$#', __DIR__ . '/controllers/FontSettingsController.php', 'FontSettings_update'],
    ['DELETE','#^/api/fonts/(\d+)$#', __DIR__ . '/controllers/FontSettingsController.php', 'FontSettings_delete'],
    ['POST', '#^/api/themes/(\d+)/fonts/bulk$#', __DIR__ . '/controllers/FontSettingsController.php', 'FontSettings_bulk'],
    ['GET',  '#^/api/themes/(\d+)/fonts/export$#', __DIR__ . '/controllers/FontSettingsController.php', 'FontSettings_export'],
    ['POST', '#^/api/themes/(\d+)/fonts/import$#', __DIR__ . '/controllers/FontSettingsController.php', 'FontSettings_import'],

    // Design settings
    ['GET',  '#^/api/themes/(\d+)/designs$#', __DIR__ . '/controllers/DesignSettingsController.php', 'DesignSettings_index'],
    ['POST', '#^/api/themes/(\d+)/designs$#', __DIR__ . '/controllers/DesignSettingsController.php', 'DesignSettings_store'],
    ['PUT',  '#^/api/designs/(\d+)$#', __DIR__ . '/controllers/DesignSettingsController.php', 'DesignSettings_update'],
    ['DELETE','#^/api/designs/(\d+)$#', __DIR__ . '/controllers/DesignSettingsController.php', 'DesignSettings_delete'],
    ['POST', '#^/api/themes/(\d+)/designs/bulk$#', __DIR__ . '/controllers/DesignSettingsController.php', 'DesignSettings_bulk'],
    ['GET',  '#^/api/themes/(\d+)/designs/export$#', __DIR__ . '/controllers/DesignSettingsController.php', 'DesignSettings_export'],
    ['POST', '#^/api/themes/(\d+)/designs/import$#', __DIR__ . '/controllers/DesignSettingsController.php', 'DesignSettings_import'],

    // Banners
    ['GET',  '#^/api/banners$#', __DIR__ . '/controllers/BannerController.php', 'Banner_index'],
    ['POST', '#^/api/banners$#', __DIR__ . '/controllers/BannerController.php', 'Banner_store'],
    ['GET',  '#^/api/banners/(\d+)$#', __DIR__ . '/controllers/BannerController.php', 'Banner_show'],
    ['PUT',  '#^/api/banners/(\d+)$#', __DIR__ . '/controllers/BannerController.php', 'Banner_update'],
    ['DELETE','#^/api/banners/(\d+)$#', __DIR__ . '/controllers/BannerController.php', 'Banner_delete'],
    // Banner translations
    ['GET',  '#^/api/banners/(\d+)/translations$#', __DIR__ . '/controllers/BannerController.php', 'Banner_translations'],
    ['POST', '#^/api/banners/(\d+)/translations$#', __DIR__ . '/controllers/BannerController.php', 'Banner_add_translation'],
    ['PUT',  '#^/api/banner_translations/(\d+)$#', __DIR__ . '/controllers/BannerController.php', 'Banner_update_translation'],
    ['DELETE','#^/api/banner_translations/(\d+)$#', __DIR__ . '/controllers/BannerController.php', 'Banner_delete_translation'],

    // System settings
    ['GET',  '#^/api/system-settings$#', __DIR__ . '/controllers/SystemSettingsController.php', 'System_index'],
    ['PUT',  '#^/api/system-settings/([^/]+)$#', __DIR__ . '/controllers/SystemSettingsController.php', 'System_update'],

    // Independent Drivers - delegate to standalone route handler
    ['GET',  '#^/api/routes/independent_drivers\.php#', null, 'independent_drivers_handler'],
    ['POST', '#^/api/routes/independent_drivers\.php#', null, 'independent_drivers_handler'],

    // Test / proxy to external fragment (for your testing)
    ['GET',  '#^/api/test/external$#', null, 'proxy_external_test'],
];

// Helper to get the request path
$path = function_exists('get_request_path') ? get_request_path() : rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// dispatch wrapped in try/catch to log unexpected errors
try {
    $matched = false;
    foreach ($routes as $r) {
        [$m, $pattern, $file, $handler] = $r;
        if (strtoupper($m) !== strtoupper($method)) continue;
        if (preg_match($pattern, $path, $matches)) {
            array_shift($matches);
            // include controller file when provided
            if ($file !== null) {
                if (!is_readable($file)) {
                    log_error("Controller file not found: $file (route pattern: $pattern, path: $path)");
                    json_error("Controller file not found: " . basename($file), 500);
                }
                try {
                    require_once $file;
                } catch (Throwable $e) {
                    log_exception($e);
                    json_error('Failed to include controller: ' . basename($file), 500);
                }
            }
            // make container available to handlers
            global $container;
            if (!is_callable($handler)) {
                if (!function_exists($handler)) {
                    log_error("Handler not found: $handler (after including $file)");
                    json_error("Handler not found: $handler", 500);
                }
            }
            // call the handler with container as first argument
            try {
                call_user_func_array($handler, array_merge([$container], $matches));
            } catch (Throwable $e) {
                log_exception($e);
                json_error('Server error', 500);
            }
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        json_error('Not Found', 404);
    }
} catch (Throwable $e) {
    log_exception($e);
    json_error('Server error', 500);
}


// -------------------------------
// Built-in handlers
// -------------------------------

/**
 * Simple health-check handler
 */
function api_health($container): void {
    $dbOk = false;
    try {
        if (!empty($container['db']) && $container['db'] instanceof mysqli) {
            $db = $container['db'];
            $res = $db->query('SELECT 1');
            $dbOk = $res !== false;
        }
    } catch (Throwable $e) {
        $dbOk = false;
    }
    json_response(['status' => 'ok', 'db' => $dbOk, 'time' => date('c')]);
}

/**
 * Proxy / Test handler that fetches the external fragment you specified.
 * Use this endpoint to test remote fragment consumption:
 *   GET /api/test/external
 *
 * NOTE: This is meant for simple testing only. Do not enable open proxy in production.
 */
function proxy_external_test($container): void {
    $url = 'https://mzmz.rf.gd/admin/fragments/DeliveryCompany.php';

    // simple cURL GET
    if (!function_exists('curl_init')) {
        json_error('cURL extension required for proxy', 500);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // set a reasonable timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // follow location
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // user-agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'DesignAPI/1.0 (+https://your-host/)');

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html';
    curl_close($ch);

    if ($resp === false || $err) {
        json_error('Failed to fetch external resource: ' . $err, 502);
    }

    // Return raw response from external fragment
    header('Content-Type: ' . $contentType);
    http_response_code($httpCode >= 200 ? $httpCode : 200);
    echo $resp;
    exit;
}

/**
 * Independent Drivers handler - delegates to standalone route file
 */
function independent_drivers_handler($container): void {
    $routeFile = __DIR__ . '/routes/independent_drivers.php';
    if (!is_readable($routeFile)) {
        log_error('Independent drivers route file not found: ' . $routeFile);
        json_error('Route handler not found', 500);
    }
    
    try {
        // Make globals available to the route file
        global $db, $conn;
        require $routeFile;
    } catch (Throwable $e) {
        log_exception($e);
        json_error('Server error in independent drivers route', 500);
    }
}