<?php
declare(strict_types=1);

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

date_default_timezone_set('Asia/Riyadh');

// Load dependencies
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// Load model classes
$modelsPath = API_VERSION_PATH . '/models/commissions';
require_once $modelsPath . '/repositories/PdoCommissionPaymentsRepository.php';
require_once $modelsPath . '/validators/CommissionPaymentsValidator.php';
require_once $modelsPath . '/services/CommissionPaymentsService.php';
require_once $modelsPath . '/controllers/CommissionPaymentsController.php';

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
    $repo       = new PdoCommissionPaymentsRepository($pdo);
    $service    = new CommissionPaymentsService($repo);
    $controller = new CommissionPaymentsController($service);
    $method     = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                $tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
                $entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
                $stats = $controller->stats($tenantId, $entityId);
                ResponseFormatter::success($stats);
                break;
            }
            if (isset($_GET['generate_number'])) {
                $number = $controller->generatePaymentNumber();
                ResponseFormatter::success(['payment_number' => $number]);
                break;
            }
            if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
                $item = $controller->find((int)$_GET['id']);
                if (!$item) { ResponseFormatter::error('Commission payment not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (isset($_GET['tenant_id']))              $filters['tenant_id'] = $_GET['tenant_id'];
                if (isset($_GET['entity_id']))              $filters['entity_id'] = $_GET['entity_id'];
                if (isset($_GET['commission_invoice_id']))  $filters['commission_invoice_id'] = $_GET['commission_invoice_id'];
                if (isset($_GET['is_cancelled']))           $filters['is_cancelled'] = $_GET['is_cancelled'];
                if (isset($_GET['date_from']))              $filters['date_from'] = $_GET['date_from'];
                if (isset($_GET['date_to']))                $filters['date_to'] = $_GET['date_to'];
                if (isset($_GET['limit']))                  $filters['limit'] = $_GET['limit'];
                if (isset($_GET['offset']))                 $filters['offset'] = $_GET['offset'];
                $result = $controller->list($filters);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            if (empty($data['tenant_id'])) {
                $data['tenant_id'] = $_SESSION['tenant_id'] ?? 1;
            }
            $errors = CommissionPaymentsValidator::validateCreate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'Commission payment created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $errors = CommissionPaymentsValidator::validateUpdate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $controller->update($id, $data);
            ResponseFormatter::success(null, 'Commission payment updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->delete($id);
            ResponseFormatter::success(null, 'Commission payment deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

