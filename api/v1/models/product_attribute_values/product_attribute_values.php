<?php
declare(strict_types=1);

// api/routes/product_attribute_values.php

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

// ===== تحميل ملفات product_attribute_values =====
require_once API_VERSION_PATH . '/models/product_attribute_values/repositories/PdoProductAttributeValuesRepository.php';
require_once API_VERSION_PATH . '/models/product_attribute_values/validators/ProductAttributeValuesValidator.php';
require_once API_VERSION_PATH . '/models/product_attribute_values/services/ProductAttributeValuesService.php';
require_once API_VERSION_PATH . '/models/product_attribute_values/controllers/ProductAttributeValuesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// إنشاء الاعتمادات
$repo      = new PdoProductAttributeValuesRepository($pdo);
$validator = new ProductAttributeValuesValidator();
$service   = new ProductAttributeValuesService($repo, $validator);
$controller = new ProductAttributeValuesController($service);

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
    safe_log('error', 'Product attribute values route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}