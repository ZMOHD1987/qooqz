<?php
declare(strict_types=1);

require_once __DIR__.'/../bootstrap.php';
require_once API_VERSION_PATH.'/models/entities/repositories/PdoEntityBankAccountsRepository.php';
require_once API_VERSION_PATH.'/models/entities/services/EntityBankAccountsService.php';
require_once API_VERSION_PATH.'/models/entities/controllers/EntityBankAccountsController.php';
require_once API_VERSION_PATH.'/models/entities/validators/EntityBankAccountsValidator.php';

$pdo = $GLOBALS['ADMIN_DB'];

$repo = new PdoEntityBankAccountsRepository($pdo);
$service = new EntityBankAccountsService($repo);
$controller = new EntityBankAccountsController($service);

$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$entityId = (int)($_REQUEST['entity_id'] ?? 0);

if (!$tenantId || !$entityId) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

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
    }
} catch (Throwable $e) {
    ResponseFormatter::error($e->getMessage(), 400);
}
