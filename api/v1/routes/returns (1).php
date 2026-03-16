<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/returns';
require_once $modelsPath . '/Contracts/ReturnsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoReturnsRepository.php';
require_once $modelsPath . '/validators/ReturnsValidator.php';
require_once $modelsPath . '/services/ReturnsService.php';
require_once $modelsPath . '/controllers/ReturnsController.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

$pdo    = $GLOBALS['ADMIN_DB'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

try {
    $repo       = new PdoReturnsRepository($pdo);
    $service    = new ReturnsService($repo);
    $controller = new ReturnsController($service);

    $lang     = $_GET['lang']      ?? 'en';
    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])           : 1;
    $limit    = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'status'    => $_GET['status']    ?? null,
        'order_id'  => isset($_GET['order_id'])  && is_numeric($_GET['order_id'])  ? (int)$_GET['order_id']  : null,
        'user_id'   => isset($_GET['user_id'])   && is_numeric($_GET['user_id'])   ? (int)$_GET['user_id']   : null,
        'entity_id' => isset($_GET['entity_id']) && is_numeric($_GET['entity_id']) ? (int)$_GET['entity_id'] : null,
        'search'    => $_GET['search']    ?? null,
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($tenantId, (int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'limit'       => $limit,
                        'total_pages' => $limit > 0 ? (int)ceil($result['total'] / $limit) : 1,
                    ],
                ]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = $controller->create($tenantId, $input);
            ResponseFormatter::success(['id' => $id], 'Return request created successfully', 201);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($input['id'])) {
                ResponseFormatter::error('ID is required', 400);
                break;
            }
            $affected = $controller->update($tenantId, $input);
            ResponseFormatter::success(['affected' => $affected], 'Return request updated successfully');
            break;

        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = $input['id'] ?? $_GET['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                ResponseFormatter::error('ID is required', 400);
                break;
            }
            $deleted = $controller->delete($tenantId, (int)$id);
            ResponseFormatter::success(['deleted' => $deleted], 'Return request deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    ResponseFormatter::error('Server error: ' . $e->getMessage(), 500);
}