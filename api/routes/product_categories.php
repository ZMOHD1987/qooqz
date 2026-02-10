<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

$baseDir = dirname(__DIR__);

// ===== Bootstrap =====
require_once $baseDir . '/bootstrap.php';

// ===== Core =====
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/products/repositories/PdoProduct_categoriesRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/Product_categoriesValidator.php';
require_once API_VERSION_PATH . '/models/products/services/Product_categoriesService.php';
require_once API_VERSION_PATH . '/models/products/controllers/Product_categoriesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// Controller setup
$repo       = new PdoProduct_categoriesRepository($pdo);
$validator  = new Product_categoriesValidator();
$service    = new Product_categoriesService($repo, $validator);
$controller = new Product_categoriesController($service);

// ===== Extract ID from URI =====
$uriParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$idFromUri = null;
if (isset($uriParts[count($uriParts)-1]) && is_numeric($uriParts[count($uriParts)-1])) {
    $idFromUri = (int)$uriParts[count($uriParts)-1];
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($idFromUri !== null) {
                ResponseFormatter::success($controller->get($idFromUri));
            } else {
                $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
                $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
                $filters = [
                    'product_id'  => $_GET['product_id'] ?? null,
                    'category_id' => $_GET['category_id'] ?? null,
                    'is_primary'  => isset($_GET['is_primary']) ? (int)$_GET['is_primary'] : null
                ];
                $orderBy  = $_GET['order_by'] ?? 'id';
                $orderDir = $_GET['order_dir'] ?? 'DESC';

                $data = [
                    'items' => $controller->list($limit, $offset, $filters, $orderBy, $orderDir),
                    'total' => $controller->count($filters)
                ];
                ResponseFormatter::success($data);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ResponseFormatter::success($controller->create($data));
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ResponseFormatter::success($controller->update($data));
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $controller->delete($data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('error', 'Product_categories route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
