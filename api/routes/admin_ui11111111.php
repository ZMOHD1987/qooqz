<?php
declare(strict_types=1);

// ===============================
// api/routes/admin_ui.php
// ===============================

$baseDir = dirname(__DIR__);

require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/admin_ui/repositories/PdoAdminUiRepository.php';
require_once API_VERSION_PATH . '/models/admin_ui/validators/AdminUiValidator.php';
require_once API_VERSION_PATH . '/models/admin_ui/services/AdminUiService.php';
require_once API_VERSION_PATH . '/models/admin_ui/controllers/AdminUiController.php';

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// Tenant/User context
$tenantId = $_SESSION['tenant_id'] ?? 1;
$userId   = $_SESSION['user']['id'] ?? null;

// Init layers
$repo       = new PdoAdminUiRepository($pdo);
$validator  = new AdminUiValidator();
$service    = new AdminUiService($repo, $validator);
$controller = new AdminUiController($service);

// ===============================
// Helper: Read Request Data
// ===============================
function getRequestData(): array {
    $data = [];

    // 1. $_POST
    if (!empty($_POST)) {
        $data = $_POST;
        unset($data['_method']);
    }

    // 2. JSON body
    $json = file_get_contents('php://input');
    if ($json) {
        $jsonData = json_decode($json, true);
        if (is_array($jsonData)) {
            $data = array_merge($data, $jsonData);
        }
    }

    // 3. Uploaded files
    if (!empty($_FILES)) {
        $data['files'] = $_FILES;
    }

    // 4. Cast numeric fields
    $numericFields = ['id', 'theme_id', 'tenant_id', 'user_id', 'is_active', 'is_default', 'sort_order', 'border_width', 'border_radius'];
    foreach ($numericFields as $f) {
        if (isset($data[$f])) {
            $data[$f] = is_numeric($data[$f]) ? (int)$data[$f] : null;
        }
    }

    // 5. Trim strings
    $stringFields = ['name', 'slug', 'description', 'setting_key', 'setting_name', 'setting_value', 'category', 'color_value', 'font_family', 'font_size', 'font_weight', 'button_type', 'card_type'];
    foreach ($stringFields as $f) {
        if (isset($data[$f])) {
            $data[$f] = trim((string)$data[$f]);
            if ($data[$f] === '') {
                $data[$f] = null;
            }
        }
    }

    return $data;
}

