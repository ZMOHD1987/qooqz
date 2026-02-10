<?php
declare(strict_types=1);

/**
 * api/routes/product_types.php
 * Production Ready REST Route
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

$baseDir = dirname(__DIR__);

// ===== Bootstrap =====
require_once $baseDir . '/bootstrap.php';

// ===== Core =====
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/config/db.php';

// ===== MVC =====
require_once API_VERSION_PATH . '/models/products/repositories/PdoProduct_typesRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/Product_typesValidator.php';
require_once API_VERSION_PATH . '/models/products/services/Product_typesService.php';
require_once API_VERSION_PATH . '/models/products/controllers/Product_typesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// ===== Init =====
$repo       = new PdoProduct_typesRepository($pdo);
$validator  = new Product_typesValidator();
$service    = new Product_typesService($repo, $validator);
$controller = new Product_typesController($service);

// ===== Parse URI =====
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// مثال:
// /api/product_types
// /api/product_types/5
$segments = array_values(array_filter(explode('/', $uri)));
$id = null;

// حاول استخراج ID من آخر segment
if (!empty($segments)) {
    $last = end($segments);
    if (is_numeric($last)) {
        $id = (int)$last;
    }
}

// ===== Body =====
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?? [];

try {

    switch ($method) {

        /* =====================================================
         * GET
         * ===================================================== */
        case 'GET':

            // GET /product_types/{id}
            if ($id !== null) {
                ResponseFormatter::success(
                    $controller->get($id)
                );
                break;
            }

            // GET /product_types (with filters)
            $filters = [
                'search'    => $_GET['search']    ?? null,
                'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
            ];

            $limit     = isset($_GET['limit'])  ? (int)$_GET['limit']  : null;
            $offset    = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
            $orderBy   = $_GET['order_by']  ?? 'id';
            $orderDir  = $_GET['order_dir'] ?? 'DESC';

            ResponseFormatter::success([
                'data'  => $service->list($limit, $offset, $filters, $orderBy, $orderDir),
                'total' => $service->count($filters),
            ]);
            break;

        /* =====================================================
         * POST  (Create)
         * ===================================================== */
        case 'POST':
            ResponseFormatter::success(
                $controller->create($body)
            );
            break;

        /* =====================================================
         * PUT  (Update)
         * ===================================================== */
        case 'PUT':
            if ($id !== null && empty($body['id'])) {
                $body['id'] = $id; // دعم PUT /product_types/5
            }

            ResponseFormatter::success(
                $controller->update($body)
            );
            break;

        /* =====================================================
         * DELETE
         * ===================================================== */
        case 'DELETE':
            if ($id !== null && empty($body['id'])) {
                $body['id'] = $id; // دعم DELETE /product_types/5
            }

            $controller->delete($body);
            ResponseFormatter::success(['deleted' => true]);
            break;

        /* =====================================================
         * METHOD NOT ALLOWED
         * ===================================================== */
        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);

} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);

} catch (Throwable $e) {
    safe_log('error', 'product_types route error', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
