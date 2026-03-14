<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';
require_once $certPath . '/repositories/PdoCertificatesProductsRepository.php';
require_once $certPath . '/validators/CertificatesProductsValidator.php';
require_once $certPath . '/services/CertificatesProductsService.php';
require_once $certPath . '/controllers/CertificatesProductsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new CertificatesProductsRepository($pdo);
$validator  = new CertificatesProductsValidator();
$service    = new CertificatesProductsService($repo, $validator);
$controller = new CertificatesProductsController($service);

try {

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ================= OPTIONS =================
    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(204);
        exit;
    }

    // ================= GET =================
    if ($method === 'GET') {

        // -------- GET by ID --------
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get((int)$_GET['id']);
            if ($item === null) {
                ResponseFormatter::error('Record not found', 404);
                exit;
            }
            ResponseFormatter::success($item);
            exit;
        }

        // -------- Filters --------
        $filters = [
            'tenant_id'           => isset($_GET['tenant_id'])           && is_numeric($_GET['tenant_id'])           ? (int)$_GET['tenant_id']           : null,
            'entity_id'           => isset($_GET['entity_id'])           && is_numeric($_GET['entity_id'])           ? (int)$_GET['entity_id']           : null,
            'brand_id'            => isset($_GET['brand_id'])            && is_numeric($_GET['brand_id'])            ? (int)$_GET['brand_id']            : null,
            'origin_country_id'   => isset($_GET['origin_country_id'])   && is_numeric($_GET['origin_country_id'])   ? (int)$_GET['origin_country_id']   : null,
            'entity_product_code' => isset($_GET['entity_product_code']) && $_GET['entity_product_code'] !== ''      ? $_GET['entity_product_code']      : null,
            'weight_unit'         => isset($_GET['weight_unit'])         && $_GET['weight_unit'] !== ''              ? $_GET['weight_unit']              : null,
            'sample_status'       => isset($_GET['sample_status'])       && $_GET['sample_status'] !== ''            ? $_GET['sample_status']            : null,
            'product_condition'   => isset($_GET['product_condition'])   && $_GET['product_condition'] !== ''        ? $_GET['product_condition']        : null,
        ];

        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';

        $page   = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
        $limit  = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
        $offset = ($page - 1) * $limit;

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

    // ================= BODY =================
    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    // تأكيد وجود origin_country_id في POST/PUT
    if (in_array($method, ['POST','PUT'], true)) {
        if (!isset($data['origin_country_id']) || !is_numeric($data['origin_country_id'])) {
            ResponseFormatter::error('origin_country_id is required and must be numeric', 422);
            exit;
        }
        $data['origin_country_id'] = (int)$data['origin_country_id'];
    }

    // ================= POST =================
    if ($method === 'POST') {
        $newId = $controller->create($data);
        ResponseFormatter::success(['id' => $newId]);
        exit;
    }

    // ================= PUT =================
    if ($method === 'PUT') {
        $updatedId = $controller->update($data);
        ResponseFormatter::success(['id' => $updatedId]);
        exit;
    }

    // ================= DELETE =================
    if ($method === 'DELETE') {
        if (empty($data['id']) || !is_numeric($data['id'])) {
            ResponseFormatter::error('Missing product ID for deletion', 400);
            exit;
        }
        $deleted = $controller->delete((int)$data['id']);
        ResponseFormatter::success(['deleted' => $deleted]);
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {

    safe_log('warning', 'certificates_products.validation', [
        'error' => $e->getMessage()
    ]);

    ResponseFormatter::error($e->getMessage(), 422);

} catch (Throwable $e) {

    safe_log('critical', 'certificates_products.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);

    ResponseFormatter::error('Internal server error', 500);
}
