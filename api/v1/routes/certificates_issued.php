<?php
declare(strict_types=1);

// ── إعدادات العرض (في الإنتاج يُفضل إيقاف عرض الأخطاء) ───────────
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$baseDir = dirname(__DIR__, 2); // العودة إلى api/

// ── تحميل الملفات الأساسية ──────────────────────────────────────
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// ── مسارات مجلد certificates ─────────────────────────────────────
$certPath = API_VERSION_PATH . '/models/certificates';

// ── المستودعات ───────────────────────────────────────────────────
require_once $certPath . '/repositories/PdoCertificatesIssuedRepository.php';

// ── الخدمات ──────────────────────────────────────────────────────
require_once $certPath . '/services/CertificatesIssuedService.php';

// ── المدقق والمتحكم ──────────────────────────────────────────────
require_once $certPath . '/validators/CertificatesIssuedValidator.php';
require_once $certPath . '/controllers/CertificatesIssuedController.php';

// ── الجلسة (اختياري) ────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'cookie_samesite' => 'Lax',
    ]);
}

// ── الاتصال بقاعدة البيانات ──────────────────────────────────────
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

// ── تهيئة الكائنات ───────────────────────────────────────────────
$issuedRepo      = new PdoCertificatesIssuedRepository($pdo);
$issuedService   = new CertificatesIssuedService($issuedRepo);
$issuedValidator = new CertificatesIssuedValidator();
$controller      = new CertificatesIssuedController($issuedService, $issuedValidator);

// ── استخراج tenant_id ────────────────────────────────────────────
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

// ── CORS headers ─────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// ── معالجة طلبات OPTIONS ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── معالجة الطلب الرئيسي ─────────────────────────────────────────
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // GET
    if ($method === 'GET') {
        // حالة جلب عنصر واحد
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $row = $controller->get($tenantId, (int)$_GET['id']);
            ResponseFormatter::success($row);
            exit;
        }

        // قائمة مع فلترة
        $filters = [];

        // الفلاتر الممكنة (يمكن إضافة المزيد حسب الرغبة)
        if (isset($_GET['version_id']) && is_numeric($_GET['version_id'])) {
            $filters['version_id'] = (int)$_GET['version_id'];
        }
        if (isset($_GET['certificate_number']) && $_GET['certificate_number'] !== '') {
            $filters['certificate_number'] = $_GET['certificate_number'];
        }
        if (isset($_GET['verification_code']) && $_GET['verification_code'] !== '') {
            $filters['verification_code'] = $_GET['verification_code'];
        }
        if (isset($_GET['issued_by']) && is_numeric($_GET['issued_by'])) {
            $filters['issued_by'] = (int)$_GET['issued_by'];
        }
        if (isset($_GET['language_code']) && $_GET['language_code'] !== '') {
            $filters['language_code'] = $_GET['language_code'];
        }
        if (isset($_GET['is_cancelled']) && is_numeric($_GET['is_cancelled'])) {
            $filters['is_cancelled'] = (int)$_GET['is_cancelled'];
        }

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

    // قراءة جسم الطلب JSON
    $raw   = file_get_contents('php://input');
    $input = $raw ? (json_decode($raw, true) ?? []) : [];

    // POST
    if ($method === 'POST') {
        $id = $controller->create($tenantId, $input);
        ResponseFormatter::success(['id' => $id], 'تم الإنشاء بنجاح', 201);
        exit;
    }

    // PUT / PATCH
    if ($method === 'PUT' || $method === 'PATCH') {
        $id = $controller->update($tenantId, $input);
        ResponseFormatter::success(['id' => $id], 'تم التحديث بنجاح');
        exit;
    }

    // DELETE
    if ($method === 'DELETE') {
        $id = $input['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            ResponseFormatter::error('معرف غير موجود أو غير صالح للحذف', 400);
            exit;
        }
        $controller->delete($tenantId, (int)$id);
        ResponseFormatter::success(['deleted' => true], 'تم الحذف بنجاح');
        exit;
    }

    // طريقة غير مدعومة
    ResponseFormatter::error('الطريقة غير مدعومة', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'cert_issued.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (RuntimeException $e) {
    safe_log('error', 'cert_issued.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);

} catch (Throwable $e) {
    safe_log('critical', 'cert_issued.fatal', [
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
