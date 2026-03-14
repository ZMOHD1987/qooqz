<?php
declare(strict_types=1);

 $baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

 $modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntitiesAttributeValuesRepository.php';
require_once $modelsPath . '/validators/EntitiesAttributeValuesValidator.php';
require_once $modelsPath . '/services/EntitiesAttributeValuesService.php';
require_once $modelsPath . '/controllers/EntitiesAttributeValuesController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

 $repo = new PdoEntitiesAttributeValuesRepository($pdo);
 $service = new EntitiesAttributeValuesService($repo);
 $controller = new EntitiesAttributeValuesController($service);

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
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';
    $lang    = $_GET['lang'] ?? 'ar';

    // Collect filters
    $filters = [];
    
    // فلتر entity_id
    if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
        $filters['entity_id'] = (int)$_GET['entity_id'];
    }
    
    // فلتر attribute_id
    if (isset($_GET['attribute_id']) && is_numeric($_GET['attribute_id'])) {
        $filters['attribute_id'] = (int)$_GET['attribute_id'];
    }
    
    // فلتر value (بحث في القيمة)
    if (isset($_GET['value']) && !empty(trim($_GET['value']))) {
        $filters['value'] = trim($_GET['value']);
    }
    
    // فلتر store_name (بحث في اسم المتجر)
    if (isset($_GET['store_name']) && !empty(trim($_GET['store_name']))) {
        $filters['store_name'] = trim($_GET['store_name']);
    }
    
    // فلتر entity_status
    if (isset($_GET['entity_status']) && in_array($_GET['entity_status'], ['pending', 'approved', 'suspended', 'rejected'])) {
        $filters['entity_status'] = $_GET['entity_status'];
    }
    
    // فلتر attribute_type
    if (isset($_GET['attribute_type']) && in_array($_GET['attribute_type'], ['text', 'number', 'select', 'boolean'])) {
        $filters['attribute_type'] = $_GET['attribute_type'];
    }
    
    // فلتر attribute_slug
    if (isset($_GET['attribute_slug']) && !empty(trim($_GET['attribute_slug']))) {
        $filters['attribute_slug'] = trim($_GET['attribute_slug']);
    }
    
    // فلتر attribute_name (بحث في اسم الخاصية)
    if (isset($_GET['attribute_name']) && !empty(trim($_GET['attribute_name']))) {
        $filters['attribute_name'] = trim($_GET['attribute_name']);
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // GET /api/entities_attribute_values/statistics - الحصول على الإحصائيات
            if (isset($_GET['action']) && $_GET['action'] === 'statistics') {
                $statistics = $controller->getStatistics();
                ResponseFormatter::success($statistics);
                exit;
            }
            
            // GET /api/entities_attribute_values/entity/{entity_id} - الحصول على جميع قيم كيان
            if (isset($_GET['action']) && $_GET['action'] === 'entity' && isset($_GET['entity_id'])) {
                $values = $controller->getEntityValues((int)$_GET['entity_id'], $lang);
                ResponseFormatter::success($values);
                exit;
            }
            
            // GET /api/entities_attribute_values/attribute/{attribute_id} - الحصول على جميع قيم خاصية
            if (isset($_GET['action']) && $_GET['action'] === 'attribute' && isset($_GET['attribute_id'])) {
                $values = $controller->getAttributeValues((int)$_GET['attribute_id'], $lang);
                ResponseFormatter::success($values);
                exit;
            }
            
            // GET /api/entities_attribute_values/{id} - الحصول على قيمة محددة
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id'], $lang);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Attribute value not found', 404);
                }
            }
            // GET /api/entities_attribute_values - الحصول على القائمة
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
            // POST /api/entities_attribute_values/bulk/{entity_id} - حفظ جماعي لقيم كيان
            if (isset($_GET['action']) && $_GET['action'] === 'bulk' && isset($_GET['entity_id'])) {
                $entityId = (int)$_GET['entity_id'];
                $savedIds = $controller->saveEntityValues($entityId, $data);
                ResponseFormatter::success(['saved_ids' => $savedIds], 'Bulk values saved successfully', 201);
                exit;
            }
            
            // POST /api/entities_attribute_values - إنشاء قيمة جديدة
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
            // DELETE /api/entities_attribute_values/entity/{entity_id} - حذف جميع قيم كيان
            if ((isset($_GET['action']) && $_GET['action'] === 'entity' && isset($_GET['entity_id'])) || (isset($_GET['entity_id']) && !isset($_GET['id']))) {
                $controller->deleteEntityValues((int)$_GET['entity_id']);
                ResponseFormatter::success(null, 'All entity values deleted successfully');
                exit;
            }
            
            // DELETE /api/entities_attribute_values/attribute/{attribute_id} - حذف جميع قيم خاصية
            if (isset($_GET['action']) && $_GET['action'] === 'attribute' && isset($_GET['attribute_id'])) {
                $controller->deleteAttributeValues((int)$_GET['attribute_id']);
                ResponseFormatter::success(null, 'All attribute values deleted successfully');
                exit;
            }
            
            // DELETE /api/entities_attribute_values - حذف قيمة محددة
            $deleteId = null;
            if (!empty($data['id']) && is_numeric($data['id'])) {
                $deleteId = (int)$data['id'];
            } elseif (!empty($_GET['id']) && is_numeric($_GET['id'])) {
                $deleteId = (int)$_GET['id'];
            }
            
            if (!$deleteId) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            
            $controller->delete($deleteId);
            ResponseFormatter::success(null, 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','entities_attribute_values.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','entities_attribute_values.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log("Error in entities_attribute_values: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 3, __DIR__ . '/../../error_log.txt');
    safe_log('critical','entities_attribute_values.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
}

