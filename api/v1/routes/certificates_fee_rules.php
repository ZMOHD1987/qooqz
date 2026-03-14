<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';

require_once $certPath . '/repositories/PdoCertificatesFeeRulesRepository.php';
require_once $certPath . '/validators/CertificatesFeeRulesValidator.php';
require_once $certPath . '/services/CertificatesFeeRulesService.php';
require_once $certPath . '/controllers/CertificatesFeeRulesController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// هذا الجدول لا يحتوي على tenant_id، لذلك لا نستخدم tenant هنا.
// لكن يمكن تمريره كفلتر إذا أردنا، لكنه غير موجود بالجدول.
// سنهمل الـ tenant.

$repo       = new PdoCertificatesFeeRulesRepository($pdo);
$validator  = new CertificatesFeeRulesValidator();
$service    = new CertificatesFeeRulesService($repo, $validator);
$controller = new CertificatesFeeRulesController($service);

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
            $item = $controller->get((int)$_GET['id']);
            ResponseFormatter::success($item);
            exit;
        }

        $filters = [
            'fee_type'   => $_GET['fee_type'] ?? null,
            'max_items'  => isset($_GET['max_items']) && is_numeric($_GET['max_items']) ? (int)$_GET['max_items'] : null,
            'is_active'  => isset($_GET['is_active']) && is_numeric($_GET['is_active']) ? (int)$_GET['is_active'] : null,
        ];

        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page']) : 1;
        $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
        $offset   = ($page - 1) * $limit;

        $result = $controller->list($filters, $orderBy, $orderDir, $limit, $offset);
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
        $newId = $controller->create($data);
        ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
        exit;
    }

    // PUT
    if ($method === 'PUT') {
        $updatedId = $controller->update($data);
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
        $controller->delete((int)$id);
        ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'certificates_fee_rules.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'certificates_fee_rules.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'certificates_fee_rules.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
