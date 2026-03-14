<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';

require_once $certPath . '/repositories/PdoCertificatesAuditsRepository.php';
require_once $certPath . '/validators/CertificatesAuditsValidator.php';
require_once $certPath . '/services/CertificatesAuditsService.php';
require_once $certPath . '/controllers/CertificatesAuditsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// Tenant resolution
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('tenant_id required', 401);
    exit;
}

$repo       = new PdoCertificatesAuditsRepository($pdo);
$validator  = new CertificatesAuditsValidator();
$service    = new CertificatesAuditsService($repo, $validator);
$controller = new CertificatesAuditsController($service);

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

    // GET
    if ($method === 'GET') {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get($tenantId, (int)$_GET['id']);
            ResponseFormatter::success($item);
            exit;
        }

        $filters = [
            'request_id' => isset($_GET['request_id']) && is_numeric($_GET['request_id']) ? (int)$_GET['request_id'] : null,
            'auditor_id' => isset($_GET['auditor_id']) && is_numeric($_GET['auditor_id']) ? (int)$_GET['auditor_id'] : null,
            'status'     => $_GET['status'] ?? null,
            'assigned_by' => isset($_GET['assigned_by']) && is_numeric($_GET['assigned_by']) ? (int)$_GET['assigned_by'] : null,
        ];

        // Date range filters for audit_date
        if (isset($_GET['audit_date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $_GET['audit_date_from'])) {
            $filters['audit_date_from'] = $_GET['audit_date_from'];
        }
        if (isset($_GET['audit_date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $_GET['audit_date_to'])) {
            $filters['audit_date_to'] = $_GET['audit_date_to'];
        }

        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page']) : 1;
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

    // Read JSON body
    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    // POST
    if ($method === 'POST') {
        $newId = $controller->create($tenantId, $data);
        ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
        exit;
    }

    // PUT
    if ($method === 'PUT') {
        $updatedId = $controller->update($tenantId, $data);
        ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
        exit;
    }

    // DELETE
    if ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            ResponseFormatter::error('Missing or invalid id for deletion', 400);
            exit;
        }
        $controller->delete($tenantId, (int)$id);
        ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'certificates_audits.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'certificates_audits.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'certificates_audits.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
