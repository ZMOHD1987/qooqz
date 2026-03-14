<?php
declare(strict_types=1);

require_once API_VERSION_PATH . '/models/delivery_zones/Contracts/DeliveryTrackingRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/delivery_zones/repositories/PdoDeliveryTrackingRepository.php';
require_once API_VERSION_PATH . '/models/delivery_zones/validators/DeliveryTrackingValidator.php';
require_once API_VERSION_PATH . '/models/delivery_zones/services/DeliveryTrackingService.php';
require_once API_VERSION_PATH . '/models/delivery_zones/controllers/DeliveryTrackingController.php';

if (!defined('API_VERSION_PATH')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Service unavailable', 503);
    exit;
}

 $controller = new DeliveryTrackingController(
    new DeliveryTrackingService(
        new PdoDeliveryTrackingRepository($pdo),
        new DeliveryTrackingValidator()
    )
);

 $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
if ($tenantId === 0) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

 $method = $_SERVER['REQUEST_METHOD'];
 $uriPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
 $segments = explode('/', trim($uriPath, '/'));
 $id       = null;
foreach ($segments as $seg) {
    if (ctype_digit($seg) && (int)$seg > 0) {
        $id = (int)$seg;
        break;
    }
}

 $lang = in_array($_GET['lang'] ?? 'ar', ['ar', 'en'], true) ? ($_GET['lang'] ?? 'ar') : 'ar';

try {
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $item = $controller->get($tenantId, $id, $lang);
                if ($item === null) {
                    ResponseFormatter::error('Tracking record not found', 404);
                    break;
                }
                ResponseFormatter::success($item);
                break;
            }

            $filters = [];
            if (isset($_GET['delivery_order_id']) && ctype_digit((string)$_GET['delivery_order_id'])) {
                $filters['delivery_order_id'] = (int)$_GET['delivery_order_id'];
            }
            if (isset($_GET['provider_id']) && ctype_digit((string)$_GET['provider_id'])) {
                $filters['provider_id'] = (int)$_GET['provider_id'];
            }

            $page     = max(1, (int)($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'dt.id';
            $orderDir = $_GET['order_dir'] ?? 'DESC';

            $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);

            ResponseFormatter::success([
                'items' => $result['items'],
                'meta'  => [
                    'total'       => $result['total'],
                    'page'        => $page,
                    'per_page'    => $limit,
                    'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0,
                ],
            ]);
            break;

        case 'POST':
            $data  = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Tracking record created successfully', 201);
            break;

        case 'DELETE':
            if ($id !== null) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Tracking record deleted successfully');
                break;
            }
            ResponseFormatter::error('ID required for delete', 400);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (PDOException $e) {
    safe_log('error', '[DeliveryTracking] DB Error', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Database error', 500);
} catch (Throwable $e) {
    safe_log('error', '[DeliveryTracking] Error', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Unexpected error', 500);
}