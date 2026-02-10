<?php
declare(strict_types=1);

 $baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

 $modelsPath = API_VERSION_PATH . '/models/entities';
require_once $modelsPath . '/repositories/PdoEntitySettingsRepository.php';
require_once $modelsPath . '/services/EntitySettingsService.php';
require_once $modelsPath . '/controllers/EntitySettingsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

 $repo = new PdoEntitySettingsRepository($pdo);
 $service = new EntitySettingsService($repo);
 $controller = new EntitySettingsController($service);

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
    $orderBy = $_GET['order_by'] ?? 'entity_id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters - جمع جميع الفلاتر الممكنة
    $filters = [];
    
    // فلتر entity_id
    if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
        $filters['entity_id'] = (int)$_GET['entity_id'];
    }
    
    // الفلاتر المنطقية (Boolean)
    $booleanFilters = ['auto_accept_orders', 'allow_cod', 'allow_online_booking', 
                       'booking_cancellation_allowed', 'allow_preorders', 'is_visible', 
                       'maintenance_mode', 'show_reviews', 'show_contact_info', 
                       'featured_in_app', 'allow_multiple_payment_methods'];
    
    foreach ($booleanFilters as $filter) {
        if (isset($_GET[$filter]) && in_array((int)$_GET[$filter], [0, 1], true)) {
            $filters[$filter] = (int)$_GET[$filter];
        }
    }
    
    // الفلاتر الرقمية
    $numericFilters = ['min_order_amount', 'preparation_time_minutes', 'booking_window_days',
                       'max_bookings_per_slot', 'max_daily_orders', 'delivery_radius_km', 
                       'free_delivery_min_order'];
    
    foreach ($numericFilters as $filter) {
        if (isset($_GET[$filter]) && is_numeric($_GET[$filter])) {
            $filters[$filter] = $_GET[$filter];
        }
    }
    
    // فلتر طريقة الدفع الافتراضية
    if (isset($_GET['default_payment_method']) && is_string($_GET['default_payment_method'])) {
        $filters['default_payment_method'] = $_GET['default_payment_method'];
    }
    
    // فلتر اسم المتجر (بحث جزئي)
    if (isset($_GET['store_name']) && !empty(trim($_GET['store_name']))) {
        $filters['store_name'] = trim($_GET['store_name']);
    }
    
    // فلتر حالة الكيان
    if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'suspended', 'rejected'])) {
        $filters['status'] = $_GET['status'];
    }
    
    // فلتر بحث في الإعدادات الإضافية
    if (isset($_GET['additional_settings_search']) && !empty(trim($_GET['additional_settings_search']))) {
        $filters['additional_settings_search'] = trim($_GET['additional_settings_search']);
    }

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // التحقق إذا كان طلب عنصر واحد
            if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id']) && !isset($_GET['page'])) {
                $item = $controller->get((int)$_GET['entity_id']);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    // Return default settings when none exist yet for this entity
                    ResponseFormatter::success([
                        'entity_id' => (int)$_GET['entity_id'],
                        'auto_accept_orders' => 0,
                        'allow_cod' => 0,
                        'min_order_amount' => 0,
                        'allow_online_booking' => 0,
                        'booking_window_days' => 0,
                        'max_bookings_per_slot' => 0,
                        'show_reviews' => 1,
                        'show_contact_info' => 1,
                        'featured_in_app' => 0
                    ]);
                }
            } else {
                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir);
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
                        'filters'     => $filters // إضافة الفلاتر المستخدمة في الاستجابة
                    ]
                ]);
            }
            break;

        case 'POST':
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                ResponseFormatter::error('Entity ID is required', 400);
                exit;
            }
            
            $entityId = (int)$data['entity_id'];
            unset($data['entity_id']);
            
            $created = $controller->create($entityId, $data);
            ResponseFormatter::success(['id' => $entityId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                ResponseFormatter::error('Entity ID is required', 400);
                exit;
            }
            
            $entityId = (int)$data['entity_id'];
            unset($data['entity_id']);
            
            $updated = $controller->update($entityId, $data);
            ResponseFormatter::success(['id' => $entityId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                ResponseFormatter::error('Entity ID is required', 400);
                exit;
            }
            
            $deleted = $controller->delete((int)$data['entity_id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','entity_settings.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','entity_settings.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical','entity_settings.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error('Internal Server Error', 500);
}