<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';
require_once $certPath . '/repositories/PdoCertificatesProductsTranslationsRepository.php';
require_once $certPath . '/validators/CertificatesProductsTranslationsValidator.php';
require_once $certPath . '/services/CertificatesProductsTranslationsService.php';
require_once $certPath . '/controllers/CertificatesProductsTranslationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new CertificatesProductsTranslationsRepository($pdo);
$validator  = new CertificatesProductsTranslationsValidator();
$service    = new CertificatesProductsTranslationsService($repo, $validator);
$controller = new CertificatesProductsTranslationsController($service);

/**
 * يُبقي فقط الحقول الموجودة في الجدول
 * brand وأي حقل آخر يُحذف هنا قبل أن يصل لأي طبقة أخرى
 */
function stripToKnownFields(array $data): array
{
    return array_intersect_key($data, array_flip(['id', 'product_id', 'language_code', 'name']));
}

// =============================================================================
// Handle request
// =============================================================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(204);
        exit;
    }

    // ── GET ───────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get((int)$_GET['id']);
            if ($item === null) { ResponseFormatter::error('Record not found', 404); exit; }
            ResponseFormatter::success($item);
            exit;
        }

        $filters = [
            'product_id'    => isset($_GET['product_id'])    && is_numeric($_GET['product_id']) ? (int)$_GET['product_id'] : null,
            'language_code' => isset($_GET['language_code']) && $_GET['language_code'] !== ''   ? $_GET['language_code']   : null,
            'name'          => isset($_GET['name'])          && $_GET['name'] !== ''            ? $_GET['name']            : null,
        ];
        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
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

    // ── Body parse ────────────────────────────────────────────────────────────
    $raw  = file_get_contents('php://input');
    $body = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    // ── POST ──────────────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $data  = stripToKnownFields($body); // brand يُحذف هنا
        $newId = $controller->create($data);
        ResponseFormatter::success(['id' => $newId]);
        exit;
    }

    // ── PUT ───────────────────────────────────────────────────────────────────
    if ($method === 'PUT') {
        $data      = stripToKnownFields($body);
        $updatedId = $controller->update($data);
        ResponseFormatter::success(['id' => $updatedId]);
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        if (!empty($body['id']) && is_numeric($body['id'])) {
            $deleted = $controller->delete((int)$body['id']);
            ResponseFormatter::success(['deleted' => $deleted]);
            exit;
        }
        if (!empty($body['product_id']) && !empty($body['language_code'])) {
            $deleted = $controller->deleteByProductAndLang(
                (int)$body['product_id'],
                (string)$body['language_code']
            );
            ResponseFormatter::success(['deleted' => $deleted]);
            exit;
        }
        ResponseFormatter::error('Provide id OR (product_id + language_code) for deletion.', 400);
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'cert_product_translations.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'cert_product_translations.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'cert_product_translations.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
