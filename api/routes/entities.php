<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntitiesRepository.php';
require_once $modelsPath . '/validators/EntitiesValidator.php';
require_once $modelsPath . '/services/EntitiesService.php';
require_once $modelsPath . '/controllers/EntitiesController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoEntitiesRepository($pdo);
$service    = new EntitiesService($repo);
$controller = new EntitiesController($service);
$validator  = new EntitiesValidator();

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
    $raw    = file_get_contents('php://input');
    $data   = $raw ? json_decode($raw, true) : [];

    $lang      = $_GET['lang'] ?? 'ar';
    $page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit     = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset    = ($page - 1) * $limit;
    $orderBy   = $_GET['order_by'] ?? 'id';
    $orderDir  = $_GET['order_dir'] ?? 'DESC';

    // ================================
    // Collect filters
    // ================================
    $filters = [
        'user_id'       => isset($_GET['user_id']) ? (int)$_GET['user_id'] : null,
        'status'        => $_GET['status'] ?? null,
        'vendor_type'   => $_GET['vendor_type'] ?? null,
        'store_type'    => $_GET['store_type'] ?? null,
        'is_verified'   => isset($_GET['is_verified']) ? (int)$_GET['is_verified'] : null
    ];

    switch ($method) {

        // ================================
        // OPTIONS
        // ================================
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        // ================================
        // GET
        // ================================
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get(
                    $tenantId,
                    (int)$_GET['id'],
                    $lang
                );
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list(
                    $tenantId,
                    $limit,
                    $offset,
                    $filters,
                    $orderBy,
                    $orderDir,
                    $lang
                );

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

        // ================================
        // POST
        // ================================
        case 'POST':
            $validator->validate($data, false);

            $newId = $controller->save(
                $tenantId,
                $data,
                $lang
            );

            ResponseFormatter::success(
                ['id' => $newId],
                'Created successfully',
                201
            );
            break;

        // ================================
        // PUT
        // ================================
        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required for update', 400);
                exit;
            }

            $validator->validate($data, true);

            $updatedId = $controller->save(
                $tenantId,
                $data,
                $lang
            );

            ResponseFormatter::success(
                ['id' => $updatedId],
                'Updated successfully'
            );
            break;

        // ================================
        // DELETE
        // ================================
        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing entity ID for deletion', 400);
                exit;
            }

            $deleted = $controller->delete(
                $tenantId,
                (int)$data['id']
            );

            ResponseFormatter::success(
                ['deleted' => $deleted],
                'Deleted successfully'
            );
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'entities.validation', [
        'error' => $e->getMessage()
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (\RuntimeException $e) {
    safe_log('error', 'entities.runtime', [
        'error' => $e->getMessage()
    ]);
    ResponseFormatter::error($e->getMessage(), 400);

} catch (Throwable $e) {
    safe_log('critical', 'entities.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error('Internal Server Error', 500);
}
