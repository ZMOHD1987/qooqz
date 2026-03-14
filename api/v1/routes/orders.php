<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/orders';
require_once $modelsPath . '/repositories/PdoOrdersRepository.php';
require_once $modelsPath . '/services/OrdersService.php';
require_once $modelsPath . '/controllers/OrdersController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoOrdersRepository($pdo);
$service = new OrdersService($repo);
$controller = new OrdersController($service);

$user = $_SESSION['user'] ?? [];
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $lang = $_GET['lang'] ?? 'ar';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'user_id' => isset($_GET['user_id']) ? (int)$_GET['user_id'] : null,
        'status' => $_GET['status'] ?? null,
        'payment_status' => $_GET['payment_status'] ?? null,
        'fulfillment_status' => $_GET['fulfillment_status'] ?? null,
        'order_type' => $_GET['order_type'] ?? null,
        'created_from' => $_GET['created_from'] ?? null,
        'created_to' => $_GET['created_to'] ?? null
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['order_number'])) {
                $order = $controller->getByOrderNumber($tenantId, $_GET['order_number']);
                ResponseFormatter::success($order);
                break;
            }

            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($tenantId, (int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta' => [
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from' => $total > 0 ? $offset + 1 : 0,
                        'to' => $total > 0 ? min($offset + $limit, $total) : 0
                    ]
                ]);
            }
            break;

        case 'POST':
            require_once $modelsPath . '/validators/OrdersValidator.php';
            $validator = new App\Models\Orders\Validators\OrdersValidator();
            $validator->validate($data, false);
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required for update', 400);
                exit;
            }
            require_once $modelsPath . '/validators/OrdersValidator.php';
            $validator = new App\Models\Orders\Validators\OrdersValidator();
            $validator->validate($data, true);
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing order ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete($tenantId, (int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'orders.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'orders.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'orders.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error', 500);
}
