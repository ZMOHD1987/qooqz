<?php
declare(strict_types=1);

// api/routes/banners.php — updated to follow brands.php pattern

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$bannersPath = API_VERSION_PATH . '/models/banners';
require_once $bannersPath . '/repositories/PdoBannersRepository.php';
require_once $bannersPath . '/validators/BannersValidator.php';
require_once $bannersPath . '/services/BannersService.php';
require_once $bannersPath . '/controllers/BannersController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoBannersRepository($pdo);
$validator  = new BannersValidator();
$service    = new BannersService($repo, $validator);
$controller = new BannersController($service);

// Resolve tenantId — from query param or session
$tenantId = null;
if (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
    $tenantId = (int)$_GET['tenant_id'];
} elseif (isset($_SESSION['tenant_id'])) {
    $tenantId = (int)$_SESSION['tenant_id'];
} elseif (isset($GLOBALS['ADMIN_UI']['user']['tenant_id'])) {
    $tenantId = (int)$GLOBALS['ADMIN_UI']['user']['tenant_id'];
}

if ($tenantId === null) {
    ResponseFormatter::error('tenant_id required', 401);
    exit;
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parse URI to extract id or sub-path
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));
$id       = null;
$subPath  = null;

if (count($segments) > 0) {
    $last = end($segments);
    if (is_numeric($last)) {
        $id = (int)$last;
    } elseif ($last !== 'banners') {
        $subPath = $last; // e.g. 'active', 'positions'
    }
}

try {
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawBody = file_get_contents('php://input');
    $body    = ($rawBody !== '' && $rawBody !== false) ? (json_decode($rawBody, true) ?? []) : [];

    if ($method === 'GET') {
        if ($subPath === 'active') {
            $position = $_GET['position'] ?? 'homepage_main';
            ResponseFormatter::success($controller->getActive($tenantId, $position));
        } elseif ($subPath === 'positions') {
            ResponseFormatter::success($controller->positions($tenantId));
        } elseif ($id !== null) {
            ResponseFormatter::success($controller->get($tenantId, $id));
        } else {
            ResponseFormatter::success($controller->list($tenantId));
        }
    } elseif ($method === 'POST') {
        $result = $controller->create($tenantId, $body);
        ResponseFormatter::success($result, 201);
    } elseif ($method === 'PUT') {
        if ($id === null && !empty($body['id'])) {
            $id = (int)$body['id'];
        }
        if ($id === null) {
            ResponseFormatter::error('Banner ID required', 400);
            exit;
        }
        $body['id'] = $id;
        ResponseFormatter::success($controller->update($tenantId, $body));
    } elseif ($method === 'DELETE') {
        if ($id === null && !empty($body['id'])) {
            $id = (int)$body['id'];
        }
        if ($id === null) {
            ResponseFormatter::error('Banner ID required', 400);
            exit;
        }
        $body['id'] = $id;
        $controller->delete($tenantId, $body);
        ResponseFormatter::success(['deleted' => true]);
    } else {
        ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    if (str_contains(strtolower($e->getMessage()), 'not found')) {
        ResponseFormatter::error($e->getMessage(), 404);
    } else {
        ResponseFormatter::error($e->getMessage(), 400);
    }
} catch (Throwable $e) {
    safe_log('error', 'Banners route error', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
