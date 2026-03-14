<?php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$brandsPath = API_VERSION_PATH . '/models/brands';
require_once $brandsPath . '/repositories/PdoBrandsRepository.php';
require_once $brandsPath . '/validators/BrandsValidator.php';
require_once $brandsPath . '/services/BrandsService.php';
require_once $brandsPath . '/controllers/BrandsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoBrandsRepository($pdo);
$validator  = new BrandsValidator();
$service    = new BrandsService($repo, $validator);
$controller = new BrandsController($service);

$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('tenant_id required', 401);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// تحليل المسار للكشف عن slug أو id
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));
$slug = null;
$id = null;

if (count($segments) > 0) {
    $last = end($segments);
    if ($last !== 'brands' && $last !== 'active' && $last !== 'featured') {
        if (is_numeric($last)) {
            $id = (int)$last;
        } else {
            $slug = $last;
        }
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawBody = file_get_contents('php://input');
    $body = ($rawBody !== false && $rawBody !== '') ? (json_decode($rawBody, true) ?? []) : [];

    if ($method === 'GET') {
        if ($slug !== null) {
            // GET /brands/{slug}
            $result = $controller->get($tenantId, $slug);
            ResponseFormatter::success($result);
        } elseif ($id !== null) {
            // GET /brands/{id}
            $result = $controller->getById($tenantId, $id);
            ResponseFormatter::success($result);
        } elseif (strpos($uri, '/brands/active') !== false) {
            // GET /brands/active
            $result = $controller->getActive($tenantId);
            ResponseFormatter::success($result);
        } elseif (strpos($uri, '/brands/featured') !== false) {
            // GET /brands/featured
            $result = $controller->getFeatured($tenantId);
            ResponseFormatter::success($result);
        } else {
            // GET /brands (قائمة مع فلترة)
            $result = $controller->list($tenantId);
            ResponseFormatter::success($result);
        }
    } elseif ($method === 'POST') {
        $result = $controller->create($tenantId, $body);
        ResponseFormatter::success($result, 'Created successfully', 201);
    } elseif ($method === 'PUT') {
        $result = $controller->update($tenantId, $body);
        ResponseFormatter::success($result, 'Updated successfully');
    } elseif ($method === 'DELETE') {
        $controller->delete($tenantId, $body);
        ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
    } else {
        ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    safe_log('warning', 'brands.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('warning', 'brands.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('critical', 'brands.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
