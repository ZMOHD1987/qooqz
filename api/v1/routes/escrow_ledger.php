<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/escrow';
require_once $modelsPath . '/Contracts/EscrowLedgerRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoEscrowLedgerRepository.php';
require_once $modelsPath . '/validators/EscrowLedgerValidator.php';
require_once $modelsPath . '/services/EscrowLedgerService.php';
require_once $modelsPath . '/controllers/EscrowLedgerController.php';

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
    $repo       = new PdoEscrowLedgerRepository($pdo);
    $service    = new EscrowLedgerService($repo);
    $controller = new EscrowLedgerController($service);

    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])           : 1;
    $limit    = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'escrow_id'        => isset($_GET['escrow_id']) && is_numeric($_GET['escrow_id'])        ? (int)$_GET['escrow_id']        : null,
        'entity_id'        => isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])        ? (int)$_GET['entity_id']        : null,
        'transaction_type' => $_GET['transaction_type'] ?? null,
        'currency_code'    => $_GET['currency_code']    ?? null,
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
            $data  = json_decode(file_get_contents('php://input'), true) ?: [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Escrow ledger entry created successfully', 201);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required for deletion', 400);
                break;
            }
            $deleted = $controller->delete($tenantId, $id);
            ResponseFormatter::success(['deleted' => $deleted], 'Escrow ledger entry deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'escrow_ledger.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'escrow_ledger.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'escrow_ledger.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('An unexpected error occurred', 500);
}
