<?php
declare(strict_types=1);

// api/routes/design_settings.php

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

// ===== تحميل ملفات design_settings =====
require_once API_VERSION_PATH . '/models/design_settings/repositories/PdoDesignSettingsRepository.php';
require_once API_VERSION_PATH . '/models/design_settings/validators/DesignSettingsValidator.php';
require_once API_VERSION_PATH . '/models/design_settings/services/DesignSettingsService.php';
require_once API_VERSION_PATH . '/models/design_settings/controllers/DesignSettingsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// استخدم tenantId ثابت مؤقتًا
$tenantId = 1;

// إنشاء الاعتمادات
$repo      = new PdoDesignSettingsRepository($pdo);
$validator = new DesignSettingsValidator();
$service   = new DesignSettingsService($repo, $validator);
$controller = new DesignSettingsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /design_settings/active
    if ($method === 'GET' && str_contains($uri, '/design_settings/active')) {
        ResponseFormatter::success(
            $controller->getActive($tenantId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/design_settings/categories')) {
        ResponseFormatter::success(
            $controller->categories($tenantId)
        );
    } elseif ($method === 'GET') {
        $key = $_GET['key'] ?? null;
        if ($key) {
            ResponseFormatter::success(
                $controller->get($tenantId, $key)
            );
        } else {
            ResponseFormatter::success(
                $controller->list($tenantId)
            );
        }
    } elseif ($method === 'POST' && str_contains($uri, '/design_settings/bulk')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->bulkUpdate($tenantId, $data)
        );
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
    safe_log('error', 'Design settings route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}