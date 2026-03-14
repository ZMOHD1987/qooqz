<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/certificates/repositories/PdoCertificatesTemplatesRepository.php';
require_once API_VERSION_PATH . '/models/certificates/validators/CertificatesTemplatesValidator.php';
require_once API_VERSION_PATH . '/models/certificates/services/CertificatesTemplatesService.php';
require_once API_VERSION_PATH . '/models/certificates/controllers/CertificatesTemplatesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$repo = new PdoCertificatesTemplatesRepository($pdo);
$service = new CertificatesTemplatesService($repo);
$controller = new CertificatesTemplatesController($service);

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
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // GET single by path /certificates_templates/{id}
    if ($method === 'GET' && preg_match('#/certificates_templates/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        $row = $controller->get($id);
        ResponseFormatter::success($row);
        return;
    }

    // GET list with filters
    if ($method === 'GET') {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 50;
        $offset = ($page - 1) * $limit;
        $orderBy = $_GET['order_by'] ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';

        $filters = [
            'code'          => $_GET['code']          ?? null,
            'language_code' => $_GET['language_code'] ?? null,
            'paper_size'    => $_GET['paper_size']    ?? null,  // إضافة
            'orientation'   => $_GET['orientation']   ?? null,  // إضافة
            'is_active'     => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null
        ];

        $res = $controller->list($filters, $limit, $offset, $orderBy, $orderDir);
        $meta = [
            'total'       => $res['total'],
            'page'        => $page,
            'per_page'    => $limit,
            'from'        => $res['total'] > 0 ? $offset + 1 : 0,
            'to'          => $res['total'] > 0 ? min($offset + $limit, $res['total']) : 0,
            'total_pages' => $res['total'] > 0 ? (int)ceil($res['total'] / $limit) : 0
        ];
        ResponseFormatter::success(['items' => $res['items'], 'meta' => $meta]);
        return;
    }

    // read JSON body
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    if ($method === 'POST') {
        $row = $controller->create($data);
        ResponseFormatter::success($row, 201);
        return;
    }

    if ($method === 'PUT') {
        $row = $controller->update($data);
        ResponseFormatter::success($row);
        return;
    }

    if ($method === 'DELETE') {
        // allow id in path or body
        if (preg_match('#/certificates_templates/(\d+)$#', $uri, $m)) {
            $id = (int)$m[1];
        } else {
            $id = !empty($data['id']) ? (int)$data['id'] : null;
        }
        if (empty($id)) {
            ResponseFormatter::error('Missing id for delete', 422);
            return;
        }
        $controller->delete($id);
        ResponseFormatter::success(['deleted' => true]);
        return;
    }

    ResponseFormatter::error('Method not allowed', 405);
} catch (InvalidArgumentException $e) {
    safe_log('warning', 'cert_templates.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'cert_templates.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'cert_templates.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'GET' => $_GET]);
    ResponseFormatter::error('Internal server error', 500);
}
