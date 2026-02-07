<?php
declare(strict_types=1);

// api/routes/font_settings.php

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

// ===== تحميل ملفات font_settings =====
require_once API_VERSION_PATH . '/models/font_settings/repositories/PdoFontSettingsRepository.php';
require_once API_VERSION_PATH . '/models/font_settings/validators/FontSettingsValidator.php';
require_once API_VERSION_PATH . '/models/font_settings/services/FontSettingsService.php';
require_once API_VERSION_PATH . '/models/font_settings/controllers/FontSettingsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$tenantId = (int)($_SESSION['tenant_id'] ?? 1);

// إنشاء الاعتمادات
$repo      = new PdoFontSettingsRepository($pdo);
$validator = new FontSettingsValidator();
$service   = new FontSettingsService($repo, $validator);
$controller = new FontSettingsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /font_settings/active
    if ($method === 'GET' && str_contains($uri, '/font_settings/active')) {
        ResponseFormatter::success(
            $controller->getActive($tenantId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/font_settings/categories')) {
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
    } elseif ($method === 'POST' && str_contains($uri, '/font_settings/bulk')) {
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
    safe_log('error', 'Font settings route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}