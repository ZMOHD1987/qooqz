<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/audit_logs';
require_once $modelsPath . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAuditLogsRepository.php';
require_once $modelsPath . '/validators/AuditLogsValidator.php';
require_once $modelsPath . '/services/AuditLogsService.php';
require_once $modelsPath . '/controllers/AuditLogsController.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    $repo       = new PdoAuditLogsRepository($pdo);
    $service    = new AuditLogsService($repo);
    $controller = new AuditLogsController($service);

    $page     = isset($_GET['page'])      ? max(1, (int)$_GET['page'])              : 1;
    $limit    = isset($_GET['limit'])     ? min(200, max(1, (int)$_GET['limit']))   : 50;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by']  ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'action'      => $_GET['action']      ?? null,
        'entity_type' => $_GET['entity_type'] ?? null,
        'entity_id'   => isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null,
        'user_id'     => isset($_GET['user_id'])   ? (int)$_GET['user_id']   : null,
        'http_method' => $_GET['http_method'] ?? null,
        'session_id'  => $_GET['session_id']  ?? null,
        'request_id'  => $_GET['request_id']  ?? null,
        'date_from'   => $_GET['date_from']   ?? null,
        'date_to'     => $_GET['date_to']     ?? null,
        'search'      => $_GET['search']      ?? null,
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

        default:
            ResponseFormatter::error('Method not allowed. Audit logs are read-only via API.', 405);
    }
} catch (\RuntimeException $e) {
    safe_log('error', 'audit_logs.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'audit_logs.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('An unexpected error occurred', 500);
}