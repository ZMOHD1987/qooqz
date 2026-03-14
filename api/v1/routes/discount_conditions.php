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

// Load controller stack
$modelsDir = dirname(__DIR__, 2) . '/v1/models/discounts';
require_once $modelsDir . '/repositories/PdoDiscountsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountTranslationsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountScopesRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountConditionsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountActionsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountRedemptionsRepository.php';
require_once $modelsDir . '/repositories/PdoDiscountExclusionsRepository.php';
require_once $modelsDir . '/services/DiscountsService.php';
require_once $modelsDir . '/controllers/DiscountsController.php';

try {
    $pdo        = $GLOBALS['ADMIN_DB'];
    $controller = new DiscountsController(new DiscountsService($pdo));
    $method     = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $discountId = (int)($_GET['discount_id'] ?? 0);
            if ($discountId <= 0) { ResponseFormatter::error('discount_id is required', 400); break; }

            $items = $controller->listConditions($discountId);
            ResponseFormatter::success($items);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $required = ['discount_id', 'condition_type', 'operator', 'condition_value'];
            $missing = [];
            foreach ($required as $f) {
                if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
            }
            if ($missing) { ResponseFormatter::error('Missing required fields: ' . implode(', ', $missing), 422); break; }

            $id = $controller->createCondition($data);
            ResponseFormatter::success(['id' => $id], 'Condition created', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }

            unset($data['id']);
            if (empty($data)) { ResponseFormatter::error('No fields to update', 422); break; }

            $controller->updateCondition($id, $data);
            ResponseFormatter::success(null, 'Condition updated');
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { ResponseFormatter::error('ID is required', 400); break; }
            $controller->deleteCondition($id);
            ResponseFormatter::success(null, 'Condition deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 422);
}

