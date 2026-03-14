<?php
declare(strict_types=1);

// ============================================================
// Dependencies
// ============================================================
require_once API_VERSION_PATH . '/models/notifications/notification_recipients/Contracts/NotificationRecipientRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/notifications/notification_recipients/repositories/PdoNotificationRecipientRepository.php';
require_once API_VERSION_PATH . '/models/notifications/notification_recipients/validators/NotificationRecipientValidator.php';
require_once API_VERSION_PATH . '/models/notifications/notification_recipients/services/NotificationRecipientService.php';
require_once API_VERSION_PATH . '/models/notifications/notification_recipients/controllers/NotificationRecipientController.php';

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
$controller = new NotificationRecipientController(
    new NotificationRecipientService(
        new PdoNotificationRecipientRepository($pdo),
        new NotificationRecipientValidator()
    )
);

// ============================================================
// Tenant & Auth
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
        $id = (int) $seg;
        // Check for sub-action after the ID: /recipients/5/mark-read
        $action = $segments[$i + 1] ?? null;
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
            if ($id !== null) {
                $row = $controller->get($id);
                if ($row === null) {
                    ResponseFormatter::error('Recipient not found', 404);
                    break;
                }
                ResponseFormatter::success($row);
                break;
            }

            $filters = [];
            foreach (['notification_id', 'recipient_type', 'recipient_id', 'is_read'] as $key) {
                if (isset($_GET[$key]) && $_GET[$key] !== '') {
                    $filters[$key] = $_GET[$key];
                }
            }

            $page     = max(1, (int) ($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'nr.id';
            $orderDir = $_GET['order_dir'] ?? 'DESC';

            $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir);

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

            // POST /notification_recipients/{id}/mark-read
            if ($id !== null && $action === 'mark-read') {
                $done = $controller->markRead($id);
                ResponseFormatter::success(['marked' => $done], 'Marked as read');
                break;
            }

            // POST /notification_recipients/mark-all-read
            if (isset($segments[array_search('mark-all-read', $segments)])) {
                $affected = $controller->markAllRead($data);
                ResponseFormatter::success(['affected' => $affected], 'All notifications marked as read');
                break;
            }

            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Recipient created successfully', 201);
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

            $updated = $controller->update($data);
            ResponseFormatter::success(['updated' => $updated], 'Recipient updated successfully');
            break;

        // ----------------------------------------------------
        // DELETE
        // ----------------------------------------------------
        case 'DELETE':
            if ($id !== null) {
                $deleted = $controller->delete($id);
                ResponseFormatter::success(['deleted' => $deleted], 'Recipient deleted successfully');
                break;
            }

            $input    = json_decode((string) file_get_contents('php://input'), true) ?? [];
            $deleteId = isset($input['id']) && ctype_digit((string) $input['id']) ? (int) $input['id'] : null;

            if ($deleteId === null) {
                ResponseFormatter::error('Missing id', 400);
                break;
            }

            $deleted = $controller->delete($deleteId);
            ResponseFormatter::success(['deleted' => $deleted], 'Recipient deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('warning', '[NotificationRecipients] Validation failed', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (PDOException $e) {
    safe_log('error', '[NotificationRecipients] Database error', [
        'tenant_id' => $tenantId,
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('A database error occurred.', 500);

} catch (Throwable $e) {
    safe_log('error', '[NotificationRecipients] Unexpected error', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('An unexpected error occurred.', 500);
}
