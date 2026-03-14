<?php
declare(strict_types=1);

// ============================================================
// Dependencies
// ============================================================
require_once API_VERSION_PATH . '/models/delivery_zones/Contracts/DeliveryProviderRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/delivery_zones/repositories/PdoDeliveryProviderRepository.php';
require_once API_VERSION_PATH . '/models/delivery_zones/validators/DeliveryProviderValidator.php';
require_once API_VERSION_PATH . '/models/delivery_zones/services/DeliveryProviderService.php';
require_once API_VERSION_PATH . '/models/delivery_zones/controllers/DeliveryProviderController.php';

// ============================================================
// Bootstrap
// ============================================================
if (!defined('API_VERSION_PATH')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// ============================================================
// Database
// ============================================================
/** @var PDO $pdo */
 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Service unavailable', 503);
    exit;
}

// ============================================================
// Wiring
// ============================================================
 $controller = new DeliveryProviderController(
    new DeliveryProviderService(
        new PdoDeliveryProviderRepository($pdo),
        new DeliveryProviderValidator()
    )
);

// ============================================================
// Tenant resolution
// ============================================================
 $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
if ($tenantId === 0) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

// ============================================================
// Request parsing
// ============================================================
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

// ============================================================
// Route dispatch
// ============================================================
try {

    switch ($method) {

        // ----------------------------------------------------
        // GET
        // ----------------------------------------------------
        case 'GET':
            if ($id !== null) {
                $provider = $controller->get($tenantId, $id, $lang);
                if ($provider === null) {
                    ResponseFormatter::error('Delivery provider not found', 404);
                    break;
                }
                ResponseFormatter::success($provider);
                break;
            }

            // Filters specific to delivery_providers
            $filters = [];
            foreach (['provider_type', 'vehicle_type', 'is_online', 'is_active', 'entity_id'] as $key) {
                if (isset($_GET[$key]) && $_GET[$key] !== '') {
                    $filters[$key] = $_GET[$key];
                }
            }

            $page     = max(1, (int)($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'dp.id';
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

        // ----------------------------------------------------
        // POST
        // ----------------------------------------------------
        case 'POST':
            $data  = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Delivery provider created successfully', 201);
            break;

        // ----------------------------------------------------
        // PUT
        // ----------------------------------------------------
        case 'PUT':
            $data = json_decode((string)file_get_contents('php://input'), true) ?? [];

            if ($id !== null && empty($data['id'])) {
                $data['id'] = $id;
            }

            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required for update', 422);
                break;
            }

            $updated = $controller->update($tenantId, $data);
            ResponseFormatter::success(['updated' => $updated], 'Delivery provider updated successfully');
            break;

        // ----------------------------------------------------
        // DELETE
        // ----------------------------------------------------
        case 'DELETE':
            if ($id !== null) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Delivery provider deleted successfully');
                break;
            }

            $input    = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $deleteId = isset($input['id']) && ctype_digit((string)$input['id']) ? (int)$input['id'] : null;

            if ($deleteId === null) {
                ResponseFormatter::error('Missing id', 400);
                break;
            }

            $deleted = $controller->delete($tenantId, $deleteId);
            ResponseFormatter::success(['deleted' => $deleted], 'Delivery provider deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('warning', '[DeliveryProviders] Validation failed', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (PDOException $e) {
    safe_log('error', '[DeliveryProviders] Database error', [
        'tenant_id' => $tenantId,
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('A database error occurred.', 500);

} catch (Throwable $e) {
    safe_log('error', '[DeliveryProviders] Unexpected error', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('An unexpected error occurred.', 500);
}