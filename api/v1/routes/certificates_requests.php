<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';
require_once $certPath . '/repositories/PdoCertificatesRequestsRepository.php';
require_once $certPath . '/validators/CertificatesRequestsValidator.php';
require_once $certPath . '/services/CertificatesRequestsService.php';
require_once $certPath . '/controllers/CertificatesRequestsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoCertificatesRequestsRepository($pdo);
$service    = new CertificatesRequestsService($repo);
$controller = new CertificatesRequestsController($service);

// Tenant
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('tenant_id required', 401);
    exit;
}

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {

        // Single record
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $row = $controller->get($tenantId, (int)$_GET['id']);
            ResponseFormatter::success($row);
            exit;
        }

        // ── Status filter ─────────────────────────────────────────────────
        // Supports:
        //   ?status=approved
        //   ?status=draft,under_review,payment_pending   (comma-separated)
        //   ?status_exclude=approved,issued              (excludes from results)
        $statusFilter = null;
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $statusFilter = $_GET['status'];   // kept as-is (string); repo handles splitting
        }

        $statusExclude = null;
        if (isset($_GET['status_exclude']) && $_GET['status_exclude'] !== '') {
            $statusExclude = $_GET['status_exclude'];
        }

        // ── Build filters array ───────────────────────────────────────────
        $filters = [
            'entity_id'              => isset($_GET['entity_id'])              && is_numeric($_GET['entity_id'])              ? (int)$_GET['entity_id']              : null,
            'importer_country_id'    => isset($_GET['importer_country_id'])    && is_numeric($_GET['importer_country_id'])    ? (int)$_GET['importer_country_id']    : null,
            'certificate_type'       => $_GET['certificate_type']              ?? null,
            'operation_type'         => $_GET['operation_type']                ?? null,
            'status'                 => $statusFilter,
            'status_exclude'         => $statusExclude,
            'importer_name'          => $_GET['importer_name']                 ?? null,
            'search'                 => $_GET['search']                        ?? null,
            'shipment_condition'     => (isset($_GET['shipment_condition']) && $_GET['shipment_condition'] !== '') ? $_GET['shipment_condition'] : null,
            'certificate_id'         => isset($_GET['certificate_id'])         && is_numeric($_GET['certificate_id'])         ? (int)$_GET['certificate_id']         : null,
            'certificate_edition_id' => isset($_GET['certificate_edition_id']) && is_numeric($_GET['certificate_edition_id']) ? (int)$_GET['certificate_edition_id'] : null,
            'auditor_user_id'        => isset($_GET['auditor_user_id'])        && is_numeric($_GET['auditor_user_id'])        ? (int)$_GET['auditor_user_id']        : null,
            'payment_status'         => $_GET['payment_status']                ?? null,
        ];

        if (isset($_GET['issue_date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['issue_date_from'])) {
            $filters['issue_date_from'] = $_GET['issue_date_from'];
        }
        if (isset($_GET['issue_date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['issue_date_to'])) {
            $filters['issue_date_to'] = $_GET['issue_date_to'];
        }

        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
        $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
        $offset   = ($page - 1) * $limit;

        $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
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

    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    if ($method === 'POST') {
        $row = $controller->create($tenantId, $data);
        ResponseFormatter::success($row);
        exit;
    }

    if ($method === 'PUT') {
        $row = $controller->update($tenantId, $data);
        ResponseFormatter::success($row);
        exit;
    }

    if ($method === 'DELETE') {
        if (empty($data['id']) || !is_numeric($data['id'])) {
            ResponseFormatter::error('Missing or invalid id for deletion.', 400);
            exit;
        }
        $controller->delete($tenantId, (int)$data['id']);
        ResponseFormatter::success(['deleted' => true]);
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'cert_requests.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'cert_requests.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'cert_requests.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
