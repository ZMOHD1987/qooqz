<?php
declare(strict_types=1);

// ── مهم: في الإنتاج لا تعرض الأخطاء على الشاشة ────────────────────────────────
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL); // لكن لا تعرضها — سجلها فقط

$baseDir = dirname(__DIR__, 2);

// ── Bootstrap & Helpers ────────────────────────────────────────────────────────
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// ── Paths ───────────────────────────────────────────────────────────────────────
$certPath = API_VERSION_PATH . '/models/certificates';

// ── Repositories (افتراض أن الأسماء تم تصحيحها) ────────────────────────────────
require_once $certPath . '/repositories/PdoCertificatesVersionsRepository.php';
require_once $certPath . '/repositories/Pdocertificateslogsrepository.php';          // ← الاسم الصحيح

// ── Services ───────────────────────────────────────────────────────────────────
require_once $certPath . '/services/CertificatesVersionsService.php';
require_once $certPath . '/services/Certificateslogsservice.php';

// ── Controller ─────────────────────────────────────────────────────────────────
require_once $certPath . '/controllers/CertificatesVersionsController.php';

// ── Session (فقط إذا كنت بحاجة إليها فعلاً) ────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'cookie_samesite' => 'Lax',
    ]);
}

// ── Database Connection ────────────────────────────────────────────────────────
/** @var PDO|null $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

if (!$pdo instanceof PDO) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'خطأ داخلي في الخادم',
        'error_code' => 'db_not_initialized'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Initialize Services ────────────────────────────────────────────────────────
$logsRepo    = new CertificatesLogsRepository($pdo);
$logsService = new CertificatesLogsService($logsRepo);

$versionsRepo = new PdoCertificatesVersionsRepository($pdo);
$versionsService = new CertificatesVersionsService($versionsRepo, $logsRepo);
$controller   = new CertificatesVersionsController($versionsService);

// ── Tenant Resolution ──────────────────────────────────────────────────────────
$tenantId = null;

if (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
    $tenantId = (int)$_GET['tenant_id'];
} elseif (isset($_SESSION['tenant_id']) && is_numeric($_SESSION['tenant_id'])) {
    $tenantId = (int)$_SESSION['tenant_id'];
}

if ($tenantId === null) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'tenant_id مطلوب',
        'error_code' => 'missing_tenant'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Current User ───────────────────────────────────────────────────────────────
$sessionUser = $_SESSION['user'] ?? [];
$userId = isset($sessionUser['id']) && is_numeric($sessionUser['id'])
    ? (int)$sessionUser['id']
    : 0;

// ── CORS Headers (للـ API) ─────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');           // غيّرها لاحقاً إلى نطاقات محددة
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// ── Handle OPTIONS preflight ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Main Request Handling ─────────────────────────────────────────────────────
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ── GET ────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $row = $controller->get($tenantId, (int)$_GET['id']);
            ResponseFormatter::success($row);
            exit;
        }

        $filters = [
            'request_id' => isset($_GET['request_id']) && is_numeric($_GET['request_id'])
                ? (int)$_GET['request_id']
                : null,
        ];

        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = strtoupper($_GET['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $page   = max(1, (int)($_GET['page']  ?? 1));
        $limit  = min(1000, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total  = $result['total'] ?? 0;

        ResponseFormatter::success([
            'items' => $result['items'] ?? [],
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

    // ── Parse JSON Body ────────────────────────────────────────────────────
    $raw   = file_get_contents('php://input');
    $input = $raw ? (json_decode($raw, true) ?? []) : [];

    // ── POST ───────────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $row = $controller->create($tenantId, $userId, $input);
        ResponseFormatter::success($row, 'تم الإنشاء بنجاح', 201);
        exit;
    }

    // ── PUT ────────────────────────────────────────────────────────────────
    if ($method === 'PUT' || $method === 'PATCH') {
        $row = $controller->update($tenantId, $userId, $input);
        ResponseFormatter::success($row, 'تم التحديث بنجاح');
        exit;
    }

    // ── DELETE ─────────────────────────────────────────────────────────────
    if ($method === 'DELETE') {
        $id = $input['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            ResponseFormatter::error('معرف غير موجود أو غير صالح للحذف', 400);
            exit;
        }
        $controller->delete($tenantId, $userId, (int)$id);
        ResponseFormatter::success(['deleted' => true], 'تم الحذف بنجاح');
        exit;
    }

    ResponseFormatter::error('الطريقة غير مدعومة', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'cert_versions.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (RuntimeException $e) {
    safe_log('error', 'cert_versions.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);

} catch (Throwable $e) {
    safe_log('critical', 'cert_versions.fatal', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => $e->getTraceAsString(),
        'method'  => $method,
        'tenant'  => $tenantId,
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ داخلي في الخادم',
        'error_code' => 'internal_server_error'
    ], JSON_UNESCAPED_UNICODE);
}
