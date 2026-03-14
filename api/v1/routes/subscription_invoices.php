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
require_once $baseDir . '/shared/helpers/AuditLogger.php';
require_once $baseDir . '/shared/config/db.php';

// Load model classes
$modelsPath = API_VERSION_PATH . '/models/subscriptions';
require_once $modelsPath . '/repositories/PdoSubscriptionInvoicesRepository.php';
require_once $modelsPath . '/validators/SubscriptionInvoicesValidator.php';
require_once $modelsPath . '/services/SubscriptionInvoicesService.php';
require_once $modelsPath . '/controllers/SubscriptionInvoicesController.php';

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
    $repo       = new PdoSubscriptionInvoicesRepository($pdo);
    $service    = new SubscriptionInvoicesService($repo);
    $controller = new SubscriptionInvoicesController($service);
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
                if (!$item) { ResponseFormatter::error('Invoice not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (isset($_GET['subscription_id'])) $filters['subscription_id'] = $_GET['subscription_id'];
                if (isset($_GET['tenant_id']))       $filters['tenant_id'] = $_GET['tenant_id'];
                if (isset($_GET['status']))          $filters['status'] = $_GET['status'];
                if (isset($_GET['search']))          $filters['search'] = $_GET['search'];
                if (isset($_GET['date_from']))       $filters['date_from'] = $_GET['date_from'];
                if (isset($_GET['date_to']))         $filters['date_to'] = $_GET['date_to'];
                if (isset($_GET['limit']))           $filters['limit'] = $_GET['limit'];
                if (isset($_GET['offset']))          $filters['offset'] = $_GET['offset'];

                if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                    $rows = $repo->all(10000, 0, $filters);
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="invoices_' . date('Y-m-d') . '.csv"');
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF"); // BOM for Arabic
                    fputcsv($out, ['Invoice #', 'Subscription ID', 'Tenant', 'Amount', 'Tax', 'Total', 'Currency', 'Status', 'Due Date', 'Paid At', 'Created']);
                    foreach ($rows as $row) {
                        fputcsv($out, [$row['invoice_number'], $row['subscription_id'], $row['tenant_name'] ?? $row['tenant_id'], $row['amount'], $row['tax_amount'], $row['total_amount'], $row['currency_code'], $row['status'], $row['due_date'], $row['paid_at'] ?? '', $row['created_at']]);
                    }
                    fclose($out);
                    exit;
                }

                $result = $controller->list($filters);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            if (isset($data['mark_paid']) && isset($data['id'])) {
                $controller->markPaid((int)$data['id'], $data['payment_method'] ?? '', $data['transaction_id'] ?? '');
                AuditLogger::log('invoice_marked_paid', 'subscription_invoice', (int)$data['id']);
                ResponseFormatter::success(null, 'Invoice marked as paid');
                break;
            }
            $errors = SubscriptionInvoicesValidator::validateCreate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $id = $controller->create($data);
            AuditLogger::log('invoice_created', 'subscription_invoice', $id);
            ResponseFormatter::success(['id' => $id], 'Invoice created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $errors = SubscriptionInvoicesValidator::validateUpdate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $controller->update($id, $data);
            AuditLogger::log('invoice_updated', 'subscription_invoice', $id);
            ResponseFormatter::success(null, 'Invoice updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->delete($id);
            AuditLogger::log('invoice_deleted', 'subscription_invoice', $id);
            ResponseFormatter::success(null, 'Invoice deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

