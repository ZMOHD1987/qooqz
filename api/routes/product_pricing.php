<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir.'/bootstrap.php';
require_once $baseDir.'/shared/core/ResponseFormatter.php';
require_once $baseDir.'/shared/helpers/safe_helpers.php';
require_once $baseDir.'/shared/config/db.php';

$modelsPath = API_VERSION_PATH.'/models/products';
require_once $modelsPath.'/repositories/PdoProductPricingRepository.php';
require_once $modelsPath.'/services/ProductPricingService.php';
require_once $modelsPath.'/controllers/ProductPricingController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'];
$repo = new PdoProductPricingRepository($pdo);
$service = new ProductPricingService($repo);
$controller = new ProductPricingController($service);

$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
if (!$tenantId) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $filters = [
        'product_id' => $_GET['product_id'] ?? null,
        'variant_id' => $_GET['variant_id'] ?? null,
        'currency_code' => $_GET['currency_code'] ?? null,
        'pricing_type' => $_GET['pricing_type'] ?? null,
        'is_active' => $_GET['is_active'] ?? null
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                ResponseFormatter::success(
                    $controller->get($tenantId, (int)$_GET['id'])
                );
            } else {
                ResponseFormatter::success(
                    $controller->list(
                        $tenantId,
                        isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : null,
                        isset($_GET['offset']) && is_numeric($_GET['offset']) ? (int)$_GET['offset'] : null,
                        $filters,
                        $_GET['order_by'] ?? 'id',
                        $_GET['order_dir'] ?? 'DESC'
                    )
                );
            }
            break;

        case 'POST':
            ResponseFormatter::success(
                ['id'=>$controller->create($tenantId, $data)],
                'Created',
                201
            );
            break;

        case 'PUT':
            ResponseFormatter::success(
                ['id'=>$controller->update($tenantId, $data)],
                'Updated'
            );
            break;

        case 'DELETE':
            ResponseFormatter::success(
                ['deleted'=>$controller->delete($tenantId, (int)$data['id'])],
                'Deleted'
            );
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (Throwable $e) {
    safe_log('error','product_pricing', ['error'=>$e->getMessage()]);
    $msg = ($e instanceof InvalidArgumentException) ? $e->getMessage() : 'Internal server error';
    ResponseFormatter::error($msg, 500);
}