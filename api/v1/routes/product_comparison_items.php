<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/products/repositories/PdoProductComparisonItemsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductComparisonItemsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductComparisonItemsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductComparisonItemsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$repo       = new PdoProductComparisonItemsRepository($pdo);
$validator  = new ProductComparisonItemsValidator();
$service    = new ProductComparisonItemsService($repo, $validator);
$controller = new ProductComparisonItemsController($service);

try {
    $tenantId = $_GET['tenant_id'] ?? $_SESSION['tenant_id'] ?? null;
    if (!$tenantId || !is_numeric($tenantId)) {
        ResponseFormatter::error('Unauthorized: tenant not found', 401);
        exit;
    }
    $tenantId = (int)$tenantId;

    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments   = explode('/', trim($requestUri, '/'));
    $id = null;
    foreach ($segments as $seg) {
        if (ctype_digit($seg)) {
            $id = (int)$seg;
            break;
        }
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['comparison_id']) && is_numeric($_GET['comparison_id'])) {
                $lang = $_GET['lang'] ?? 'ar';
                $items = $controller->getByComparison($tenantId, (int)$_GET['comparison_id'], $lang);
                ResponseFormatter::success($items);
            } elseif ($id) {
                $lang = $_GET['lang'] ?? 'ar';
                $item = $controller->find($tenantId, $id, $lang);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Item not found', 404);
                }
            } else {
                ResponseFormatter::error('Missing comparison_id or item id', 400);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Item created successfully', 201);
            break;

        case 'DELETE':
            if (isset($_GET['comparison_id']) && is_numeric($_GET['comparison_id'])) {
                $deleted = $controller->deleteByComparison($tenantId, (int)$_GET['comparison_id']);
                ResponseFormatter::success(['deleted' => $deleted], 'All items of comparison deleted successfully');
            } elseif ($id) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Item deleted successfully');
            } else {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $deleteId = $input['id'] ?? null;
                if ($deleteId && is_numeric($deleteId)) {
                    $deleted = $controller->delete($tenantId, (int)$deleteId);
                    ResponseFormatter::success(['deleted' => $deleted], 'Item deleted successfully');
                } else {
                    ResponseFormatter::error('Missing comparison_id or item id', 400);
                }
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('error', 'Validation error in ProductComparisonItems', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (PDOException $e) {
    safe_log('error', 'Database error in ProductComparisonItems', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    safe_log('error', 'ProductComparisonItems route failed', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}
