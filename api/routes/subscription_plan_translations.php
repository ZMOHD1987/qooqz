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
require_once $modelsPath . '/repositories/PdoSubscriptionPlanTranslationsRepository.php';
require_once $modelsPath . '/validators/SubscriptionPlanTranslationsValidator.php';
require_once $modelsPath . '/services/SubscriptionPlanTranslationsService.php';
require_once $modelsPath . '/controllers/SubscriptionPlanTranslationsController.php';

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
    $repo       = new PdoSubscriptionPlanTranslationsRepository($pdo);
    $service    = new SubscriptionPlanTranslationsService($repo);
    $controller = new SubscriptionPlanTranslationsController($service);
    $method     = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            if (isset($_GET['plan_id']) && (int)$_GET['plan_id'] > 0) {
                $items = $controller->listByPlan((int)$_GET['plan_id']);
                ResponseFormatter::success($items);
            } else {
                ResponseFormatter::error('plan_id is required', 400);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $errors = SubscriptionPlanTranslationsValidator::validateUpsert($data);
            if ($errors) { ResponseFormatter::error(implode(', ', $errors), 422); break; }
            $id = $controller->upsert((int)$data['plan_id'], $data['language_code'], $data);
            ResponseFormatter::success(['id' => $id], 'Translation saved', 201);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->delete($id);
            ResponseFormatter::success(null, 'Translation deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}
