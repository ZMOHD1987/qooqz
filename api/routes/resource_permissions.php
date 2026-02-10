<?php
declare(strict_types=1);

/**
 * Production route for /api/resource_permissions and /api/resource_permissions/batch
 * - Uses ResponseFormatter for output
 * - Auth & tenant checks are left as before (session based)
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/permissions';
require_once $modelsPath . '/repositories/PdoResourcePermissionsRepository.php';
require_once $modelsPath . '/validators/ResourcePermissionsValidator.php';
require_once $modelsPath . '/services/ResourcePermissionsService.php';
require_once $modelsPath . '/controllers/ResourcePermissionsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new \App\Models\Permissions\Repositories\PdoResourcePermissionsRepository($pdo);
$validator = new \App\Models\Permissions\Validators\ResourcePermissionsValidator($pdo);
$service = new \App\Models\Permissions\Services\ResourcePermissionsService($repo, $validator);
$controller = new \App\Models\Permissions\Controllers\ResourcePermissionsController($service);

// basic auth/tenant check (same as you had)
$user = $_SESSION['user'] ?? [];
$roles = $user['roles'] ?? ($_SESSION['roles'] ?? []);
$isSuperAdmin = in_array('super_admin', $roles, true) || in_array('SUPER_ADMIN', $roles, true);
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);
$roleId = isset($_GET['role_id']) && is_numeric($_GET['role_id']) ? (int)$_GET['role_id'] : null;

if (!$isSuperAdmin && ($tenantId === null || $tenantId !== (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null))) {
    ResponseFormatter::error('Unauthorized for this tenant', 403);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    safe_log('info', 'resource_permissions.request', [
        'method'=>$method,'query'=>$_GET,'body_keys'=>array_keys($data ?? []),
    ]);

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    // normalize path to determine batch endpoints
    $isBatch = str_ends_with($path, '/batch') || str_ends_with($path, '/resource_permissions/batch');

    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(204);
        exit;
    }

    if ($method === 'GET') {
        // list - require role_id in controller list (controller will throw if missing)
        $res = $controller->list($tenantId, $roleId);
        ResponseFormatter::success($res);
        exit;
    }

    if ($method === 'POST') {
        // Accept single create or batch updates under 'updates' or 'items'
        if (isset($data['updates']) && is_array($data['updates'])) {
            $summary = $controller->update(['updates' => $data['updates']]);
            ResponseFormatter::success($summary, 'Updated successfully');
            exit;
        }

        if (isset($data['items']) && is_array($data['items'])) {
            $summary = $controller->update(['items' => $data['items']]);
            ResponseFormatter::success($summary, 'Updated successfully');
            exit;
        }

        // single create
        if (!empty($data) && (isset($data['resource_type']) || isset($data['permission_id']))) {
            $res = $controller->store($data);
            ResponseFormatter::success($res, 'Created successfully', 201);
            exit;
        }

        ResponseFormatter::error('Invalid POST data. Expected "updates" or "items" array or resource permission payload.', 400);
        exit;
    }

    if ($method === 'PUT') {
        // treat as single update (id required) or bulk via body.items/updates
        $out = $controller->update($data);
        ResponseFormatter::success($out, 'Updated successfully');
        exit;
    }

    if ($method === 'DELETE') {
        $out = $controller->delete($data);
        ResponseFormatter::success($out, 'Deleted successfully');
        exit;
    }

    ResponseFormatter::error('Method not allowed: ' . $method, 405);
} catch (\InvalidArgumentException $e) {
    safe_log('warning','resource_permissions.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','resource_permissions.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical','resource_permissions.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error('Internal server error', 500);
}