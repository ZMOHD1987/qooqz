<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/products';
require_once $modelsPath . '/repositories/PdoProductsRepository.php';
require_once $modelsPath . '/services/ProductsService.php';
require_once $modelsPath . '/controllers/ProductsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoProductsRepository($pdo);
$service = new ProductsService($repo);
$controller = new ProductsController($service);

// ================================
// Tenant & Auth check
// ================================
$user = $_SESSION['user'] ?? [];
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : ($_SESSION['tenant_id'] ?? null);

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

    $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
    $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [
        'product_type_id' => $_GET['product_type_id'] ?? null,
        'sku'             => $_GET['sku'] ?? null,
        'slug'            => $_GET['slug'] ?? null,
        'barcode'         => $_GET['barcode'] ?? null,
        'brand_id'        => $_GET['brand_id'] ?? null,
        'is_active'       => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($tenantId, (int)$_GET['id']);
                ResponseFormatter::success($item);
            } else {
                $list = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
                ResponseFormatter::success($list);
            }
            break;

        case 'POST':
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing product ID for deletion', 400);
            }
            $deleted = $controller->delete($tenantId, (int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','products.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','products.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical','products.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error('Internal server error', 500);
}
