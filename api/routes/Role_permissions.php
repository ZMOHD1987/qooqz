<?php
declare(strict_types=1);

// api/routes/role_permissions.php

$baseDir = dirname(__DIR__);

// ===== تحميل bootstrap و dependencies =====
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات role_permissions =====
require_once API_VERSION_PATH . '/models/permissions/repositories/PdoRolePermissionsRepository.php';
require_once API_VERSION_PATH . '/models/permissions/validators/Role_permissionsValidator.php';
require_once API_VERSION_PATH . '/models/permissions/services/Role_permissionsService.php';
require_once API_VERSION_PATH . '/models/permissions/controllers/RolePermissionsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// ===== احصل على tenantId و roleId من query string =====
$tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : ($_SESSION['tenant_id'] ?? 1);
$roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

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
$repo      = new PdoRolePermissionsRepository($pdo);
$validator = new RolePermissionsValidator();
$service   = new RolePermissionsService($repo, $validator);
$controller = new RolePermissionsController($service);

// ===== توجيه الطلب =====
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $rawInput = file_get_contents('php://input');
    $data = $rawInput ? json_decode($rawInput, true) : [];

    safe_log('debug', 'Role permissions request', [
        'method' => $method,
        'tenant_id' => $tenantId,
        'role_id' => $roleId,
        'data' => $data
    ]);

    switch ($method) {
        case 'GET':
            // إذا تم تمرير role_id، جلب صلاحيات هذا الدور فقط
            if ($roleId) {
                $all = $controller->list($tenantId);
                $filtered = array_filter($all, fn($item) => $item['role_id'] === $roleId);
                ResponseFormatter::success(array_values($filtered));
            } else {
                // جلب جميع صلاحيات tenant
                ResponseFormatter::success($controller->list($tenantId));
            }
            break;

        case 'POST':
            // تفريق بين bulk و single assign
            $result = $controller->assign($tenantId, $data);
            ResponseFormatter::success($result);
            break;

        case 'DELETE':
            $controller->delete($tenantId, $data);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed: ' . $method, 405);
    }
} catch (InvalidArgumentException $e) {
    safe_log('warning', 'Validation error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'Runtime error', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('error', 'Role permissions route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