// ===============================
// Routing
// ===============================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    
    // Handle _method parameter for RESTful API
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }
    
    // Remove /api prefix if exists
    $uri = preg_replace('#^/api#', '', $uri);
    $uri = '/' . trim($uri, '/');

    // 🔴 **GET /admin_ui/themes - Get all themes**
    if ($method === 'GET' && $uri === '/admin_ui/themes') {
        ResponseFormatter::success($controller->getThemes($tenantId));
    }
    
    // 🔴 **GET /admin_ui/themes/{id} - Get single theme**
    elseif ($method === 'GET' && preg_match('#^/admin_ui/themes/(\d+)$#', $uri, $m)) {
        $themeId = (int)$m[1];
        ResponseFormatter::success($controller->getTheme($tenantId, $themeId));
    }
    
    // 🔴 **GET /admin_ui/active_theme - Get active theme**
    elseif ($method === 'GET' && $uri === '/admin_ui/active_theme') {
        ResponseFormatter::success($controller->getActiveTheme($tenantId));
    }
    
    // 🔴 **GET /admin_ui/full_theme - Get full theme data with all settings**
    elseif ($method === 'GET' && $uri === '/admin_ui/full_theme') {
        ResponseFormatter::success($controller->getFullThemeData($tenantId));
    }
    
    // 🔴 **POST /admin_ui/themes - Create new theme**
    elseif ($method === 'POST' && $uri === '/admin_ui/themes') {
        $data = getRequestData();
        ResponseFormatter::success($controller->createTheme($tenantId, $data));
    }
    
    // 🔴 **PUT /admin_ui/themes/{id} - Update theme**
    elseif (($method === 'PUT' || ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT')) 
            && preg_match('#^/admin_ui/themes/(\d+)$#', $uri, $m)) {
        $themeId = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $themeId;
        ResponseFormatter::success($controller->updateTheme($tenantId, $data));
    }
    
    // 🔴 **DELETE /admin_ui/themes/{id} - Delete theme**
    elseif ($method === 'DELETE' && preg_match('#^/admin_ui/themes/(\d+)$#', $uri, $m)) {
        $themeId = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $themeId;
        ResponseFormatter::success($controller->deleteTheme($tenantId, $data));
    }
    
    // 🔴 **GET /admin_ui/design_settings - Get design settings**
    elseif ($method === 'GET' && $uri === '/admin_ui/design_settings') {
        ResponseFormatter::success($controller->getDesignSettings($tenantId));
    }
    
    // 🔴 **POST /admin_ui/design_settings - Save design settings**
    elseif ($method === 'POST' && $uri === '/admin_ui/design_settings') {
        $data = getRequestData();
        ResponseFormatter::success($controller->saveDesignSettings($tenantId, $data));
    }
    
    // 🔴 **GET /admin_ui/color_settings - Get color settings**
    elseif ($method === 'GET' && $uri === '/admin_ui/color_settings') {
        ResponseFormatter::success($controller->getColorSettings($tenantId));
    }
    
    // 🔴 **POST /admin_ui/color_settings - Save color settings**
    elseif ($method === 'POST' && $uri === '/admin_ui/color_settings') {
        $data = getRequestData();
        ResponseFormatter::success($controller->saveColorSettings($tenantId, $data));
    }
    
    // 🔴 **GET /admin_ui/font_settings - Get font settings**
    elseif ($method === 'GET' && $uri === '/admin_ui/font_settings') {
        ResponseFormatter::success($controller->getFontSettings($tenantId));
    }
    
    // 🔴 **POST /admin_ui/font_settings - Save font settings**
    elseif ($method === 'POST' && $uri === '/admin_ui/font_settings') {
        $data = getRequestData();
        ResponseFormatter::success($controller->saveFontSettings($tenantId, $data));
    }
    
    // 🔴 **GET /admin_ui/button_styles - Get button styles**
    elseif ($method === 'GET' && $uri === '/admin_ui/button_styles') {
        ResponseFormatter::success($controller->getButtonStyles($tenantId));
    }
    
    // 🔴 **POST /admin_ui/button_styles - Create button style**
    elseif ($method === 'POST' && $uri === '/admin_ui/button_styles') {
        $data = getRequestData();
        ResponseFormatter::success($controller->createButtonStyle($tenantId, $data));
    }
    
    // 🔴 **PUT /admin_ui/button_styles/{id} - Update button style**
    elseif (($method === 'PUT' || ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT')) 
            && preg_match('#^/admin_ui/button_styles/(\d+)$#', $uri, $m)) {
        $styleId = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $styleId;
        ResponseFormatter::success($controller->updateButtonStyle($tenantId, $data));
    }
    
    // 🔴 **DELETE /admin_ui/button_styles/{id} - Delete button style**
    elseif ($method === 'DELETE' && preg_match('#^/admin_ui/button_styles/(\d+)$#', $uri, $m)) {
        $styleId = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $styleId;
        ResponseFormatter::success($controller->deleteButtonStyle($tenantId, $data));
    }
    
    // 🔴 **GET /admin_ui/card_styles - Get card styles**
    elseif ($method === 'GET' && $uri === '/admin_ui/card_styles') {
        ResponseFormatter::success($controller->getCardStyles($tenantId));
    }
    
    // 🔴 **POST /admin_ui/card_styles - Create card style**
    elseif ($method === 'POST' && $uri === '/admin_ui/card_styles') {
        $data = getRequestData();
        ResponseFormatter::success($controller->createCardStyle($tenantId, $data));
    }
    
    // 🔴 **PUT /admin_ui/card_styles/{id} - Update card style**
    elseif (($method === 'PUT' || ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT')) 
            && preg_match('#^/admin_ui/card_styles/(\d+)$#', $uri, $m)) {
        $styleId = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $styleId;
        ResponseFormatter::success($controller->updateCardStyle($tenantId, $data));
    }
    
    // 🔴 **DELETE /admin_ui/card_styles/{id} - Delete card style**
    elseif ($method === 'DELETE' && preg_match('#^/admin_ui/card_styles/(\d+)$#', $uri, $m)) {
        $styleId = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $styleId;
        ResponseFormatter::success($controller->deleteCardStyle($tenantId, $data));
    }
    
    // 🔴 **GET /admin_ui/system_settings - Get all system settings**
    elseif ($method === 'GET' && $uri === '/admin_ui/system_settings') {
        ResponseFormatter::success($controller->getSystemSettings($tenantId));
    }
    
    // 🔴 **GET /admin_ui/system_settings/{key} - Get single system setting**
    elseif ($method === 'GET' && preg_match('#^/admin_ui/system_settings/([a-zA-Z0-9_\-]+)$#', $uri, $m)) {
        $key = $m[1];
        ResponseFormatter::success($controller->getSystemSetting($tenantId, $key));
    }
    
    // 🔴 **POST /admin_ui/system_settings - Save system setting**
    elseif ($method === 'POST' && $uri === '/admin_ui/system_settings') {
        $data = getRequestData();
        ResponseFormatter::success($controller->saveSystemSetting($tenantId, $data));
    }
    
    // 🔴 **GET /admin_ui/tenant - Get tenant info**
    elseif ($method === 'GET' && $uri === '/admin_ui/tenant') {
        ResponseFormatter::success($controller->getTenant($tenantId));
    }
    
    // 🔴 **GET /admin_ui/tenant_users - Get tenant users**
    elseif ($method === 'GET' && $uri === '/admin_ui/tenant_users') {
        ResponseFormatter::success($controller->getTenantUsers($tenantId));
    }
    
    // 🔴 **GET /admin_ui/css - Generate CSS**
    elseif ($method === 'GET' && $uri === '/admin_ui/css') {
        ResponseFormatter::success($controller->generateCss($tenantId));
    }
    
    // 🔴 **POST /admin_ui/upload_image - Upload theme image**
    elseif ($method === 'POST' && $uri === '/admin_ui/upload_image') {
        $data = getRequestData();
        $files = $data['files'] ?? [];
        ResponseFormatter::success($controller->uploadThemeImage($tenantId, $files));
    }
    
    else {
        ResponseFormatter::error('Method not allowed or route not found: ' . $uri, 405);
    }
    
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('error', 'Admin UI route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}