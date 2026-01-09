<?php
// api/routes/products.php
// Routes for product endpoints (maps HTTP requests to ProductController methods)
// Uses bootstrap for consistent DB, auth, and config loading

// Load bootstrap for DB connection, session, auth, and config
require_once __DIR__ . '/../bootstrap.php';

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/ProductController.php';

// Response helper should already be loaded by bootstrap, but ensure it's available
if (!function_exists('json_response')) {
    require_once __DIR__ . '/../helpers/response.php';
}

// CORS & Content-Type (already handled by bootstrap, but ensure headers)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/products
$baseSegment = '/api/products';
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
        // /api/products  GET (list)
        case $first === '' && $method === 'GET':
            ProductController::index();
            exit;

        // /api/products  POST (create)
        case $first === '' && $method === 'POST':
            ProductController::create();
            exit;

        // /api/products/featured  GET
        case $first === 'featured' && $method === 'GET':
            ProductController::featured();
            exit;

        // /api/products/{id_or_slug}  GET show
        case $isNumericFirst && $method === 'GET' && !$second:
            ProductController::show($id);
            exit;

        case !$isNumericFirst && $first && $method === 'GET' && !$second:
            // treat as slug
            ProductController::show($first);
            exit;

        // /api/products/{id} PUT/POST update
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            ProductController::update($id);
            exit;

        // /api/products/{id} DELETE
        case $isNumericFirst && $method === 'DELETE' && !$second:
            ProductController::delete($id);
            exit;

        // /api/products/{id}/stock  POST
        case $isNumericFirst && $second === 'stock' && $method === 'POST':
            ProductController::updateStock($id);
            exit;

        // /api/products/{id}/images  POST
        case $isNumericFirst && $second === 'images' && $method === 'POST':
            ProductController::uploadImage($id);
            exit;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
            exit;
    }

} catch (Throwable $e) {
    error_log("Products route error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
    exit;
}

?>