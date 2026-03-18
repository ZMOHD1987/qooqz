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

// Pagination
$limit = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filters
$filters = [];
if (!empty($_GET['role_id'])) $filters['role_id'] = (int)$_GET['role_id'];
if (!empty($_GET['search'])) $filters['search'] = trim($_GET['search']);
if (isset($_GET['is_active'])) $filters['is_active'] = (bool)$_GET['is_active'];

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
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            ResponseFormatter::success($controller->get($id));
        } else {
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
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success($controller->create($data));
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        ResponseFormatter::success($controller->update($data));
    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller->delete($data);
        ResponseFormatter::success(['deleted' => true]);
    } else {
        ResponseFormatter::error('Method not allowed', 405);
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
    ]);
    ResponseFormatter::error('Internal server error', 500);
}