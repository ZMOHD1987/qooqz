<?php
declare(strict_types=1);

// api/routes/languages.php

$baseDir = dirname(__DIR__);

// ===== تحميل bootstrap و dependencies =====
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات languages =====
require_once API_VERSION_PATH . '/models/languages/repositories/PdoLanguagesRepository.php';
require_once API_VERSION_PATH . '/models/languages/validators/LanguagesValidator.php';
require_once API_VERSION_PATH . '/models/languages/services/LanguagesService.php';
require_once API_VERSION_PATH . '/models/languages/controllers/LanguagesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// ===== احصل على tenantId من query string =====
$sessionTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;
$tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : $sessionTenantId;

// ===== تحميل بيانات المستخدم الحالي =====
$user = $_SESSION['user'] ?? [];
$roles = $user['roles'] ?? [];
$permissions = $user['permissions'] ?? [];

// ===== تحقق إذا كان super_admin =====
$isSuperAdmin = in_array('super_admin', $roles, true);

// إذا لم يكن super_admin، تحقق من أنه يملك الوصول لهذا الـ tenant
if (!$isSuperAdmin && $tenantId !== $sessionTenantId) {
    ResponseFormatter::error('Unauthorized for this tenant', 403);
    return;
}

// ===== إنشاء الاعتمادات =====
$repo      = new PdoLanguagesRepository($pdo);
$validator = new LanguagesValidator();
$service   = new LanguagesService($repo, $validator);
$controller = new LanguagesController($service);

// ===== توجيه الطلب =====
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $rawInput = file_get_contents('php://input');
    $data = $rawInput ? json_decode($rawInput, true) : [];

    safe_log('debug', 'Languages request', [
        'method' => $method,
        'tenant_id' => $tenantId,
        'data' => $data
    ]);

    switch ($method) {
        case 'GET':
            // جلب قائمة اللغات مع فلاتر
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 10;
            $offset = ($page - 1) * $perPage;

            $filters = [];
            if (!empty($_GET['search'])) {
                $filters['search'] = trim($_GET['search']);
            }
            if (isset($_GET['is_active'])) {
                $filters['is_active'] = (bool)$_GET['is_active'];
            }

            $items = $controller->list($perPage, $offset, $filters);
            $total = $controller->count($filters);

            ResponseFormatter::success([
                'items' => $items,
                'meta' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'page' => $page,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);
            break;

        case 'POST':
            // إنشاء لغة جديدة
            $result = $controller->create($data);
            ResponseFormatter::success($result);
            break;

        case 'PUT':
            // تحديث لغة
            $result = $controller->update($data);
            ResponseFormatter::success($result);
            break;

        case 'DELETE':
            // حذف لغة
            $controller->delete($data);
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
    safe_log('error', 'Languages route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}