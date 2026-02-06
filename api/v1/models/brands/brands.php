<?php
declare(strict_types=1);

// api/routes/brands.php

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

// ===== تحميل ملفات brands =====
require_once API_VERSION_PATH . '/models/brands/repositories/PdoBrandsRepository.php';
require_once API_VERSION_PATH . '/models/brands/validators/BrandsValidator.php';
require_once API_VERSION_PATH . '/models/brands/services/BrandsService.php';
require_once API_VERSION_PATH . '/models/brands/controllers/BrandsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// استخدم tenantId ثابت مؤقتًا
$tenantId = 1;

// إنشاء الاعتمادات
$repo      = new PdoBrandsRepository($pdo);
$validator = new BrandsValidator();
$service   = new BrandsService($repo, $validator);
$controller = new BrandsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /brands/active
    if ($method === 'GET' && str_contains($uri, '/brands/active')) {
        ResponseFormatter::success(
            $controller->getActive($tenantId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/brands/featured')) {
        ResponseFormatter::success(
            $controller->getFeatured($tenantId)
        );
    } elseif ($method === 'GET') {
        $slug = $_GET['slug'] ?? null;
        if ($slug) {
            ResponseFormatter::success(
                $controller->get($tenantId, $slug)
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
    safe_log('error', 'Brands route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}