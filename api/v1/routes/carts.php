<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/carts';
require_once $modelsPath . '/repositories/PdoCartsRepository.php';
require_once $modelsPath . '/services/CartsService.php';
require_once $modelsPath . '/controllers/CartsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoCartsRepository($pdo);
$service = new CartsService($repo);
$controller = new CartsController($service);

// ================================
// Tenant & Auth check
// ================================
$user = $_SESSION['user'] ?? [];
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $lang    = $_GET['lang'] ?? 'ar';
    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [
        'user_id'    => isset($_GET['user_id']) ? (int)$_GET['user_id'] : null,
        'session_id' => $_GET['session_id'] ?? null,
        'device_id'  => $_GET['device_id'] ?? null,
        'status'     => $_GET['status'] ?? null,
        'entity_id'  => isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Special endpoint: get cart by session
            if (isset($_GET['session_id']) && isset($_GET['entity_id']) && !isset($_GET['id'])) {
                $cart = $controller->getBySession(
                    $tenantId,
                    $_GET['session_id'],
                    (int)$_GET['entity_id']
                );
                ResponseFormatter::success($cart);
                break;
            }

            // Special endpoint: get cart by user
            if (isset($_GET['user_id']) && isset($_GET['entity_id']) && !isset($_GET['id'])) {
                $cart = $controller->getByUser(
                    $tenantId,
                    (int)$_GET['user_id'],
                    (int)$_GET['entity_id']
                );
                ResponseFormatter::success($cart);
                break;
            }

            // Get single cart by ID
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($tenantId, (int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            } else {
                // List carts
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0
                    ]
                ]);
            }
            break;

        case 'POST':
            // Validate using validator
            require_once $modelsPath . '/validators/CartsValidator.php';
            $validator = new App\Models\Carts\Validators\CartsValidator();
            $validator->validate($data, false);

            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required for update', 400);
                exit;
            }

            // Validate using validator
            require_once $modelsPath . '/validators/CartsValidator.php';
            $validator = new App\Models\Carts\Validators\CartsValidator();
            $validator->validate($data, true);

            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing cart ID for deletion', 400);
                exit;
            }
            $deleted = $controller->delete($tenantId, (int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'carts.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'carts.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'carts.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error', 500);
}

