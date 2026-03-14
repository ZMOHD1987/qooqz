<?php
declare(strict_types=1);

// ============================================================
// Dependencies
// ============================================================
require_once API_VERSION_PATH . '/models/notifications/notification_counters/Contracts/NotificationCounterRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/notifications/notification_counters/repositories/PdoNotificationCounterRepository.php';
require_once API_VERSION_PATH . '/models/notifications/notification_counters/validators/NotificationCounterValidator.php';
require_once API_VERSION_PATH . '/models/notifications/notification_counters/services/NotificationCounterService.php';
require_once API_VERSION_PATH . '/models/notifications/notification_counters/controllers/NotificationCounterController.php';

// ============================================================
// Bootstrap guard
// ============================================================
if (!defined('API_VERSION_PATH')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// ============================================================
// Database
// ============================================================
/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Service unavailable', 503);
    exit;
}

// ============================================================
// Wiring
// ============================================================
$controller = new NotificationCounterController(
    new NotificationCounterService(
        new PdoNotificationCounterRepository($pdo),
        new NotificationCounterValidator()
    )
);

// ============================================================
// Tenant resolution
// ============================================================
$tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
if ($tenantId === 0) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

// ============================================================
// Request parsing
// ============================================================
$method   = $_SERVER['REQUEST_METHOD'];
$uriPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uriPath, '/'));
$id       = null;
$action   = null;

foreach ($segments as $i => $seg) {
    if (ctype_digit($seg) && (int) $seg > 0) {
        $id     = (int) $seg;
        $action = $segments[$i + 1] ?? null;
        break;
    }
    if (in_array($seg, ['unread-count', 'increment', 'reset'], true)) {
        $action = $seg;
        break;
    }
}

// ============================================================
// Route dispatch
// ============================================================
try {

    switch ($method) {

        // ----------------------------------------------------
        // GET
        // ----------------------------------------------------
        case 'GET':
            // GET /notification_counters/unread-count?recipient_type=user&recipient_id=5
            if ($action === 'unread-count' || isset($_GET['recipient_type'])) {
                $count = $controller->getUnreadCount($tenantId, $_GET);
                ResponseFormatter::success(['unread_count' => $count]);
                break;
            }

            if ($id !== null) {
                $row = $controller->get($tenantId, $id);
                if ($row === null) {
                    ResponseFormatter::error('Counter not found', 404);
                    break;
                }
                ResponseFormatter::success($row);
                break;
            }

            $filters  = [];
            foreach (['recipient_type', 'recipient_id'] as $key) {
                if (isset($_GET[$key]) && $_GET[$key] !== '') {
                    $filters[$key] = $_GET[$key];
                }
            }
            $page     = max(1, (int) ($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'nc.id';
            $orderDir = $_GET['order_dir'] ?? 'DESC';

            $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
            ResponseFormatter::success([
                'items' => $result['items'],
                'meta'  => [
                    'total'       => $result['total'],
                    'page'        => $page,
                    'per_page'    => $limit,
                    'total_pages' => $result['total'] > 0 ? (int) ceil($result['total'] / $limit) : 0,
                ],
            ]);
            break;

        // ----------------------------------------------------
        // POST
        // ----------------------------------------------------
        case 'POST':
            $data = json_decode((string) file_get_contents('php://input'), true) ?? [];

            if ($action === 'increment') {
                $done = $controller->increment($tenantId, $data);
                ResponseFormatter::success(['incremented' => $done], 'Counter incremented');
                break;
            }

            if ($action === 'reset') {
                $done = $controller->reset($tenantId, $data);
                ResponseFormatter::success(['reset' => $done], 'Counter reset to zero');
                break;
            }

            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Counter created successfully', 201);
            break;

        // ----------------------------------------------------
        // PUT
        // ----------------------------------------------------
        case 'PUT':
            $data = json_decode((string) file_get_contents('php://input'), true) ?? [];

            if ($id !== null && empty($data['id'])) {
                $data['id'] = $id;
            }
            if (empty($data['id'])) {
                ResponseFormatter::error('ID is required for update', 422);
                break;
            }

            $updated = $controller->update($tenantId, $data);
            ResponseFormatter::success(['updated' => $updated], 'Counter updated successfully');
            break;

        // ----------------------------------------------------
        // DELETE
        // ----------------------------------------------------
        case 'DELETE':
            if ($id !== null) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Counter deleted successfully');
                break;
            }

            $input    = json_decode((string) file_get_contents('php://input'), true) ?? [];
            $deleteId = isset($input['id']) && ctype_digit((string) $input['id']) ? (int) $input['id'] : null;

            if ($deleteId === null) {
                ResponseFormatter::error('Missing id', 400);
                break;
            }

            $deleted = $controller->delete($tenantId, $deleteId);
            ResponseFormatter::success(['deleted' => $deleted], 'Counter deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('warning', '[NotificationCounters] Validation failed', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (PDOException $e) {
    safe_log('error', '[NotificationCounters] Database error', [
        'tenant_id' => $tenantId,
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('A database error occurred.', 500);

} catch (Throwable $e) {
    safe_log('error', '[NotificationCounters] Unexpected error', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('An unexpected error occurred.', 500);
}
