<?php
// htdocs/api/routes/services.php
// Routes for service endpoints (maps HTTP requests to ServiceController methods)

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/ServiceController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type (تعديل حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/services
$baseSegment = '/api/services';
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
        // /api/services  GET (list) or POST (create)
        case $first === '' && $method === 'GET':
            ServiceController::index();
            exit;

        case $first === '' && $method === 'POST':
            ServiceController::create();
            exit;

        // /api/services/{id_or_slug}  GET show
        case $isNumericFirst && $method === 'GET' && !$second:
            ServiceController::show($id);
            exit;

        case !$isNumericFirst && $first && $method === 'GET' && !$second:
            // treat as slug
            ServiceController::show($first);
            exit;

        // /api/services/{id} PUT/POST update
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            ServiceController::update($id);
            exit;

        // /api/services/{id} DELETE
        case $isNumericFirst && $method === 'DELETE' && !$second:
            ServiceController::delete($id);
            exit;

        // /api/services/{id}/availability  GET
        case $isNumericFirst && $second === 'availability' && $method === 'GET':
            ServiceController::availability($id);
            exit;

        // /api/services/book  POST (booking endpoint)
        case $first === 'book' && $method === 'POST':
            ServiceController::book();
            exit;

        // /api/services/{id}/bookings  GET (provider/admin)
        case $isNumericFirst && $second === 'bookings' && $method === 'GET':
            ServiceController::bookings($id);
            exit;

        // /api/services/bookings/{booking_id}/cancel  POST
        case $first === 'bookings' && isset($actionParts[1]) && is_numeric($actionParts[1]) && isset($actionParts[2]) && $actionParts[2] === 'cancel' && $method === 'POST':
            ServiceController::cancelBooking((int)$actionParts[1]);
            exit;

        // /api/services/{id}/reviews  GET
        case $isNumericFirst && $second === 'reviews' && $method === 'GET':
            ServiceController::reviews($id);
            exit;

        // /api/services/{id}/reviews  POST (add review)
        case $isNumericFirst && $second === 'reviews' && $method === 'POST':
            ServiceController::addReview($id);
            exit;

        // /api/services/{id}/pricing  POST (save pricing)
        case $isNumericFirst && $second === 'pricing' && $method === 'POST':
            ServiceController::savePricing($id);
            exit;

        // /api/services/{id}/stats  GET
        case $isNumericFirst && $second === 'stats' && $method === 'GET':
            ServiceController::stats($id);
            exit;

        // /api/services/stats  GET (global)
        case $first === 'stats' && $method === 'GET':
            ServiceController::stats();
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Services route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>