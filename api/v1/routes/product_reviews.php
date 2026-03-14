<?php
declare(strict_types=1);

ini_set('display_errors', '0'); // لا تعرض الأخطاء
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir.'/bootstrap.php';
require_once $baseDir.'/shared/core/ResponseFormatter.php';
require_once $baseDir.'/shared/config/db.php';
require_once $baseDir.'/shared/helpers/safe_helpers.php';

require_once API_VERSION_PATH . '/models/products/repositories/PdoProductReviewsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductReviewsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductReviewsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductReviewsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$repo       = new PdoProductReviewsRepository($pdo);
$validator  = new ProductReviewsValidator();
$service    = new ProductReviewsService($repo);
$controller = new ProductReviewsController($service, $validator);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawData = file_get_contents('php://input');
    $data = $rawData ? json_decode($rawData, true) : [];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $item = $controller->get((int)$_GET['id']);
                ResponseFormatter::success($item);
            } else {
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = min(1000, max(1, (int)($_GET['limit'] ?? 25)));
                $offset = ($page - 1) * $limit;

                $filters = [];
                foreach (['product_id','user_id','is_verified_purchase','is_approved'] as $f) {
                    if (isset($_GET[$f])) $filters[$f] = $_GET[$f];
                }

                $orderBy = $_GET['order_by'] ?? 'created_at';
                $orderDir = $_GET['order_dir'] ?? 'DESC';

                $result = $controller->list($filters, $limit, $offset, $orderBy, $orderDir);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'Created successfully');
            break;

        case 'PUT':
            $id = $controller->update($data);
            ResponseFormatter::success(['id' => $id], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('ID required for deletion', 422);
            }
            $deleted = $controller->delete((int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (\Throwable $e) {
    safe_log('error', 'ProductReviews API failed', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
