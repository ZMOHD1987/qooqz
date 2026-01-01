<?php
// htdocs/api/routes/payments.php
// Routes for payment endpoints (maps HTTP requests to PaymentController methods)
// Supports: listing, creating payment records, show by id, find by order, refunds, webhooks, stats

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/PaymentController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/payments
$baseSegment = '/api/payments';
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
        // /api/payments  GET (list)
        case $first === '' && $method === 'GET':
            PaymentController::index();
            exit;

        // /api/payments  POST (create payment record)
        case $first === '' && $method === 'POST':
            PaymentController::create();
            exit;

        // /api/payments/stats  GET
        case $first === 'stats' && $method === 'GET':
            PaymentController::stats();
            exit;

        // /api/payments/{id}  GET show
        case $isNumericFirst && $method === 'GET' && !$second:
            PaymentController::show($id);
            exit;

        // /api/payments/{id}  DELETE (optional)
        case $isNumericFirst && $method === 'DELETE' && !$second:
            PaymentController::delete($id);
            exit;

        // /api/payments/order/{order_id}  GET - payments for an order
        case $first === 'order' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'GET':
            PaymentController::findByOrder((int)$actionParts[1]);
            exit;

        // /api/payments/{id}/refund  POST - create refund for payment id
        case $isNumericFirst && $second === 'refund' && $method === 'POST':
            PaymentController::createRefund($id);
            exit;

        // /api/payments/refunds/{id}  GET - get refund by id
        case $first === 'refunds' && isset($actionParts[1]) && is_numeric($actionParts[1]) && $method === 'GET':
            PaymentController::getRefund((int)$actionParts[1]);
            exit;

        // /api/payments/webhook/{gateway}  POST - gateway webhook handler
        case $first === 'webhook' && isset($actionParts[1]) && $method === 'POST':
            // gateway name in second segment, e.g., /api/payments/webhook/stripe
            $gateway = $actionParts[1];
            PaymentController::handleWebhook($gateway);
            exit;

        // /api/payments/webhook  POST - generic webhook (gateway in payload)
        case $first === 'webhook' && $method === 'POST':
            PaymentController::handleWebhook(null);
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Payments route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>