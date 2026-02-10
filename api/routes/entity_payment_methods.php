<?php
declare(strict_types=1);

require_once __DIR__.'/../bootstrap.php';

require_once API_VERSION_PATH.'/models/entities/repositories/PdoEntityPaymentMethodsRepository.php';
require_once API_VERSION_PATH.'/models/entities/services/EntityPaymentMethodsService.php';
require_once API_VERSION_PATH.'/models/entities/controllers/EntityPaymentMethodsController.php';
require_once API_VERSION_PATH.'/models/entities/validators/EntityPaymentMethodsValidator.php';

$pdo = $GLOBALS['ADMIN_DB'];

$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$entityId = (int)($_REQUEST['entity_id'] ?? 0);

if (!$tenantId || !$entityId) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

$repo = new PdoEntityPaymentMethodsRepository($pdo);
$service = new EntityPaymentMethodsService($repo);
$controller = new EntityPaymentMethodsController($service);

$data = json_decode(file_get_contents('php://input'), true) ?? [];
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['id'])) {
                ResponseFormatter::success(
                    $controller->get($tenantId, $entityId, (int)$_GET['id'])
                );
            } else {
                ResponseFormatter::success(
                    $controller->list(
                        $tenantId,
                        $entityId,
                        $_GET['limit'] ?? 25,
                        $_GET['offset'] ?? 0,
                        $_GET['order_by'] ?? 'id',
                        $_GET['order_dir'] ?? 'DESC'
                    )
                );
            }
            break;

        case 'POST':
        case 'PUT':
            $id = $controller->save($tenantId, $entityId, $data);
            ResponseFormatter::success(['id'=>$id]);
            break;

        case 'DELETE':
            ResponseFormatter::success([
                'deleted'=>$controller->delete($tenantId, $entityId, (int)$data['id'])
            ]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 400);
}
