<?php
declare(strict_types=1);

require_once API_VERSION_PATH . '/models/delivery_zones/Contracts/ProviderZoneRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/delivery_zones/repositories/PdoProviderZoneRepository.php';
require_once API_VERSION_PATH . '/models/delivery_zones/validators/ProviderZoneValidator.php';
require_once API_VERSION_PATH . '/models/delivery_zones/services/ProviderZoneService.php';
require_once API_VERSION_PATH . '/models/delivery_zones/controllers/ProviderZoneController.php';

if (!defined('API_VERSION_PATH')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Service unavailable', 503);
    exit;
}

 $controller = new ProviderZoneController(
    new ProviderZoneService(
        new PdoProviderZoneRepository($pdo),
        new ProviderZoneValidator()
    )
);

 $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
if ($tenantId === 0) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

 $method = $_SERVER['REQUEST_METHOD'];

// Helper to get composite keys from query params for GET/DELETE
 $providerIdInput = isset($_GET['provider_id']) && ctype_digit((string)$_GET['provider_id']) ? (int)$_GET['provider_id'] : null;
 $zoneIdInput     = isset($_GET['zone_id']) && ctype_digit((string)$_GET['zone_id']) ? (int)$_GET['zone_id'] : null;

 $lang = in_array($_GET['lang'] ?? 'ar', ['ar', 'en'], true) ? ($_GET['lang'] ?? 'ar') : 'ar';

try {
    switch ($method) {
        case 'GET':
            // If both keys present, find specific assignment
            if ($providerIdInput !== null && $zoneIdInput !== null) {
                $item = $controller->get($tenantId, $providerIdInput, $zoneIdInput, $lang);
                if ($item === null) {
                    ResponseFormatter::error('Assignment not found', 404);
                    break;
                }
                ResponseFormatter::success($item);
                break;
            }

            // Otherwise list
            $filters = [];
            if ($providerIdInput !== null) $filters['provider_id'] = $providerIdInput;
            if ($zoneIdInput !== null) $filters['zone_id'] = $zoneIdInput;
            if (isset($_GET['is_active'])) $filters['is_active'] = $_GET['is_active'];

            $page     = max(1, (int)($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'pz.provider_id';
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
            $created = $controller->create($tenantId, $data);
            ResponseFormatter::success(['created' => $created], 'Zone assigned to provider successfully', 201);
            break;

        case 'PUT':
            $data = json_decode((string)file_get_contents('php://input'), true) ?? [];
            
            // Require composite key in body
            if (empty($data['provider_id']) || empty($data['zone_id'])) {
                ResponseFormatter::error('provider_id and zone_id are required in body to update assignment', 422);
                break;
            }

            $updated = $controller->update($tenantId, $data);
            ResponseFormatter::success(['updated' => $updated], 'Assignment updated successfully');
            break;

        case 'DELETE':
            // Accept keys from Query String
            $pId = $providerIdInput;
            $zId = $zoneIdInput;

            // Or from Body
            if ($pId === null || $zId === null) {
                $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
                if (isset($input['provider_id'])) $pId = (int)$input['provider_id'];
                if (isset($input['zone_id'])) $zId = (int)$input['zone_id'];
            }

            if ($pId === null || $zId === null) {
                ResponseFormatter::error('provider_id and zone_id are required to delete assignment', 400);
                break;
            }

            $deleted = $controller->delete($tenantId, $pId, $zId);
            ResponseFormatter::success(['deleted' => $deleted], 'Assignment removed successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (PDOException $e) {
    // Handle Duplicate entry error for create
    if ($e->getCode() == 23000) { 
         ResponseFormatter::error('This provider is already assigned to this zone.', 409);
    } else {
        safe_log('error', '[ProviderZones] DB Error', ['error' => $e->getMessage()]);
        ResponseFormatter::error('Database error', 500);
    }
} catch (Throwable $e) {
    safe_log('error', '[ProviderZones] Error', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Unexpected error', 500);
}