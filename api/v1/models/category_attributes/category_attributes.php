<?php
declare(strict_types=1);

// api/routes/category_attributes.php

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

// ===== تحميل ملفات category_attributes =====
require_once API_VERSION_PATH . '/models/category_attributes/repositories/PdoCategoryAttributesRepository.php';
require_once API_VERSION_PATH . '/models/category_attributes/validators/CategoryAttributesValidator.php';
require_once API_VERSION_PATH . '/models/category_attributes/services/CategoryAttributesService.php';
require_once API_VERSION_PATH . '/models/category_attributes/controllers/CategoryAttributesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// استخدم tenantId ثابت مؤقتًا
$tenantId = 1;

// إنشاء الاعتمادات
$repo      = new PdoCategoryAttributesRepository($pdo);
$validator = new CategoryAttributesValidator();
$service   = new CategoryAttributesService($repo, $validator);
$controller = new CategoryAttributesController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /category_attributes/by_category?category_id=1
    if ($method === 'GET' && str_contains($uri, '/category_attributes/by_category')) {
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Valid category_id is required');
        }
        ResponseFormatter::success(
            $controller->getByCategory($tenantId, $categoryId)
        );
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
    safe_log('error', 'Category attributes route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}