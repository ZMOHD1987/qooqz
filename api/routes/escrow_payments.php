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
require_once $baseDir . '/shared/helpers/AuditLogger.php';
require_once $baseDir . '/shared/config/db.php';

// Load model classes
$modelsPath = API_VERSION_PATH . '/models/subscriptions';
require_once $modelsPath . '/repositories/PdoEscrowPaymentsRepository.php';
require_once $modelsPath . '/validators/EscrowPaymentsValidator.php';
require_once $modelsPath . '/services/EscrowPaymentsService.php';
require_once $modelsPath . '/controllers/EscrowPaymentsController.php';

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
    AuditLogger::init($pdo);
    $repo       = new PdoEscrowPaymentsRepository($pdo);
    $service    = new EscrowPaymentsService($repo);
    $controller = new EscrowPaymentsController($service);
    $method     = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                $filters = [];
                if (isset($_GET['status']))    $filters['status']    = $_GET['status'];
                if (isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
                if (isset($_GET['date_to']))   $filters['date_to']   = $_GET['date_to'];
                $stats = $controller->stats($filters);
                ResponseFormatter::success($stats);
                break;
            }
            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $item = $controller->find((int)$_GET['id']);
                if (!$item) { ResponseFormatter::error('Escrow payment not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (isset($_GET['escrow_id']))  $filters['escrow_id']  = $_GET['escrow_id'];
                if (isset($_GET['status']))     $filters['status']     = $_GET['status'];
                if (isset($_GET['date_from']))  $filters['date_from']  = $_GET['date_from'];
                if (isset($_GET['date_to']))    $filters['date_to']    = $_GET['date_to'];
                if (isset($_GET['limit']))      $filters['limit']      = $_GET['limit'];
                if (isset($_GET['offset']))     $filters['offset']     = $_GET['offset'];
                if (isset($_GET['order_by']))   $filters['order_by']   = $_GET['order_by'];
                if (isset($_GET['order_dir']))  $filters['order_dir']  = $_GET['order_dir'];

                $result = $controller->all($filters);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            if (isset($data['mark_success']) && isset($data['id'])) {
                $controller->markSuccess((int)$data['id'], $data['gateway_transaction_id'] ?? '');
                AuditLogger::log('escrow_payment_marked_success', 'escrow_payment', (int)$data['id']);
                ResponseFormatter::success(null, 'Escrow payment marked as success');
                break;
            }
            if (isset($data['mark_refunded']) && isset($data['id'])) {
                $controller->markRefunded((int)$data['id']);
                AuditLogger::log('escrow_payment_marked_refunded', 'escrow_payment', (int)$data['id']);
                ResponseFormatter::success(null, 'Escrow payment marked as refunded');
                break;
            }
            $errors = EscrowPaymentsValidator::validateCreate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $id = $controller->create($data);
            AuditLogger::log('escrow_payment_created', 'escrow_payment', $id);
            ResponseFormatter::success(['id' => $id], 'Escrow payment created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }

            if (isset($data['mark_success'])) {
                $controller->markSuccess($id, $data['gateway_transaction_id'] ?? '');
                AuditLogger::log('escrow_payment_marked_success', 'escrow_payment', $id);
                ResponseFormatter::success(null, 'Escrow payment marked as success');
                break;
            }
            if (isset($data['mark_refunded'])) {
                $controller->markRefunded($id);
                AuditLogger::log('escrow_payment_marked_refunded', 'escrow_payment', $id);
                ResponseFormatter::success(null, 'Escrow payment marked as refunded');
                break;
            }

            ResponseFormatter::error('No valid action specified for PUT', 400);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->delete($id);
            AuditLogger::log('escrow_payment_deleted', 'escrow_payment', $id);
            ResponseFormatter::success(null, 'Escrow payment deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}
