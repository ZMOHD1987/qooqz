<?php
declare(strict_types=1);

// Load bootstrap
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/shared/core/ResponseFormatter.php';
require_once dirname(__DIR__) . '/shared/helpers/safe_helpers.php';
require_once dirname(__DIR__) . '/shared/config/db.php';

// Define API_VERSION_PATH if not set
if (!defined('API_VERSION_PATH')) {
    define('API_VERSION_PATH', dirname(__DIR__) . '/v1');
}

// Load users files
require_once API_VERSION_PATH . '/models/users_account/repositories/PdoUsersRepository.php';
require_once API_VERSION_PATH . '/models/users_account/validators/UsersValidator.php';
require_once API_VERSION_PATH . '/models/users_account/services/UsersService.php';
require_once API_VERSION_PATH . '/models/users_account/controllers/UsersController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// Helper: try to extract numeric id from (in order):
// 1) ?id= query param
// 2) PATH_INFO (if provided by server)
// 3) trailing numeric segment of REQUEST_URI
function extractIdFromRequest(): ?int
{
    // 1) query param
    if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
        return (int)$_GET['id'];
    }

    // 2) PATH_INFO (fast, if PHP provides)
    if (!empty($_SERVER['PATH_INFO'])) {
        $parts = explode('/', trim((string)$_SERVER['PATH_INFO'], '/'));
        $last = end($parts);
        if (is_numeric($last)) {
            return (int)$last;
        }
    }

    // 3) fallback: parse REQUEST_URI and capture trailing number
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = explode('?', $uri, 2)[0]; // remove query string
    if (preg_match('#/(\d+)(?:/)?$#', $uri, $m)) {
        return (int)$m[1];
    }

    return null;
}

// Pagination
$limit = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filters
$filters = [];
if (!empty($_GET['preferred_language'])) $filters['preferred_language'] = trim($_GET['preferred_language']);
if (!empty($_GET['country_id'])) $filters['country_id'] = (int)$_GET['country_id'];
if (!empty($_GET['city_id'])) $filters['city_id'] = (int)$_GET['city_id'];
if (!empty($_GET['timezone'])) $filters['timezone'] = trim($_GET['timezone']);
if (!empty($_GET['role_id'])) $filters['role_id'] = (int)$_GET['role_id'];
if (!empty($_GET['search'])) $filters['search'] = trim($_GET['search']);

// Accept '0' or '1' strings for is_active; keep as int for downstream code
if (isset($_GET['is_active'])) {
    if ($_GET['is_active'] === '' || $_GET['is_active'] === null) {
        // ignore empty
    } elseif (is_numeric($_GET['is_active'])) {
        $filters['is_active'] = (int)$_GET['is_active'];
    } else {
        // accept boolean-like values
        $filters['is_active'] = ($_GET['is_active'] === 'true' || $_GET['is_active'] === '1') ? 1 : 0;
    }
}

// optional: allow filtering by user_id via query
if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}

// Create dependencies
$repo = new PdoUsersRepository($pdo);
$validator = new UsersValidator();
$service = new UsersService($repo, $validator);
$controller = new UsersController($service);

// Route the request
try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Try to detect ID from path or query
        $id = extractIdFromRequest();
        if ($id !== null) {
            ResponseFormatter::success($controller->get($id));
            return;
        }

        // No ID -> list with pagination & filters
        $list = $controller->list($limit, $offset, $filters);
        $total = $controller->count($filters);
        $data = [
            'items' => $list,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'page' => $page,
                'filters' => $filters
            ]
        ];
        ResponseFormatter::success($data);
        return;
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success($controller->create($data));
        return;
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success($controller->update($data));
        return;
    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        // Support id in path too
        if (empty($data['id'])) {
            $maybeId = extractIdFromRequest();
            if ($maybeId !== null) $data['id'] = $maybeId;
        }
        $controller->delete($data);
        ResponseFormatter::success(['deleted' => true]);
        return;
    } else {
        ResponseFormatter::error('Method not allowed', 405);
        return;
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('error', 'Users route failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? null,
        'GET' => $_GET ?? null
    ]);
    ResponseFormatter::error('Internal server error', 500);
}