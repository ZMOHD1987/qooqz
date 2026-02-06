<?php
// htdocs/api/routes/coupons.php
// Routes for coupon endpoints (maps HTTP requests to CouponController methods)
// Supports: listing, creating, show, update, delete, apply/redeem, validate, user coupons, stats

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/CouponController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type (تعديل حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/coupons
$baseSegment = '/api/coupons';
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

try {
    switch (true) {
        // /api/coupons  GET (list) or POST (create)
        case $first === '' && $method === 'GET':
            CouponController::index();
            exit;

        case $first === '' && $method === 'POST':
            CouponController::create();
            exit;

        // /api/coupons/stats  GET
        case $first === 'stats' && $method === 'GET':
            CouponController::stats();
            exit;

        // /api/coupons/{id}  GET show
        case $isNumericFirst && $method === 'GET' && !$second:
            CouponController::show($id);
            exit;

        // /api/coupons/{id}  PUT/POST update
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            CouponController::update($id);
            exit;

        // /api/coupons/{id}  DELETE
        case $isNumericFirst && $method === 'DELETE' && !$second:
            CouponController::delete($id);
            exit;

        // /api/coupons/{id}/activate  POST
        case $isNumericFirst && $second === 'activate' && $method === 'POST':
            CouponController::activate($id);
            exit;

        // /api/coupons/{id}/deactivate  POST
        case $isNumericFirst && $second === 'deactivate' && $method === 'POST':
            CouponController::deactivate($id);
            exit;

        // /api/coupons/validate  POST - validate a code without applying
        case $first === 'validate' && $method === 'POST':
            CouponController::validateCode();
            exit;

        // /api/coupons/apply  POST - apply/redeem coupon for cart/order
        case $first === 'apply' && $method === 'POST':
            CouponController::apply();
            exit;

        // /api/coupons/user/{user_id}  GET - list coupons for a user (or current user if omitted)
        case $first === 'user' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'GET':
            CouponController::forUser((int)$actionParts[1]);
            exit;

        case $first === 'user' && $method === 'GET' && !isset($actionParts[1]):
            // current authenticated user's coupons
            CouponController::forUser();
            exit;

        // /api/coupons/bulk-import  POST - optional bulk import endpoint
        case $first === 'bulk-import' && $method === 'POST':
            CouponController::bulkImport();
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Coupons route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>