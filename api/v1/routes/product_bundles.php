<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/config/db.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';

require_once API_VERSION_PATH . '/models/products/repositories/PdoProductBundlesRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductBundlesValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductBundlesService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductBundlesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// هنا نستخدم الكلاسات مباشرة (بدون namespace) لأنها معرفة في النطاق العام
$repo       = new PdoProductBundlesRepository($pdo);
$validator  = new ProductBundlesValidator(); // الآن بدون namespace
$service    = new ProductBundlesService($repo, $validator);
$controller = new ProductBundlesController($service);

try {
    $tenantId = $_GET['tenant_id'] ?? $_SESSION['tenant_id'] ?? null;
    if (!$tenantId || !is_numeric($tenantId)) {
        ResponseFormatter::error('Unauthorized: tenant not found', 401);
        exit;
    }
    $tenantId = (int)$tenantId;

    // استخراج id من URI
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
            if ($id) {
                $lang = $_GET['lang'] ?? 'ar';
                $bundle = $controller->get($tenantId, $id, $lang);
                if ($bundle) {
                    ResponseFormatter::success($bundle);
                } else {
                    ResponseFormatter::error('Bundle not found', 404);
                }
            } else {
                $filters = [];
                $allowedFilters = ['entity_id', 'is_active', 'bundle_name', 'bundle_name_ar'];
                foreach ($allowedFilters as $key) {
                    if (isset($_GET[$key]) && $_GET[$key] !== '') {
                        $filters[$key] = $_GET[$key];
                    }
                }

                $page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit    = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
                $offset   = ($page - 1) * $limit;
                $orderBy  = $_GET['order_by'] ?? 'id';
                $orderDir = $_GET['order_dir'] ?? 'DESC';
                $lang     = $_GET['lang'] ?? 'ar';

                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                $total  = $controller->count($tenantId, $filters);

                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta' => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0
                    ]
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Bundle created successfully', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data['id'])) {
                ResponseFormatter::error('Bundle ID is required for update', 422);
                break;
            }
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Bundle updated successfully');
            break;

        case 'DELETE':
            $deleteId = null;
            if ($id) {
                $deleteId = $id;
            } else {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $deleteId = $input['id'] ?? null;
            }
            if (!$deleteId || !is_numeric($deleteId)) {
                ResponseFormatter::error('Bundle ID is required for deletion', 422);
                break;
            }
            $deleted = $controller->delete($tenantId, (int)$deleteId);
            ResponseFormatter::success(['deleted' => $deleted], 'Bundle deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('error', 'Validation error in ProductBundles', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (PDOException $e) {
    safe_log('error', 'Database error in ProductBundles', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    safe_log('error', 'ProductBundles route failed', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}
