<?php
declare(strict_types=1);

// api/routes/product_attribute_translations.php

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

// ===== تحميل ملفات products =====
require_once API_VERSION_PATH . '/models/products/repositories/PdoProductAttributeTranslationsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductAttributeTranslationsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductAttributeTranslationsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductAttributeTranslationsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// إنشاء الاعتمادات
$repo      = new PdoProductAttributeTranslationsRepository($pdo);
$validator = new ProductAttributeTranslationsValidator();
$service   = new ProductAttributeTranslationsService($repo, $validator);
$controller = new ProductAttributeTranslationsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            ResponseFormatter::success(
                $controller->get($id)
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
    safe_log('error', 'Product attribute translations route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}