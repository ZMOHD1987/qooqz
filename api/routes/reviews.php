<?php
// htdocs/api/routes/reviews.php
// Routes for review endpoints (maps HTTP requests to ReviewController methods)
// Supports: listing, creating reviews (product/service), show by id, update, delete, approve/reject, stats

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/ReviewController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/reviews
$baseSegment = '/api/reviews';
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

// helpers
$isNumericFirst = isset($actionParts[0]) && is_numeric($actionParts[0]);
$id = $isNumericFirst ? (int)$actionParts[0] : null;
$second = $actionParts[1] ?? null;
$third = $actionParts[2] ?? null;

try {
    switch (true) {
        // /api/reviews  GET (list) or POST (create)
        case $first === '' && $method === 'GET':
            ReviewController::index();
            exit;

        case $first === '' && $method === 'POST':
            ReviewController::create();
            exit;

        // /api/reviews/stats  GET
        case $first === 'stats' && $method === 'GET':
            ReviewController::stats();
            exit;

        // /api/reviews/{id}  GET show
        case $isNumericFirst && $method === 'GET' && !$second:
            ReviewController::show($id);
            exit;

        // /api/reviews/{id}  PUT/POST update
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            ReviewController::update($id);
            exit;

        // /api/reviews/{id}  DELETE
        case $isNumericFirst && $method === 'DELETE' && !$second:
            ReviewController::delete($id);
            exit;

        // /api/reviews/{id}/approve  POST
        case $isNumericFirst && $second === 'approve' && $method === 'POST':
            ReviewController::approve($id);
            exit;

        // /api/reviews/{id}/reject  POST
        case $isNumericFirst && $second === 'reject' && $method === 'POST':
            ReviewController::reject($id);
            exit;

        // /api/reviews/product/{product_id}  GET list reviews for a product
        case $first === 'product' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'GET':
            ReviewController::forProduct((int)$actionParts[1]);
            exit;

        // /api/reviews/service/{service_id}  GET list reviews for a service
        case $first === 'service' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'GET':
            ReviewController::forService((int)$actionParts[1]);
            exit;

        // /api/reviews/product/{product_id} POST create review for a product
        case $first === 'product' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'POST':
            ReviewController::createForProduct((int)$actionParts[1]);
            exit;

        // /api/reviews/service/{service_id} POST create review for a service
        case $first === 'service' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'POST':
            ReviewController::createForService((int)$actionParts[1]);
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Reviews route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>