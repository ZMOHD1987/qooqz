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
require_once $modelsPath . '/repositories/PdoSubscriptionsRepository.php';
require_once $modelsPath . '/validators/SubscriptionsValidator.php';
require_once $modelsPath . '/services/SubscriptionsService.php';
require_once $modelsPath . '/controllers/SubscriptionsController.php';

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
    $repo       = new PdoSubscriptionsRepository($pdo);
    $service    = new SubscriptionsService($repo);
    $controller = new SubscriptionsController($service);
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
                if (!$item) { ResponseFormatter::error('Subscription not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (isset($_GET['tenant_id'])) $filters['tenant_id'] = $_GET['tenant_id'];
                if (isset($_GET['plan_id']))   $filters['plan_id'] = $_GET['plan_id'];
                if (isset($_GET['status']))    $filters['status'] = $_GET['status'];
                if (isset($_GET['search']))    $filters['search'] = $_GET['search'];
                if (isset($_GET['limit']))     $filters['limit'] = $_GET['limit'];
                if (isset($_GET['offset']))    $filters['offset'] = $_GET['offset'];
                $result = $controller->list($filters);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $errors = SubscriptionsValidator::validateCreate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $result = $controller->create($data);
            AuditLogger::log('subscription_created', 'subscription', $result['id']);
            ResponseFormatter::success(['id' => $result['id'], 'invoice_id' => $result['invoice_id'] ?? 0], 'Subscription created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];

            // Upgrade flow: cancel old subscription + create new one from plan
            if (!empty($data['upgrade']) && !empty($data['plan_id']) && !empty($data['tenant_id'])) {
                $planId = (int)$data['plan_id'];
                $tid = (int)$data['tenant_id'];
                // Fetch plan details
                $planStmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = :id AND is_active = 1 LIMIT 1");
                $planStmt->execute([':id' => $planId]);
                $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
                if (!$plan) { ResponseFormatter::error('Plan not found or inactive', 404); break; }
                $result = $repo->upgrade($tid, $planId, $plan);
                AuditLogger::log('subscription_upgraded', 'subscription', $result['id']);
                ResponseFormatter::success(['id' => $result['id'], 'invoice_id' => $result['invoice_id'] ?? 0], 'Subscription upgraded', 200);
                break;
            }

            // Check active subscription for tenant (product limit endpoint)
            if (isset($_GET['check_limit']) && !empty($data['tenant_id'])) {
                $tid = (int)$data['tenant_id'];
                $active = $repo->hasActiveSubscription($tid);
                if (!$active) {
                    ResponseFormatter::success(['allowed' => false, 'reason' => 'no_subscription', 'current' => 0, 'max' => 0]);
                    break;
                }
                $maxProducts = (int)($active['max_products'] ?? 0);
                $currentCount = $repo->getTenantProductCount($tid);
                $allowed = $maxProducts === 0 || $currentCount < $maxProducts; // 0 = unlimited
                ResponseFormatter::success([
                    'allowed' => $allowed,
                    'current' => $currentCount,
                    'max' => $maxProducts,
                    'plan_name' => $active['plan_name'] ?? '',
                    'subscription_id' => (int)$active['id'],
                ]);
                break;
            }

            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            if (isset($data['status']) && count($data) === 2 && isset($data['id'])) {
                $controller->updateStatus($id, $data['status']);
                AuditLogger::log('subscription_updated', 'subscription', $id);
                ResponseFormatter::success(null, 'Subscription status updated');
                break;
            }
            $errors = SubscriptionsValidator::validateUpdate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $controller->update($id, $data);
            AuditLogger::log('subscription_updated', 'subscription', $id);
            ResponseFormatter::success(null, 'Subscription updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->delete($id);
            AuditLogger::log('subscription_deleted', 'subscription', $id);
            ResponseFormatter::success(null, 'Subscription deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

