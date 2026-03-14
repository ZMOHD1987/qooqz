<?php
declare(strict_types=1);

// api/routes/banners.php

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

// ===== تحميل ملفات banners =====
require_once API_VERSION_PATH . '/models/banners/repositories/PdoBannersRepository.php';
require_once API_VERSION_PATH . '/models/banners/validators/BannersValidator.php';
require_once API_VERSION_PATH . '/models/banners/services/BannersService.php';
require_once API_VERSION_PATH . '/models/banners/controllers/BannersController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// استخدم tenantId ثابت مؤقتًا
$tenantId = 1;

// إنشاء الاعتمادات
$repo      = new PdoBannersRepository($pdo);
$validator = new BannersValidator();
$service   = new BannersService($repo, $validator);
$controller = new BannersController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /banners/active?position=homepage_main
    if ($method === 'GET' && str_contains($uri, '/banners/active')) {
        $position = $_GET['position'] ?? 'homepage_main';
        ResponseFormatter::success(
            $controller->getActive($tenantId, $position)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/banners/positions')) {
        ResponseFormatter::success(
            $controller->positions($tenantId)
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
    safe_log('error', 'Banners route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}