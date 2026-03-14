<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once API_VERSION_PATH.'/models/entities/repositories/PdoEntityBankAccountsRepository.php';
require_once API_VERSION_PATH.'/models/entities/services/EntityBankAccountsService.php';
require_once API_VERSION_PATH.'/models/entities/controllers/EntityBankAccountsController.php';
require_once API_VERSION_PATH.'/models/entities/validators/EntityBankAccountsValidator.php';

$pdo = $GLOBALS['ADMIN_DB'];

$tenantId = (int)($_SESSION['tenant_id'] ?? 0);

// Parse request body data (supports JSON, FormData for POST/PUT/DELETE)
require_once __DIR__ . '/../../shared/helpers/request_parser.php';
$data = parse_request_data();

// Get entity_id from query string or parsed body
$entityId = (int)($_REQUEST['entity_id'] ?? $data['entity_id'] ?? 0);

$roles = $_SESSION['roles'] ?? [];
$isSuperAdmin = in_array('super_admin', $roles);

if (!$tenantId && !$isSuperAdmin) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}
if (!$entityId && !$isSuperAdmin) {
    ResponseFormatter::error('entity_id is required', 400);
    exit;
}

$repo = new PdoEntityBankAccountsRepository($pdo);
$service = new EntityBankAccountsService($repo);
$controller = new EntityBankAccountsController($service);

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['id'])) {
                ResponseFormatter::success(
                    $controller->get($tenantId, $entityId, (int)$_GET['id'])
                );
            } else {
                $filters = [];
                if (!empty($_GET['search'])) $filters['search'] = substr($_GET['search'], 0, 100);
                if (isset($_GET['is_active']) && in_array($_GET['is_active'], ['0', '1'], true)) $filters['is_active'] = $_GET['is_active'];
                if (!empty($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
                if (!empty($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];

                ResponseFormatter::success(
                    $controller->list(
                        $tenantId,
                        $entityId,
                        (int)($_GET['limit'] ?? 25),
                        (int)($_GET['offset'] ?? 0),
                        $_GET['order_by'] ?? 'id',
                        $_GET['order_dir'] ?? 'DESC',
                        $filters
                    )
                );
            }
            break;

        case 'POST':
        case 'PUT':
            $id = $controller->save($tenantId, $entityId, $data);
            ResponseFormatter::success(['id' => $id]);
            break;

        case 'DELETE':
            $deleteId = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if (!$deleteId) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            ResponseFormatter::success([
                'deleted' => $controller->delete($tenantId, $entityId, $deleteId)
            ]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 400);
}
