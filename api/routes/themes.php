<?php
// htdocs/api/routes/themes.php
// Routes for theme management and design settings (admin and public read endpoints)

require_once __DIR__ . '/../controllers/ThemeController.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(204);
    exit;
}
header('Content-Type: application/json');

$base = '/api/themes';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = substr($uri, strpos($uri, $base) + strlen($base));
$path = trim($path, '/');
$parts = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET /api/themes
    if (empty($parts) && $method === 'GET') {
        ThemeController::index();
        exit;
    }

    // GET /api/themes/{id}
    if (isset($parts[0]) && is_numeric($parts[0]) && $method === 'GET' && !isset($parts[1])) {
        ThemeController::show((int)$parts[0]);
        exit;
    }

    // GET /api/themes/{id}/design-settings
    if (isset($parts[0]) && is_numeric($parts[0]) && isset($parts[1]) && $parts[1] === 'design-settings' && $method === 'GET') {
        ThemeController::getDesignSettings((int)$parts[0]);
        exit;
    }

    // POST /api/themes/{id}/design-settings    (create/update settings)
    if (isset($parts[0]) && is_numeric($parts[0]) && isset($parts[1]) && $parts[1] === 'design-settings' && ($method === 'POST' || $method === 'PUT')) {
        ThemeController::saveDesignSettings((int)$parts[0]);
        exit;
    }

    // POST /api/themes/{id}/activate
    if (isset($parts[0]) && is_numeric($parts[0]) && isset($parts[1]) && $parts[1] === 'activate' && $method === 'POST') {
        ThemeController::activate((int)$parts[0]);
        exit;
    }

    // POST /api/themes/{id}/banners (upload/create)
    if (isset($parts[0]) && is_numeric($parts[0]) && isset($parts[1]) && $parts[1] === 'banners' && $method === 'POST') {
        ThemeController::createBanner((int)$parts[0]);
        exit;
    }

    // DELETE /api/themes/{id}/banners/{banner_id}
    if (isset($parts[0]) && is_numeric($parts[0]) && isset($parts[1]) && $parts[1] === 'banners' && isset($parts[2]) && is_numeric($parts[2]) && $method === 'DELETE') {
        ThemeController::deleteBanner((int)$parts[0], (int)$parts[2]);
        exit;
    }

    // Fallback
    Response::error('Endpoint not found', 404);
} catch (Throwable $e) {
    error_log("Themes route error: " . $e->getMessage());
    Response::error('Server error', 500);
}
?>