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
$modelsPath = API_VERSION_PATH . '/models/subscriptions';
require_once $modelsPath . '/repositories/PdoSubscriptionPlansRepository.php';
require_once $modelsPath . '/validators/SubscriptionPlansValidator.php';
require_once $modelsPath . '/services/SubscriptionPlansService.php';
require_once $modelsPath . '/controllers/SubscriptionPlansController.php';

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
    $repo       = new PdoSubscriptionPlansRepository($pdo);
    $service    = new SubscriptionPlansService($repo);
    $controller = new SubscriptionPlansController($service);
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
                if (!$item) { ResponseFormatter::error('Subscription plan not found', 404); break; }
                ResponseFormatter::success($item);
            } else {
                $filters = [];
                if (isset($_GET['plan_type']))      $filters['plan_type'] = $_GET['plan_type'];
                if (isset($_GET['billing_period'])) $filters['billing_period'] = $_GET['billing_period'];
                if (isset($_GET['is_active']))      $filters['is_active'] = $_GET['is_active'];
                if (isset($_GET['search']))         $filters['search'] = $_GET['search'];
                if (isset($_GET['limit']))          $filters['limit'] = $_GET['limit'];
                if (isset($_GET['offset']))         $filters['offset'] = $_GET['offset'];
                $result = $controller->list($filters);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $errors = SubscriptionPlansValidator::validateCreate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'Subscription plan created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $errors = SubscriptionPlansValidator::validateUpdate($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $controller->update($id, $data);
            ResponseFormatter::success(null, 'Subscription plan updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->delete($id);
            ResponseFormatter::success(null, 'Subscription plan deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

