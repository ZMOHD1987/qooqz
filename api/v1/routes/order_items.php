<?php
declare(strict_types=1);
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';
$modelsPath = API_VERSION_PATH . '/models/order_items';
require_once $modelsPath . '/repositories/PdoOrderItemsRepository.php';
require_once $modelsPath . '/services/OrderItemsService.php';
require_once $modelsPath . '/controllers/OrderItemsController.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) { ResponseFormatter::error('Database not initialized', 500); exit; }
$repo = new PdoOrderItemsRepository($pdo);
$service = new OrderItemsService($repo);
$controller = new OrderItemsController($service);
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);
if ($tenantId === null) { ResponseFormatter::error('Unauthorized: tenant not found', 401); exit; }
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];
    $lang = $_GET['lang'] ?? 'ar';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset = ($page - 1) * $limit;
    $filters = ['order_id' => isset($_GET['order_id']) ? (int)$_GET['order_id'] : null, 'product_id' => isset($_GET['product_id']) ? (int)$_GET['product_id'] : null];
    switch ($method) {
        case 'OPTIONS': header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With'); http_response_code(204); exit;
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) { ResponseFormatter::success($controller->get($tenantId, (int)$_GET['id'], $lang)); }
            else { $result = $controller->list($tenantId, $limit, $offset, $filters, 'id', 'DESC', $lang); ResponseFormatter::success(['items' => $result['items'], 'meta' => ['total' => $result['total'], 'page' => $page, 'per_page' => $limit, 'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0]]); }
            break;
        case 'POST': require_once $modelsPath . '/validators/OrderItemsValidator.php'; (new App\Models\OrderItems\Validators\OrderItemsValidator())->validate($data, false); ResponseFormatter::success(['id' => $controller->create($tenantId, $data)], 'Created successfully', 201); break;
        case 'PUT': if (empty($data['id'])) { ResponseFormatter::error('ID required', 400); exit; } require_once $modelsPath . '/validators/OrderItemsValidator.php'; (new App\Models\OrderItems\Validators\OrderItemsValidator())->validate($data, true); ResponseFormatter::success(['id' => $controller->update($tenantId, $data)], 'Updated successfully'); break;
        case 'DELETE': if (empty($data['id'])) { ResponseFormatter::error('ID required', 400); exit; } ResponseFormatter::success(['deleted' => $controller->delete($tenantId, (int)$data['id'])], 'Deleted successfully'); break;
        default: ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) { safe_log('warning', 'order_items.validation', ['error' => $e->getMessage()]); ResponseFormatter::error($e->getMessage(), 422); }
catch (\RuntimeException $e) { safe_log('error', 'order_items.runtime', ['error' => $e->getMessage()]); ResponseFormatter::error($e->getMessage(), 400); }
catch (Throwable $e) { safe_log('critical', 'order_items.fatal', ['error' => $e->getMessage()]); ResponseFormatter::error('Internal Server Error', 500); }

