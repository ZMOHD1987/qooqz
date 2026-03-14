<?php
declare(strict_types=1);

// api/routes/attribute_types.php

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

// ===== تحميل ملفات attribute_types =====
require_once API_VERSION_PATH . '/models/attribute_types/repositories/PdoAttributeTypesRepository.php';
require_once API_VERSION_PATH . '/models/attribute_types/validators/AttributeTypesValidator.php';
require_once API_VERSION_PATH . '/models/attribute_types/services/AttributeTypesService.php';
require_once API_VERSION_PATH . '/models/attribute_types/controllers/AttributeTypesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// إنشاء الاعتمادات
$repo      = new PdoAttributeTypesRepository($pdo);
$validator = new AttributeTypesValidator();
$service   = new AttributeTypesService($repo, $validator);
$controller = new AttributeTypesController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /attribute_types/active
    if ($method === 'GET' && str_contains($uri, '/attribute_types/active')) {
        ResponseFormatter::success(
            $controller->getActive()
        );
    } elseif ($method === 'GET') {
        $code = $_GET['code'] ?? null;
        if ($code) {
            ResponseFormatter::success(
                $controller->get($code)
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
    safe_log('error', 'Attribute types route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}