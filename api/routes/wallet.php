<?php
// htdocs/api/routes/wallet.php
// Routes for wallet endpoints (maps HTTP requests to WalletController methods)
// Supports: wallet CRUD, balance, transactions, top-up, withdraw, transfer, webhooks, stats

// ===========================================
// تحميل Controller & Helpers
// ===========================================
require_once __DIR__ . '/../controllers/WalletController.php';
require_once __DIR__ . '/../helpers/response.php';

// CORS & Content-Type (تعديل حسب الحاجة)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Session-Id');
    header('HTTP/1.1 204 No Content');
    exit;
}
header('Content-Type: application/json');

// استخراج المسار بالنسبة لـ /api/wallet
$baseSegment = '/api/wallet';
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
        // /api/wallet  GET -> current user's wallet or list (admin)
        case $first === '' && $method === 'GET':
            WalletController::index();
            exit;

        // /api/wallet  POST -> create wallet (admin or user onboarding)
        case $first === '' && $method === 'POST':
            WalletController::create();
            exit;

        // /api/wallet/balance  GET -> current user's balance (shortcut)
        case $first === 'balance' && $method === 'GET':
            WalletController::balance();
            exit;

        // /api/wallet/{id}  GET -> show wallet by id (admin) or user's wallet
        case $isNumericFirst && $method === 'GET' && !$second:
            WalletController::show($id);
            exit;

        // /api/wallet/{id}  PUT/POST -> update wallet metadata
        case $isNumericFirst && ($method === 'PUT' || $method === 'POST') && !$second:
            WalletController::update($id);
            exit;

        // /api/wallet/{id}  DELETE -> deactivate/delete wallet
        case $isNumericFirst && $method === 'DELETE' && !$second:
            WalletController::delete($id);
            exit;

        // /api/wallet/{id}/transactions  GET -> list transactions for wallet
        case $isNumericFirst && $second === 'transactions' && $method === 'GET':
            WalletController::transactions($id);
            exit;

        // /api/wallet/transactions  GET -> list transactions (admin) or current user's
        case $first === 'transactions' && $method === 'GET':
            WalletController::transactions();
            exit;

        // /api/wallet/topup  POST -> initiate top-up (create topup record / redirect to gateway)
        case $first === 'topup' && $method === 'POST':
            WalletController::topup();
            exit;

        // /api/wallet/withdraw  POST -> request withdrawal
        case $first === 'withdraw' && $method === 'POST':
            WalletController::withdraw();
            exit;

        // /api/wallet/transfer  POST -> transfer between wallets/users
        case $first === 'transfer' && $method === 'POST':
            WalletController::transfer();
            exit;

        // /api/wallet/{id}/refund  POST -> refund wallet transaction (admin)
        case $isNumericFirst && $second === 'refund' && $method === 'POST':
            WalletController::refund($id);
            exit;

        // /api/wallet/webhook/{gateway}  POST -> payment gateway webhook for topup/withdraw
        case $first === 'webhook' && isset($actionParts[1]) && $method === 'POST':
            $gateway = $actionParts[1];
            WalletController::handleWebhook($gateway);
            exit;

        // /api/wallet/webhook  POST -> generic webhook (gateway in payload)
        case $first === 'webhook' && $method === 'POST':
            WalletController::handleWebhook(null);
            exit;

        // /api/wallet/{id}/balance  GET -> get wallet balance by id (admin)
        case $isNumericFirst && $second === 'balance' && $method === 'GET':
            WalletController::balance($id);
            exit;

        // /api/wallet/stats  GET -> wallet stats (admin)
        case $first === 'stats' && $method === 'GET':
            WalletController::stats();
            exit;

        default:
            Response::error('Endpoint not found', 404);
            break;
    }

} catch (Throwable $e) {
    error_log("Wallet route error: " . $e->getMessage());
    Response::error('Server error', 500);
}

?>