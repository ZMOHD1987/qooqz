<?php
// htdocs/api/routes/cart.php
// Routes for cart endpoints (maps HTTP requests to CartController methods)

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type (تعديل حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/cart
$baseSegment = '/api/cart';
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

// helper to detect numeric id (for item id routes)
$isNumericFirst = isset($actionParts[0]) && is_numeric($actionParts[0]);
$id = $isNumericFirst ? (int)$actionParts[0] : null;
$second = $actionParts[1] ?? null;

try {
    switch (true) {
        // GET /api/cart  -> get or create cart (client may use GET)
        case $first === '' && $method === 'GET':
            CartController::getCart();
            exit;

        // POST /api/cart or POST /api/cart/get  -> get/create cart (allow POST for clients)
        case ($first === '' && $method === 'POST') || ($first === 'get' && $method === 'POST'):
            CartController::getCart();
            exit;

        // POST /api/cart/items  -> add item
        case $first === 'items' && $method === 'POST' && !isset($actionParts[1]):
            CartController::addItem();
            exit;

        // PUT/POST /api/cart/items/{item_id}  -> update item quantity
        case $first === 'items' && $isNumericFirst && ($method === 'PUT' || $method === 'POST'):
            CartController::updateItem($id);
            exit;

        // DELETE /api/cart/items/{item_id} -> remove item
        case $first === 'items' && $isNumericFirst && $method === 'DELETE':
            CartController::removeItem($id);
            exit;

        // POST /api/cart/clear -> clear cart
        case $first === 'clear' && $method === 'POST':
            CartController::clear();
            exit;

        // POST /api/cart/apply-coupon -> apply coupon
        case $first === 'apply-coupon' && $method === 'POST':
            CartController::applyCoupon();
            exit;

        // POST /api/cart/remove-coupon -> remove coupon
        case $first === 'remove-coupon' && $method === 'POST':
            CartController::removeCoupon();
            exit;

        // POST /api/cart/merge -> merge guest cart into user cart
        case $first === 'merge' && $method === 'POST':
            CartController::merge();
            exit;

        // GET /api/cart/count -> count items
        case $first === 'count' && $method === 'GET':
            CartController::count();
            exit;

        // POST /api/cart/checkout-info -> checkout totals and payment methods
        case $first === 'checkout-info' && $method === 'POST':
            CartController::checkoutInfo();
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Cart route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>