<?php
declare(strict_types=1);

// تفعيل عرض الأخطاء للتطوير فقط
error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseDir = dirname(__DIR__);
require_once $baseDir.'/bootstrap.php';
require_once $baseDir.'/shared/core/ResponseFormatter.php';
require_once $baseDir.'/shared/config/db.php';
require_once $baseDir.'/shared/helpers/safe_helpers.php';

require_once API_VERSION_PATH . '/models/products/repositories/PdoProduct_physical_attributesRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/Product_physical_attributesValidator.php';
require_once API_VERSION_PATH . '/models/products/services/Product_physical_attributesService.php';
require_once API_VERSION_PATH . '/models/products/controllers/Product_physical_attributesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// تفعيل وضع الأخطاء في PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup
$repo       = new PdoProductPhysicalAttributesRepository($pdo);
$validator  = new ProductPhysicalAttributesValidator();
$service    = new ProductPhysicalAttributesService($repo, $validator);
$controller = new ProductPhysicalAttributesController($service);

try {
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments   = explode('/', trim($requestUri, '/'));

    $id = null;
    $type = null;

    // استخراج ID و type من URI
    foreach ($segments as $i => $seg) {
        if (ctype_digit($seg)) {
            $id = (int)$seg;
            if ($i > 0 && in_array(strtolower($segments[$i-1]), ['product','variant'], true)) {
                $type = strtolower($segments[$i-1]);
            }
            break;
        }
    }

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($id && $type) {
                $data = $type === 'variant' 
                    ? $controller->getByVariant($id) 
                    : $controller->getByProduct($id);
                ResponseFormatter::success($data);
            } else {
                // تنظيف الفلاتر
                $filters = [];
                $allowedFilters = ['product_id', 'variant_id', 'min_weight', 'max_weight', 'weight_unit', 'dimension_unit'];
                
                foreach ($allowedFilters as $key) {
                    if (isset($_GET[$key]) && $_GET[$key] !== '') {
                        $filters[$key] = $_GET[$key];
                    }
                }

                $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
                $orderBy  = $_GET['order_by'] ?? 'created_at';
                $orderDir = $_GET['order_dir'] ?? 'DESC';

                $data = [
                    'items' => $controller->list($limit, $offset, $filters, $orderBy, $orderDir),
                    'total' => $controller->count($filters),
                ];
                ResponseFormatter::success($data);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'Created successfully');
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = $controller->update($data);
            ResponseFormatter::success(['id' => $id], 'Updated successfully');
            break;

        case 'DELETE':
            if (!$id || !$type) {
                ResponseFormatter::error('ID and type (product/variant) are required for deletion', 422);
            } else {
                $deleted = $type === 'variant' 
                    ? $controller->deleteByVariant($id)
                    : $controller->deleteByProduct($id);
                ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('error', 'Validation error in ProductPhysicalAttributes', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 422);
    
} catch (PDOException $e) {
    safe_log('error', 'Database error in ProductPhysicalAttributes route', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    // في وضع التطوير فقط - أظهر التفاصيل
    ResponseFormatter::error('Database error: ' . $e->getMessage(), 500);
    
} catch (Throwable $e) {
    safe_log('error', 'ProductPhysicalAttributes route failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}