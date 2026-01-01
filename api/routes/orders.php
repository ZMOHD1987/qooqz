<?php
// htdocs/api/routes/orders.php
// Routes for order endpoints (maps HTTP requests to OrderController methods)

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/orders
$baseSegment = '/api/orders';
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
        // /api/orders  GET (list) or POST (create)
        case $first === '' && $method === 'GET':
            OrderController::index();
            exit;

        case $first === '' && $method === 'POST':
            OrderController::create();
            exit;

        // /api/orders/{id_or_number}  GET show
        case $isNumericFirst && $method === 'GET' && !$second:
            OrderController::show($id);
            exit;

        case !$isNumericFirst && $first && $method === 'GET' && !$second:
            // treat as order_number or slug-like identifier
            OrderController::show($first);
            exit;

        // /api/orders/{id} POST/PUT update status or other actions by subroutes
        case $isNumericFirst && $second === 'status' && $method === 'POST':
            OrderController::updateStatus($id);
            exit;

        case $isNumericFirst && $second === 'payment-status' && $method === 'POST':
            OrderController::updatePaymentStatus($id);
            exit;

        case $isNumericFirst && $second === 'cancel' && $method === 'POST':
            OrderController::cancel($id);
            exit;

        case $isNumericFirst && $second === 'invoice' && $method === 'GET':
            OrderController::invoice($id);
            exit;

        // /api/orders/{id}/payment-status could also be called via webhook with POST and no auth
        case $isNumericFirst && $second === 'payment-status' && $method === 'POST':
            OrderController::updatePaymentStatus($id);
            exit;

        // /api/orders/stats GET (admin)
        case $first === 'stats' && $method === 'GET':
            OrderController::stats();
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Orders route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>