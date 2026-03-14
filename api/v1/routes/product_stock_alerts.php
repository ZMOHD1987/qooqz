<?php
declare(strict_types=1);

// نسخة إنتاجية: لا تعرض الأخطاء للمستخدم
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir.'/bootstrap.php';
require_once $baseDir.'/shared/core/ResponseFormatter.php';
require_once $baseDir.'/shared/config/db.php';
require_once $baseDir.'/shared/helpers/safe_helpers.php';

require_once API_VERSION_PATH . '/models/products/repositories/PdoProductStockAlertsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductStockAlertsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductStockAlertsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductStockAlertsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ضبط PDO لرفع الاستثناءات داخليًا
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// إعداد الكائنات
$repo       = new PdoProductStockAlertsRepository($pdo);
$validator  = new ProductStockAlertsValidator();
$service    = new ProductStockAlertsService($repo);
$controller = new ProductStockAlertsController($service, $validator);

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
                foreach (['product_id','user_id'] as $f) {
                    if (isset($_GET[$f])) $filters[$f] = (int)$_GET[$f];
                }

                $orderBy = $_GET['order_by'] ?? 'id';
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
    // تسجيل الأخطاء داخليًا دون إظهار التفاصيل للمستخدم
    safe_log('error', 'ProductStockAlerts API failed', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
