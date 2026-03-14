<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/products/repositories/PdoProductQuestionsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductQuestionsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductQuestionsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductQuestionsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$repo       = new PdoProductQuestionsRepository($pdo);
$validator  = new ProductQuestionsValidator();
$service    = new ProductQuestionsService($repo, $validator);
$controller = new ProductQuestionsController($service);

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
            // مسار خاص: زيادة helpful_count
            if (isset($_GET['increment']) && $_GET['increment'] === 'helpful' && $id) {
                $updated = $controller->incrementHelpful($tenantId, $id);
                ResponseFormatter::success(['updated' => $updated], 'Helpful count incremented');
                break;
            }
            // مسار خاص: الحصول على أسئلة منتج معين
            elseif (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
                $filters['product_id'] = (int)$_GET['product_id'];
                $page     = $_GET['page'] ?? 1;
                $limit    = $_GET['limit'] ?? 20;
                $offset   = ($page - 1) * $limit;
                $orderBy  = $_GET['order_by'] ?? 'pq.created_at';
                $orderDir = $_GET['order_dir'] ?? 'DESC';
                $lang     = $_GET['lang'] ?? 'ar';

                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta' => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => ceil($result['total'] / $limit)
                    ]
                ]);
                break;
            }
            elseif ($id) {
                $lang = $_GET['lang'] ?? 'ar';
                $question = $controller->get($tenantId, $id, $lang);
                if ($question) {
                    ResponseFormatter::success($question);
                } else {
                    ResponseFormatter::error('Question not found', 404);
                }
                break;
            }
            else {
                // قائمة عامة مع فلترة
                $filters = [];
                $allowedFilters = ['product_id', 'user_id', 'is_approved'];
                foreach ($allowedFilters as $key) {
                    if (isset($_GET[$key]) && $_GET[$key] !== '') {
                        $filters[$key] = $_GET[$key];
                    }
                }

                $page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit    = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
                $offset   = ($page - 1) * $limit;
                $orderBy  = $_GET['order_by'] ?? 'pq.id';
                $orderDir = $_GET['order_dir'] ?? 'DESC';
                $lang     = $_GET['lang'] ?? 'ar';

                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                $total  = $result['total'];

                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta' => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0
                    ]
                ]);
                break;
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Question created successfully', 201);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($data['id'])) {
                ResponseFormatter::error('Question ID is required for update', 422);
                break;
            }
            $updated = $controller->update($tenantId, $data);
            ResponseFormatter::success(['updated' => $updated], 'Question updated successfully');
            break;

        case 'DELETE':
            if (isset($_GET['product_id']) && is_numeric($_GET['product_id'])) {
                $deleted = $controller->deleteByProduct($tenantId, (int)$_GET['product_id']);
                ResponseFormatter::success(['deleted' => $deleted], 'All questions for product deleted');
            } elseif ($id) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Question deleted successfully');
            } else {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                $deleteId = $input['id'] ?? null;
                if ($deleteId && is_numeric($deleteId)) {
                    $deleted = $controller->delete($tenantId, (int)$deleteId);
                    ResponseFormatter::success(['deleted' => $deleted], 'Question deleted successfully');
                } else {
                    ResponseFormatter::error('Missing id or product_id', 400);
                }
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('error', 'Validation error in ProductQuestions', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (PDOException $e) {
    safe_log('error', 'Database error in ProductQuestions', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    safe_log('error', 'ProductQuestions route failed', ['error' => $e->getMessage()]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}
