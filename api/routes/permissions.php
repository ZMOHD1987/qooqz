<?php
declare(strict_types=1);

// api/routes/permissions.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ===== مسار api =====
$baseDir = dirname(__DIR__);

// ===== تحميل ResponseFormatter =====
require_once $baseDir . '/shared/core/ResponseFormatter.php';

// ===== تحميل قاعدة البيانات =====
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات الـ MVC =====
require_once API_VERSION_PATH . '/models/permissions/repositories/PdoPermissionsRepository.php';
require_once API_VERSION_PATH . '/models/permissions/validators/PermissionsValidator.php';
require_once API_VERSION_PATH . '/models/permissions/services/PermissionsService.php';
require_once API_VERSION_PATH . '/models/permissions/controllers/PermissionsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// ===== احصل على tenantId =====
$tenantId = isset($_GET['tenant_id']) 
    ? (int)$_GET['tenant_id'] 
    : ($_SESSION['tenant_id'] ?? 1);

// ===== تحميل بيانات المستخدم الحالي =====
$user = $_SESSION['user'] ?? [];
$roles = $user['roles'] ?? [];
$permissions = $user['permissions'] ?? [];

// ===== تحقق إذا كان super_admin =====
$isSuperAdmin = in_array('super_admin', $roles, true);

// إذا لم يكن super_admin، تحقق من أنه يملك الوصول لهذا الـ tenant
if (!$isSuperAdmin && $tenantId !== ($_SESSION['tenant_id'] ?? 0)) {
    ResponseFormatter::error('Unauthorized for this tenant', 403);
    return;
}

// ===== إنشاء الاعتمادات =====
$repo      = new PdoPermissionsRepository($pdo);
$validator = new PermissionsValidator();
$service   = new PermissionsService($repo, $validator);
$controller = new PermissionsController($service);

// ===== توجيه الطلب حسب METHOD =====
try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // List permissions
            ResponseFormatter::success(
                $controller->list($tenantId)
            );
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ResponseFormatter::success(
                $controller->create($tenantId, $data)
            );
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            ResponseFormatter::success(
                $controller->update($tenantId, $data)
            );
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $controller->delete($tenantId, $data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('error', 'Permissions route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    ResponseFormatter::error('Internal server error', 500);
}
