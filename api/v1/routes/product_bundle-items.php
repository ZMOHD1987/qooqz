<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/config/db.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';

// Adjust the path to match your API_VERSION_PATH
require_once API_VERSION_PATH . '/models/products/repositories/PdoProductBundleItemsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductBundleItemsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductBundleItemsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductBundleItemsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$repo       = new PdoProductBundleItemsRepository($pdo);
$validator  = new ProductBundleItemsValidator();
$service    = new ProductBundleItemsService($repo, $validator);
$controller = new ProductBundleItemsController($service);

try {
    // Get tenant ID from query string or session
    $tenantId = $_GET['tenant_id'] ?? $_SESSION['tenant_id'] ?? null;
    if (!$tenantId || !is_numeric($tenantId)) {
        ResponseFormatter::error('Unauthorized: tenant not found', 401);
        exit;
    }
    $tenantId = (int)$tenantId;

    // Extract ID from URI if present (e.g., /api/bundle-items/123)
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments   = explode('/', trim($requestUri, '/'));
    $id = null;
    foreach ($segments as $seg) {
        if (ctype_digit($seg)) {
            $id = (int)$seg;
            break;
        }
    }

    // Language parameter (default 'ar')
    $lang = $_GET['lang'] ?? 'ar';

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['bundle_id']) && is_numeric($_GET['bundle_id'])) {
                // Get all items of a bundle
                $items = $controller->getByBundle($tenantId, (int)$_GET['bundle_id'], $lang);
                ResponseFormatter::success($items);
            } elseif ($id) {
                // Get a single item by ID
                $item = $controller->find($tenantId, $id, $lang);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Item not found', 404);
                }
            } else {
                ResponseFormatter::error('Missing bundle_id or item id', 400);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Item created successfully', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data['id'])) {
                ResponseFormatter::error('Item ID is required for update', 422);
                break;
            }
            $updated = $controller->update($tenantId, $data);
            ResponseFormatter::success(['updated' => $updated], 'Item updated successfully');
            break;

        case 'DELETE':
            // Support deletion by bundle_id (all items of a bundle) or by single item id
            if (isset($_GET['bundle_id']) && is_numeric($_GET['bundle_id'])) {
                $deleted = $controller->deleteByBundle($tenantId, (int)$_GET['bundle_id']);
                ResponseFormatter::success(['deleted' => $deleted], 'All items of bundle deleted successfully');
            } elseif ($id) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Item deleted successfully');
            } else {
                // Try to read id from request body
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $deleteId = $input['id'] ?? null;
                if ($deleteId && is_numeric($deleteId)) {
                    $deleted = $controller->delete($tenantId, (int)$deleteId);
                    ResponseFormatter::success(['deleted' => $deleted], 'Item deleted successfully');
                } else {
                    ResponseFormatter::error('Missing bundle_id or item id', 400);
                }
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('error', 'Validation error in ProductBundleItems', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (PDOException $e) {
    safe_log('error', 'Database error in ProductBundleItems', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    safe_log('error', 'ProductBundleItems route failed', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}
