<?php
declare(strict_types=1);

 $baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

 $modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntitiesAttributesRepository.php';
require_once $modelsPath . '/validators/EntitiesAttributesValidator.php';
require_once $modelsPath . '/services/EntitiesAttributesService.php';
require_once $modelsPath . '/controllers/EntitiesAttributesController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

 $repo = new PdoEntitiesAttributesRepository($pdo);
 $service = new EntitiesAttributesService($repo);
 $controller = new EntitiesAttributesController($service);

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'sort_order';
    $orderDir = $_GET['order_dir'] ?? 'ASC';
    $lang    = $_GET['lang'] ?? 'ar';

    // Collect filters
    $filters = [];
    
    // فلتر slug
    if (isset($_GET['slug']) && !empty(trim($_GET['slug']))) {
        $filters['slug'] = trim($_GET['slug']);
    }
    
    // فلتر attribute_type
    if (isset($_GET['attribute_type']) && in_array($_GET['attribute_type'], ['text', 'number', 'select', 'boolean'])) {
        $filters['attribute_type'] = $_GET['attribute_type'];
    }
    
    // فلتر is_required
    if (isset($_GET['is_required']) && in_array((int)$_GET['is_required'], [0, 1], true)) {
        $filters['is_required'] = (int)$_GET['is_required'];
    }
    
    // فلتر name (بحث في الترجمة)
    if (isset($_GET['name']) && !empty(trim($_GET['name']))) {
        $filters['name'] = trim($_GET['name']);
    }
    
    // فلتر description (بحث في الترجمة)
    if (isset($_GET['description']) && !empty(trim($_GET['description']))) {
        $filters['description'] = trim($_GET['description']);
    }
    
    // فلتر language_code
    if (isset($_GET['language_code']) && !empty(trim($_GET['language_code']))) {
        $filters['language_code'] = trim($_GET['language_code']);
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // GET /api/entities_attributes/languages - الحصول على اللغات المتاحة
            if (isset($_GET['action']) && $_GET['action'] === 'languages') {
                $languages = $controller->getLanguages();
                ResponseFormatter::success($languages);
                exit;
            }
            
            // GET /api/entities_attributes/{id}/translations - الحصول على ترجمات الخاصية
            if (isset($_GET['action']) && $_GET['action'] === 'translations' && isset($_GET['id'])) {
                $translations = $controller->getTranslations((int)$_GET['id']);
                ResponseFormatter::success($translations);
                exit;
            }
            
            // GET /api/entities_attributes/{id} - الحصول على خاصية محددة
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id'], $lang);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Attribute not found', 404);
                }
            } 
            // GET /api/entities_attributes?slug={slug} - الحصول على خاصية بواسطة slug
            elseif (isset($_GET['slug']) && !empty($_GET['slug'])) {
                $item = $controller->getBySlug($_GET['slug'], $lang);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Attribute not found', 404);
                }
            }
            // GET /api/entities_attributes - الحصول على القائمة
            else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
                $total = $result['meta']['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
                        'filters'     => $filters,
                        'lang'        => $lang
                    ]
                ]);
            }
            break;

        case 'POST':
            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id']) || !is_numeric($data['id'])) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            
            $controller->update((int)$data['id'], $data);
            ResponseFormatter::success(['id' => (int)$data['id']], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id']) || !is_numeric($data['id'])) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            
            $controller->delete((int)$data['id']);
            ResponseFormatter::success(null, 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','entities_attributes.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','entities_attributes.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical','entities_attributes.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error', 500);
}