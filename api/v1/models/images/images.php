<?php
declare(strict_types=1);

// api/routes/images.php

// ===== مسار api =====
$baseDir = dirname(__DIR__);

// ===== تحميل bootstrap =====
require_once $baseDir . '/bootstrap.php';

// ===== تحميل ResponseFormatter =====
require_once $baseDir . '/shared/core/ResponseFormatter.php';

// ===== تحميل safe_helpers =====
require_once $baseDir . '/shared/helpers/safe_helpers.php';

// ===== تحميل قاعدة البيانات =====
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات images =====
require_once API_VERSION_PATH . '/models/images/repositories/PdoImagesRepository.php';
require_once API_VERSION_PATH . '/models/images/validators/ImagesValidator.php';
require_once API_VERSION_PATH . '/models/images/services/ImagesService.php';
require_once API_VERSION_PATH . '/models/images/controllers/ImagesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// استخدم tenantId ثابت مؤقتًا
$tenantId = 1;

// إنشاء الاعتمادات
$repo      = new PdoImagesRepository($pdo);
$validator = new ImagesValidator();
$service   = new ImagesService($repo, $validator);
$controller = new ImagesController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /images/by_owner?owner_id=123&image_type_id=1
    if ($method === 'GET' && str_contains($uri, '/images/by_owner')) {
        $ownerId = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
        $imageTypeId = isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : 0;
        if ($ownerId <= 0 || $imageTypeId <= 0) {
            throw new InvalidArgumentException('Valid owner_id and image_type_id are required');
        }
        ResponseFormatter::success(
            $controller->getByOwner($tenantId, $ownerId, $imageTypeId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/images/main')) {
        $ownerId = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
        $imageTypeId = isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : 0;
        if ($ownerId <= 0 || $imageTypeId <= 0) {
            throw new InvalidArgumentException('Valid owner_id and image_type_id are required');
        }
        $mainImage = $controller->getMain($tenantId, $ownerId, $imageTypeId);
        ResponseFormatter::success($mainImage ?: ['message' => 'No main image found']);
    } elseif ($method === 'POST' && str_contains($uri, '/images/set_main')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->setMain($tenantId, $data)
        );
    } elseif ($method === 'DELETE' && str_contains($uri, '/images/by_owner')) {
        $ownerId = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
        $imageTypeId = isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : 0;
        if ($ownerId <= 0 || $imageTypeId <= 0) {
            throw new InvalidArgumentException('Valid owner_id and image_type_id are required');
        }
        $controller->deleteByOwner($tenantId, $ownerId, $imageTypeId);
        ResponseFormatter::success(['deleted' => true]);
    } elseif ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            ResponseFormatter::success(
                $controller->get($tenantId, $id)
            );
        } else {
            ResponseFormatter::success(
                $controller->list($tenantId)
            );
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->create($tenantId, $data)
        );
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->update($tenantId, $data)
        );
    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller->delete($tenantId, $data);
        ResponseFormatter::success(['deleted' => true]);
    } else {
        ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('error', 'Images route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}