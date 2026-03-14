<?php
declare(strict_types=1);

// api/routes/system_settings.php

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

// ===== تحميل ملفات system_settings =====
require_once API_VERSION_PATH . '/models/system_settings/repositories/PdoSystemSettingsRepository.php';
require_once API_VERSION_PATH . '/models/system_settings/validators/SystemSettingsValidator.php';
require_once API_VERSION_PATH . '/models/system_settings/services/SystemSettingsService.php';
require_once API_VERSION_PATH . '/models/system_settings/controllers/SystemSettingsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// استخدم tenantId ثابت مؤقتًا
$tenantId = 1;

// إنشاء الاعتمادات
$repo      = new PdoSystemSettingsRepository($pdo);
$validator = new SystemSettingsValidator();
$service   = new SystemSettingsService($repo, $validator);
$controller = new SystemSettingsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /system_settings?category=general
    if ($method === 'GET' && str_contains($uri, '/system_settings/public')) {
        ResponseFormatter::success(
            $controller->getPublic($tenantId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/system_settings/categories')) {
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
    safe_log('error', 'System settings route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}