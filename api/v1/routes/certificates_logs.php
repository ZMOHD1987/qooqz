<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$certPath = API_VERSION_PATH . '/models/certificates';
require_once $certPath . '/repositories/Pdocertificateslogsrepository.php';
require_once $certPath . '/services/Certificateslogsservice.php';
require_once $certPath . '/controllers/Certificateslogscontroller.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new CertificatesLogsRepository($pdo);
$service    = new CertificatesLogsService($repo);
$controller = new CertificatesLogsController($service);

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
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(204);
        exit;
    }

    // ── GET ───────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        // Single log: GET ?id=123
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get((int)$_GET['id']);
            if ($item === null) {
                ResponseFormatter::error('Log entry not found', 404);
                exit;
            }
            ResponseFormatter::success($item);
            exit;
        }

        // List
        $filters = [
            'request_id'  => isset($_GET['request_id'])  && is_numeric($_GET['request_id'])  ? (int)$_GET['request_id']  : null,
            'user_id'     => isset($_GET['user_id'])     && is_numeric($_GET['user_id'])     ? (int)$_GET['user_id']     : null,
            'action_type' => $_GET['action_type'] ?? null,
            'date_from'   => $_GET['date_from']   ?? null,
            'date_to'     => $_GET['date_to']     ?? null,
        ];
        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
        $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 50;
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

    // ── POST — إنشاء سجل log يدوياً (admin / internal) ───────────────────────
    if ($method === 'POST') {
        $raw   = file_get_contents('php://input');
        $input = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

        if (empty($input['request_id']) || !is_numeric($input['request_id'])) {
            ResponseFormatter::error('request_id is required and must be numeric.', 422);
            exit;
        }
        if (empty($input['action_type'])) {
            ResponseFormatter::error('action_type is required.', 422);
            exit;
        }

        // user_id: يأتي من الجلسة أولاً، أو من الـ body كـ fallback
        $logUserId = $userId > 0 ? $userId : (isset($input['user_id']) ? (int)$input['user_id'] : 0);
        if ($logUserId === 0) {
            ResponseFormatter::error('user_id is required (session or body).', 422);
            exit;
        }

        $newId = $controller->create(
            (int)$input['request_id'],
            $logUserId,
            (string)$input['action_type'],
            $input['notes'] ?? null
        );
        ResponseFormatter::success(['id' => $newId], 'Log entry created.', 201);
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'certificates_logs.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (Throwable $e) {
    safe_log('critical', 'certificates_logs.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
