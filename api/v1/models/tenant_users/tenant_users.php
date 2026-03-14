<?php
declare(strict_types=1);

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/shared/core/ResponseFormatter.php';
require_once dirname(__DIR__) . '/shared/helpers/safe_helpers.php';
require_once dirname(__DIR__) . '/shared/config/db.php';

// Load tenant_users files
require_once API_VERSION_PATH . '/models/tenant_users/repositories/PdoTenant_usersRepository.php';
require_once API_VERSION_PATH . '/models/tenant_users/validators/Tenant_usersValidator.php';
require_once API_VERSION_PATH . '/models/tenant_users/services/Tenant_usersService.php';
require_once API_VERSION_PATH . '/models/tenant_users/controllers/Tenant_usersController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// Use tenantId from session or default
$tenantId = $_SESSION['tenant_id'] ?? 1;

// Create dependencies
$repo      = new PdoTenant_usersRepository($pdo);
$validator = new Tenant_usersValidator();
$service   = new Tenant_usersService($repo, $validator);
$controller = new Tenant_usersController($service);

// Route the request
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            ResponseFormatter::success($controller->get($tenantId, $id));
        } else {
            ResponseFormatter::success($controller->list($tenantId));
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success($controller->create($tenantId, $data));
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success($controller->update($tenantId, $data));
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
    safe_log('error', 'Tenant_users route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}