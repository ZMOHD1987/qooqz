<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/escrow';
require_once $modelsPath . '/Contracts/EscrowStatusHistoryRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoEscrowStatusHistoryRepository.php';
require_once $modelsPath . '/validators/EscrowStatusHistoryValidator.php';
require_once $modelsPath . '/services/EscrowStatusHistoryService.php';
require_once $modelsPath . '/controllers/EscrowStatusHistoryController.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
    $repo       = new PdoEscrowStatusHistoryRepository($pdo);
    $service    = new EscrowStatusHistoryService($repo);
    $controller = new EscrowStatusHistoryController($service);

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])           : 1;
    $limit    = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'created_at';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'escrow_id'            => isset($_GET['escrow_id']) && is_numeric($_GET['escrow_id'])            ? (int)$_GET['escrow_id']            : null,
        'status'               => $_GET['status']               ?? null,
        'changed_by_entity_id' => isset($_GET['changed_by_entity_id']) && is_numeric($_GET['changed_by_entity_id']) ? (int)$_GET['changed_by_entity_id'] : null,
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($tenantId, (int)$_GET['id']);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0,
                    ],
                ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            if (empty($data['changed_by_entity_id']) && isset($_SESSION['user_id'])) {
                $data['changed_by_entity_id'] = (int)$_SESSION['user_id'];
            }
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Escrow status history created successfully', 201);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required for deletion', 400);
                break;
            }
            $deleted = $controller->delete($tenantId, $id);
            ResponseFormatter::success(['deleted' => $deleted], 'Escrow status history deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'escrow_status_history.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'escrow_status_history.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'escrow_status_history.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('An unexpected error occurred', 500);
}
