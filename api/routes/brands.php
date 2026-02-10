<?php
declare(strict_types=1);

// ========================================================
// ملف الراوتر: api/routes/brands.php
// ========================================================

$baseDir = dirname(__DIR__);

// ===== تحميل bootstrap =====
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات brands =====
require_once API_VERSION_PATH . '/models/brands/repositories/PdoBrandsRepository.php';
require_once API_VERSION_PATH . '/models/brands/validators/BrandsValidator.php';
require_once API_VERSION_PATH . '/models/brands/services/BrandsService.php';
require_once API_VERSION_PATH . '/models/brands/controllers/BrandsController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// ===== Init =====
$repo       = new PdoBrandsRepository($pdo);
$validator  = new BrandsValidator();
$service    = new BrandsService($repo, $validator);
$controller = new BrandsController($service);

// ===== Tenant context =====
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    return;
}

// ===== Parse URI for slug/id =====
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));
$slug = null;
if (!empty($segments)) {
    $last = end($segments);
    if ($last !== 'brands' && !is_numeric($last)) {
        $slug = $last;
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody, true) ?? [];

    switch ($method) {
        case 'GET':
            if ($slug !== null) {
                // GET /brands/{slug}
                ResponseFormatter::success($controller->get($tenantId, $slug));
            } elseif (str_contains($uri, '/brands/active')) {
                ResponseFormatter::success($controller->getActive($tenantId));
            } elseif (str_contains($uri, '/brands/featured')) {
                ResponseFormatter::success($controller->getFeatured($tenantId));
            } else {
                // GET /brands (list all)
                ResponseFormatter::success($controller->list($tenantId));
            }
            break;

        case 'POST':
            ResponseFormatter::success($controller->create($tenantId, $body), 'Created', 201);
            break;

        case 'PUT':
            ResponseFormatter::success($controller->update($tenantId, $body));
            break;

        case 'DELETE':
            $controller->delete($tenantId, $body);
            ResponseFormatter::success(['deleted' => true]);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('error', 'Brands route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}