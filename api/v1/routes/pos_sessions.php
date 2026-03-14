<?php
declare(strict_types=1);

/**
 * api/routes/pos_sessions.php
 * POS Session Management API
 *
 * GET    ?action=current          – active session for entity
 * GET    ?action=list             – paginated sessions
 * GET    ?id=<n>                  – single session
 * POST   action=open              – open new session
 * POST   action=close             – close session
 * POST   action=create_order      – create POS order with items
 * GET    ?action=session_orders   – orders for a session
 */

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// MVC layer
require_once $baseDir . '/v1/models/pos_sessions/Contracts/PosSessionsRepositoryInterface.php';
require_once $baseDir . '/v1/models/pos_sessions/validators/PosSessionsValidator.php';
require_once $baseDir . '/v1/models/pos_sessions/repositories/PdoPosSessionsRepository.php';
require_once $baseDir . '/v1/models/pos_sessions/services/PosSessionsService.php';
require_once $baseDir . '/v1/models/pos_sessions/controllers/PosSessionsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input');
$data   = ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?? []) : [];
$action = $_GET['action'] ?? $data['action'] ?? '';

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────
function pos_json_ok($d = [], int $code = 200): void
{
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    $out = is_array($d) ? array_merge(['success' => true], $d) : ['success' => true, 'data' => $d];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

function pos_json_error(string $m, int $code = 400): void
{
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    echo json_encode(['success' => false, 'message' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────
// Wire up MVC
// ─────────────────────────────────────────────
$repository = new PdoPosSessionsRepository($pdo);
$validator  = new PosSessionsValidator();
$service    = new PosSessionsService($repository, $validator);
$controller = new PosSessionsController($service);

// ─────────────────────────────────────────────
// OPTIONS pre-flight
// ─────────────────────────────────────────────
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(204);
    exit;
}

try {
    // ═══════════════════════════════════════
    // GET actions
    // ═══════════════════════════════════════
    if ($method === 'GET') {

        // GET ?action=current[&entity_id=N]
        if ($action === 'current') {
            $entityId = isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])
                ? (int)$_GET['entity_id'] : null;
            pos_json_ok($controller->current($tenantId, $entityId));
        }

        // GET ?action=list[&page&per_page&entity_id&status]
        if ($action === 'list') {
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
            $offset  = ($page - 1) * $perPage;

            $filters = [];
            if (isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
                $filters['entity_id'] = (int)$_GET['entity_id'];
            }
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['cashier_user_id']) && is_numeric($_GET['cashier_user_id'])) {
                $filters['cashier_user_id'] = (int)$_GET['cashier_user_id'];
            }

            $result = $controller->list($tenantId, $perPage, $offset, $filters);
            $result['page'] = $page;
            pos_json_ok($result);
        }

        // GET ?action=session_orders&session_id=N[&date_from=Y-m-d&date_to=Y-m-d&payment_method=cash]
        if ($action === 'session_orders') {
            $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
            if (!$sessionId) pos_json_error('session_id required');
            $filters = [];
            if (!empty($_GET['date_from'])) {
                $filters['date_from'] = preg_replace('/[^0-9\-]/', '', $_GET['date_from']);
            }
            if (!empty($_GET['date_to'])) {
                $filters['date_to'] = preg_replace('/[^0-9\-]/', '', $_GET['date_to']);
            }
            if (!empty($_GET['payment_method'])) {
                $filters['payment_method'] = preg_replace('/[^a-z_]/', '', strtolower($_GET['payment_method']));
            }
            pos_json_ok($controller->sessionOrders($tenantId, $sessionId, $filters));
        }

        // GET ?id=N  – single session
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            pos_json_ok($controller->get($tenantId, (int)$_GET['id']));
        }

        pos_json_error('Unknown action', 400);
    }

    // ═══════════════════════════════════════
    // POST actions
    // ═══════════════════════════════════════
    if ($method === 'POST') {

        // POST action=open
        if ($action === 'open') {
            pos_json_ok($controller->open($tenantId, $data), 201);
        }

        // POST action=close
        if ($action === 'close') {
            pos_json_ok($controller->close($tenantId, $data));
        }

        // POST action=create_order
        if ($action === 'create_order') {
            pos_json_ok($controller->createOrder($tenantId, $data), 201);
        }

        pos_json_error('Unknown action', 400);
    }

    pos_json_error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    pos_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    pos_json_error($e->getMessage(), 404);
} catch (\Throwable $e) {
    safe_log('critical', 'pos_sessions.fatal', ['error' => $e->getMessage()]);
    pos_json_error('Internal Server Error: ' . $e->getMessage(), 500);
}
