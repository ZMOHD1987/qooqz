<?php
// htdocs/api/routes/categories.php
// Routes for category endpoints (maps HTTP requests to CategoryController methods)

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/categories
$baseSegment = '/api/categories';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

$actionPath = '/';
if (strpos($path, $baseSegment) !== false) {
    $actionPath = substr($path, strpos($path, $baseSegment) + strlen($baseSegment));
}
$actionPath = trim($actionPath, '/');
$actionParts = $actionPath === '' ? [] : explode('/', $actionPath);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$first = $actionParts[0] ?? '';

// helper to detect numeric id
$isNumericFirst = isset($actionParts[0]) && is_numeric($actionParts[0]);
$id = $isNumericFirst ? (int)$actionParts[0] : null;
$second = $actionParts[1] ?? null;

try {
    switch (true) {
        // /api/categories  GET (list) or POST (create)
        case $first === '' && $method === 'GET':
            CategoryController::index();
            exit;

        case $first === '' && $method === 'POST':
            CategoryController::create();
            exit;

        // /api/categories/reorder  POST
        case $first === 'reorder' && $method === 'POST':
            CategoryController::reorder();
            exit;

        // /api/categories/stats  GET
        case $first === 'stats' && $method === 'GET':
            CategoryController::stats();
            exit;

        // /api/categories/{id_or_slug} GET show
        case $isNumericFirst && $method === 'GET' && !$second:
            CategoryController::show($id);
            exit;

        case !$isNumericFirst && $first && $method === 'GET' && !$second:
            // slug
            CategoryController::show($first);
            exit;

        // /api/categories/{id} PUT/POST update
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            CategoryController::update($id);
            exit;

        // /api/categories/{id} DELETE
        case $isNumericFirst && $method === 'DELETE' && !$second:
            CategoryController::delete($id);
            exit;

        // /api/categories/{id}/restore  POST
        case $isNumericFirst && $second === 'restore' && $method === 'POST':
            CategoryController::restore($id);
            exit;

        // /api/categories/{id}/products  GET
        case $isNumericFirst && $second === 'products' && $method === 'GET':
            CategoryController::products($id);
            exit;

        // /api/categories/{id}/attach-product  POST
        case $isNumericFirst && $second === 'attach-product' && $method === 'POST':
            CategoryController::attachProduct($id);
            exit;

        // /api/categories/{id}/detach-product  POST
        case $isNumericFirst && $second === 'detach-product' && $method === 'POST':
            CategoryController::detachProduct($id);
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Categories route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>