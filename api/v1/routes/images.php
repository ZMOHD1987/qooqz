<?php
declare(strict_types=1);

// ===============================
// api/routes/images.php
// ===============================

$baseDir = dirname(__DIR__, 2);

require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/images/repositories/PdoImagesRepository.php';
require_once API_VERSION_PATH . '/models/images/validators/ImagesValidator.php';
require_once API_VERSION_PATH . '/models/images/services/ImagesService.php';
require_once API_VERSION_PATH . '/models/images/controllers/ImagesController.php';

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// Tenant/User context
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;
$userId   = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

// Init layers
$repo       = new PdoImagesRepository($pdo);
$validator  = new ImagesValidator();
$service    = new ImagesService($repo, $validator, $pdo);
$controller = new ImagesController($service);

// ===============================
// Helper: Read Request Data
// ===============================
function getRequestData(): array {
    $data = [];

    // 1. $_POST
    if (!empty($_POST)) {
        $data = $_POST;
        unset($data['_method']);
    }

    // 2. JSON body
    $json = file_get_contents('php://input');
    if ($json) {
        $jsonData = json_decode($json, true);
        if (is_array($jsonData)) {
            $data = array_merge($data, $jsonData);
        }
    }

    // 3. Uploaded files
    if (!empty($_FILES) && isset($_FILES['images'])) {
        $data['images'] = $_FILES['images'];
    }

    // 4. Cast numeric fields
    $numericFields = ['id', 'owner_id', 'image_type_id', 'tenant_id', 'user_id', 'is_main', 'sort_order', 'size'];
    foreach ($numericFields as $f) {
        if (isset($data[$f])) {
            $data[$f] = is_numeric($data[$f]) ? (int)$data[$f] : null;
        }
    }

    // 5. Trim strings
    $stringFields = ['url', 'filename', 'thumb_url', 'mime_type', 'visibility', 'entity', 'q'];
    foreach ($stringFields as $f) {
        if (isset($data[$f])) {
            $data[$f] = trim((string)$data[$f]);
            if ($data[$f] === '') {
                $data[$f] = null;
            }
        }
    }

    return $data;
}

// ===============================
// Routing
// ===============================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    
    // Handle _method parameter for RESTful API
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }
    
    // Remove /api prefix if exists
    $uri = preg_replace('#^/api#', '', $uri);
    $uri = '/' . trim($uri, '/');

    // 🔴 **GET /images/types - الحصول على أنواع الصور**
    if ($method === 'GET' && $uri === '/images/types') {
        ResponseFormatter::success($controller->getImageTypes());
    }
    
    // 🔴 **GET /images/by_owner - الحصول على صور مالك معين**
    elseif ($method === 'GET' && $uri === '/images/by_owner') {
        $ownerId     = (int)($_GET['owner_id'] ?? 0);
        $imageTypeId = (int)($_GET['image_type_id'] ?? 0);
        if ($ownerId <= 0 || $imageTypeId <= 0) {
            throw new InvalidArgumentException('owner_id and image_type_id are required');
        }
        ResponseFormatter::success($controller->getByOwner($tenantId, $ownerId, $imageTypeId));
    }
    
    // 🔴 **GET /images/main - الحصول على الصورة الرئيسية**
    elseif ($method === 'GET' && $uri === '/images/main') {
        $ownerId     = (int)($_GET['owner_id'] ?? 0);
        $imageTypeId = (int)($_GET['image_type_id'] ?? 0);
        if ($ownerId <= 0 || $imageTypeId <= 0) {
            throw new InvalidArgumentException('owner_id and image_type_id are required');
        }
        ResponseFormatter::success($controller->getMain($tenantId, $ownerId, $imageTypeId));
    }
    
    // 🔴 **GET /images/{id} - الحصول على صورة واحدة**
    elseif ($method === 'GET' && preg_match('#^/images/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        ResponseFormatter::success($controller->get($tenantId, $id));
    }
    
    // 🔴 **DELETE /images/by_owner - حذف جميع صور مالك معين**
    elseif ($method === 'DELETE' && $uri === '/images/by_owner') {
        $ownerId     = (int)($_GET['owner_id'] ?? 0);
        $imageTypeId = (int)($_GET['image_type_id'] ?? 0);
        if ($ownerId <= 0 || $imageTypeId <= 0) {
            throw new InvalidArgumentException('owner_id and image_type_id are required');
        }
        ResponseFormatter::success($controller->deleteByOwner($tenantId, $ownerId, $imageTypeId, $userId));
    }
    
    // 🔴 **DELETE /images/multiple - حذف صور متعددة**
    elseif ($method === 'DELETE' && $uri === '/images/multiple') {
        $data = getRequestData();
        ResponseFormatter::success($controller->deleteMultiple($tenantId, $data));
    }
    
    // 🔴 **DELETE /images/{id} - حذف صورة واحدة**
    elseif ($method === 'DELETE' && preg_match('#^/images/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $id;
        ResponseFormatter::success($controller->delete($tenantId, $data));
    }
    
    // 🔴 **POST /images/set_main - تعيين صورة كرئيسية**
    elseif ($method === 'POST' && $uri === '/images/set_main') {
        $data = getRequestData();
        ResponseFormatter::success($controller->setMain($tenantId, $data));
    }
    
    // 🔴 **POST /images/update_sort - تحديث ترتيب الصور**
    elseif ($method === 'POST' && $uri === '/images/update_sort') {
        $data = getRequestData();
        ResponseFormatter::success($controller->updateSortOrder($tenantId, $data));
    }
    
    // 🔴 **POST /images/update_visibility - تحديث رؤية الصور**
    elseif ($method === 'POST' && $uri === '/images/update_visibility') {
        $data = getRequestData();
        ResponseFormatter::success($controller->updateVisibility($tenantId, $data));
    }
    
    // 🔴 **POST /images (upload or create) - رفع أو إنشاء صور**
    elseif ($method === 'POST' && $uri === '/images') {
        $data = getRequestData();
        
        // Check if this is an upload (has files or images array)
        if (!empty($data['images']) || !empty($_FILES['images'])) {
            $files = !empty($_FILES['images']) ? $_FILES['images'] : $data['images'];
            ResponseFormatter::success($controller->upload($tenantId, $data, $files, $userId));
        } else {
            ResponseFormatter::success($controller->create($tenantId, $data));
        }
    }
    
    // 🔴 **PUT /images/{id} - تحديث صورة واحدة**
    elseif (($method === 'PUT' || ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT')) 
            && preg_match('#^/images/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        $data = getRequestData();
        $data['id'] = $id;
        ResponseFormatter::success($controller->update($tenantId, $data));
    }
    
    // 🔴 **GET /images - قائمة الصور مع الفلترة (هذا هو الـ default)**
    elseif ($method === 'GET' && ($uri === '/images' || $uri === '/images/')) {
        ResponseFormatter::success($controller->list($tenantId));
    }
    
    else {
        ResponseFormatter::error('Method not allowed or route not found: ' . $uri, 405);
    }
    
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('error', 'Images route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}
