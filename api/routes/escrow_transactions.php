<?php
declare(strict_types=1);

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

date_default_timezone_set('Asia/Riyadh');

// Load dependencies
$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// Load model classes
$modelsPath = API_VERSION_PATH . '/models/subscriptions';
require_once $modelsPath . '/repositories/PdoEscrowTransactionsRepository.php';
require_once $modelsPath . '/validators/EscrowTransactionsValidator.php';
require_once $modelsPath . '/services/EscrowTransactionsService.php';
require_once $modelsPath . '/controllers/EscrowTransactionsController.php';

// CORS headers
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

// Session
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Database connection
if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

try {
    $pdo        = $GLOBALS['ADMIN_DB'];
    $repo       = new PdoEscrowTransactionsRepository($pdo);
    $service    = new EscrowTransactionsService($repo);
    $controller = new EscrowTransactionsController($service);
    $method     = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                $stats = $controller->stats();
                ResponseFormatter::success($stats);
                break;
            }
            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $item = $controller->find((int)$_GET['id']);
                if (!$item) { ResponseFormatter::error('Escrow transaction not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (isset($_GET['tenant_id']))  $filters['tenant_id'] = $_GET['tenant_id'];
                if (isset($_GET['order_id']))   $filters['order_id'] = $_GET['order_id'];
                if (isset($_GET['buyer_id']))   $filters['buyer_id'] = $_GET['buyer_id'];
                if (isset($_GET['seller_id']))  $filters['seller_id'] = $_GET['seller_id'];
                if (isset($_GET['status']))     $filters['status'] = $_GET['status'];
                if (isset($_GET['search']))     $filters['search'] = $_GET['search'];
                if (isset($_GET['limit']))      $filters['limit'] = $_GET['limit'];
                if (isset($_GET['offset']))     $filters['offset'] = $_GET['offset'];
                $result = $controller->list($filters);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $errors = EscrowTransactionsValidator::validateCreate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'Escrow transaction created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            if (isset($data['update_status']) && isset($data['status'])) {
                $controller->updateStatus($id, $data['status']);
                ResponseFormatter::success(null, 'Escrow status updated');
                break;
            }
            $errors = EscrowTransactionsValidator::validateUpdate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $controller->update($id, $data);
            ResponseFormatter::success(null, 'Escrow transaction updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->delete($id);
            ResponseFormatter::success(null, 'Escrow transaction deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}
