<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';

require_once $certPath . '/repositories/PdoCertificatesPaymentsRepository.php';
require_once $certPath . '/validators/CertificatesPaymentsValidator.php';
require_once $certPath . '/services/CertificatesPaymentsService.php';
require_once $certPath . '/controllers/CertificatesPaymentsController.php';

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

$repo       = new PdoCertificatesPaymentsRepository($pdo);
$validator  = new CertificatesPaymentsValidator();
$service    = new CertificatesPaymentsService($repo, $validator);
$controller = new CertificatesPaymentsController($service);

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
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get($tenantId, (int)$_GET['id']);
            ResponseFormatter::success($item);
            exit;
        }

        $filters = [
            'request_id'         => isset($_GET['request_id']) && is_numeric($_GET['request_id']) ? (int)$_GET['request_id'] : null,
            'entity_id'          => isset($_GET['entity_id']) && is_numeric($_GET['entity_id']) ? (int)$_GET['entity_id'] : null,
            'payment_type'       => $_GET['payment_type'] ?? null,
            'verification_status'=> $_GET['verification_status'] ?? null,
            'verified_by'        => isset($_GET['verified_by']) && is_numeric($_GET['verified_by']) ? (int)$_GET['verified_by'] : null,
            'currency'           => $_GET['currency'] ?? null,
        ];

        // Date range for payment_date
        if (isset($_GET['payment_date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $_GET['payment_date_from'])) {
            $filters['payment_date_from'] = $_GET['payment_date_from'];
        }
        if (isset($_GET['payment_date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $_GET['payment_date_to'])) {
            $filters['payment_date_to'] = $_GET['payment_date_to'];
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

    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    if ($method === 'POST') {
        $newId = $controller->create($tenantId, $data);
        ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
        exit;
    }

    if ($method === 'PUT') {
        $updatedId = $controller->update($tenantId, $data);
        ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
        exit;
    }

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
    safe_log('warning', 'certificates_payments.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'certificates_payments.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'certificates_payments.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
