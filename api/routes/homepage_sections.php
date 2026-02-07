<?php
declare(strict_types=1);

// api/routes/homepage_sections.php

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

// ===== تحميل ملفات homepage_sections =====
require_once API_VERSION_PATH . '/models/homepage_sections/repositories/PdoHomepageSectionsRepository.php';
require_once API_VERSION_PATH . '/models/homepage_sections/validators/HomepageSectionsValidator.php';
require_once API_VERSION_PATH . '/models/homepage_sections/services/HomepageSectionsService.php';
require_once API_VERSION_PATH . '/models/homepage_sections/controllers/HomepageSectionsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$tenantId = (int)($_SESSION['tenant_id'] ?? 1);

// إنشاء الاعتمادات
$repo      = new PdoHomepageSectionsRepository($pdo);
$validator = new HomepageSectionsValidator();
$service   = new HomepageSectionsService($repo, $validator);
$controller = new HomepageSectionsController($service);

// توجيه الطلب
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // GET /homepage_sections/active
    if ($method === 'GET' && str_contains($uri, '/homepage_sections/active')) {
        ResponseFormatter::success(
            $controller->getActive($tenantId)
        );
    } elseif ($method === 'GET' && str_contains($uri, '/homepage_sections/types')) {
        ResponseFormatter::success(
            $controller->sectionTypes($tenantId)
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
    safe_log('error', 'Homepage sections route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}