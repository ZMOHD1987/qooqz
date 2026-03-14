<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';
require_once $certPath . '/repositories/Pdocertificatesrequestitemsrepository.php';
require_once $certPath . '/validators/Certificatesrequestitemsvalidator.php';
require_once $certPath . '/repositories/Pdocertificateslogsrepository.php';
require_once $certPath . '/services/Certificateslogsservice.php';
require_once $certPath . '/services/Certificatesrequestitemsservice.php';
require_once $certPath . '/controllers/Certificatesrequestitemscontroller.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ── تهيئة الـ LogsService ليُمرَّر لـ RequestItemsService ─────────────────
$logsRepo    = new CertificatesLogsRepository($pdo);
$logsService = new CertificatesLogsService($logsRepo);

$repo        = new PdoCertificatesRequestItemsRepository($pdo);
$service     = new CertificatesRequestItemsService($repo, $pdo, $logsService);
$controller  = new CertificatesRequestItemsController($service);

// ── Tenant resolution ──────────────────────────────────────────────────────
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('tenant_id required', 401);
    exit;
}

// ── User ID من الجلسة ──────────────────────────────────────────────────────
$sessionUser = $_SESSION['user'] ?? [];
$userId      = isset($sessionUser['id']) ? (int)$sessionUser['id'] : 0;

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ── OPTIONS ───────────────────────────────────────────────────────────────
    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(204);
        exit;
    }

    // ── GET ───────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        // Single item: GET ?id=123
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $row = $controller->get($tenantId, (int)$_GET['id']);
            ResponseFormatter::success($row);
            exit;
        }

        $filters = [
            'request_id'          => isset($_GET['request_id'])     && is_numeric($_GET['request_id'])     ? (int)$_GET['request_id']     : null,
            'product_id'          => isset($_GET['product_id'])      && is_numeric($_GET['product_id'])     ? (int)$_GET['product_id']     : null,
            'weight_unit_id'      => isset($_GET['weight_unit_id'])  && is_numeric($_GET['weight_unit_id']) ? (int)$_GET['weight_unit_id'] : null,
            'quantity_min'        => isset($_GET['quantity_min'])     && is_numeric($_GET['quantity_min'])   ? (float)$_GET['quantity_min'] : null,
            'quantity_max'        => isset($_GET['quantity_max'])     && is_numeric($_GET['quantity_max'])   ? (float)$_GET['quantity_max'] : null,
            'production_date_from'=> $_GET['production_date_from']   ?? null,
            'expiry_date_to'      => $_GET['expiry_date_to']         ?? null,
        ];
        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
        $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
        $offset   = ($page - 1) * $limit;

        $result = $controller->list($tenantId, $filters, $limit, $offset, $orderBy, $orderDir);
        $total  = $result['total'];

        ResponseFormatter::success([
            'items' => $result['items'],
            'meta'  => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                'from'        => $total > 0 ? $offset + 1 : 0,
                'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
            ],
        ]);
        exit;
    }

    // ── Shared body parse ─────────────────────────────────────────────────────
    $raw   = file_get_contents('php://input');
    $input = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    // ── POST ──────────────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $row = $controller->create($tenantId, $input, $userId);
        ResponseFormatter::success($row, 'Created successfully', 201);
        exit;
    }

    // ── PUT ───────────────────────────────────────────────────────────────────
    if ($method === 'PUT') {
        $row = $controller->update($tenantId, $input, $userId);
        ResponseFormatter::success($row, 'Updated successfully');
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        if (empty($input['id']) || !is_numeric($input['id'])) {
            ResponseFormatter::error('Missing or invalid id for deletion.', 400);
            exit;
        }
        $controller->delete($tenantId, (int)$input['id'], $userId);
        ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'cert_request_items.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'cert_request_items.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'cert_request_items.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
