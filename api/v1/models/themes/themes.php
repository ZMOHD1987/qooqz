<?php
declare(strict_types=1);

// api/routes/themes.php

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

// ===== تحميل ملفات themes =====
require_once API_VERSION_PATH . '/models/themes/repositories/PdoThemesRepository.php';
require_once API_VERSION_PATH . '/models/themes/validators/ThemesValidator.php';
require_once API_VERSION_PATH . '/models/themes/services/ThemesService.php';
require_once API_VERSION_PATH . '/models/themes/controllers/ThemesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// استخدم tenantId ثابت مؤقتًا
$tenantId = 1;

// إنشاء الاعتمادات
$repo      = new PdoThemesRepository($pdo);
$validator = new ThemesValidator();
$service   = new ThemesService($repo, $validator);
$controller = new ThemesController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /themes/active
    if ($method === 'GET' && str_contains($uri, '/themes/active')) {
        ResponseFormatter::success(
            $controller->getActive($tenantId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/themes/default')) {
        ResponseFormatter::success(
            $controller->getDefault($tenantId)
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
    } elseif ($method === 'POST' && str_contains($uri, '/themes/activate')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->activate($tenantId, $data)
        );
    } elseif ($method === 'POST' && str_contains($uri, '/themes/set-default')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success(
            $controller->setDefault($tenantId, $data)
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
    safe_log('error', 'Themes route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}