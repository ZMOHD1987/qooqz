<?php
declare(strict_types=1);

// api/routes/product_attributes.php

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

// ===== تحميل مproductss =====
require_once API_VERSION_PATH . '/models/products/repositories/PdoProductAttributesRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductAttributesValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductAttributesService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductAttributesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// إنشاء الاعتمادات
$repo      = new PdoProductAttributesRepository($pdo);
$validator = new ProductAttributesValidator();
$service   = new ProductAttributesService($repo, $validator);
$controller = new ProductAttributesController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $slug = $_GET['slug'] ?? null;
        if ($slug) {
            ResponseFormatter::success(
                $controller->get($slug)
            );
        } else {
            ResponseFormatter::success(
                $controller->list()
            );
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->create($data)
        );
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->update($data)
        );
    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller->delete($data);
        ResponseFormatter::success(['deleted' => true]);
    } else {
        ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('error', 'Product attributes route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}