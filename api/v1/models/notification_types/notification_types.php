<?php
declare(strict_types=1);

// ============================================================
// Dependencies
// ============================================================
require_once API_VERSION_PATH . '/models/notifications/notification_types/Contracts/NotificationTypeRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/notifications/notification_types/repositories/PdoNotificationTypeRepository.php';
require_once API_VERSION_PATH . '/models/notifications/notification_types/validators/NotificationTypeValidator.php';
require_once API_VERSION_PATH . '/models/notifications/notification_types/services/NotificationTypeService.php';
require_once API_VERSION_PATH . '/models/notifications/notification_types/controllers/NotificationTypeController.php';

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
$controller = new NotificationTypeController(
    new NotificationTypeService(
        new PdoNotificationTypeRepository($pdo),
        new NotificationTypeValidator()
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
$codeParam = null;

foreach ($segments as $i => $seg) {
    if ($seg === 'code' && isset($segments[$i + 1])) {
        $codeParam = $segments[$i + 1];
        break;
    }
    if (ctype_digit($seg) && (int) $seg > 0) {
        $id = (int) $seg;
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
            if ($codeParam !== null) {
                $type = $controller->getByCode($codeParam);
                if ($type === null) {
                    ResponseFormatter::error('Notification type not found', 404);
                    break;
                }
                ResponseFormatter::success($type);
                break;
            }

            if ($id !== null) {
                $type = $controller->get($id);
                if ($type === null) {
                    ResponseFormatter::error('Notification type not found', 404);
                    break;
                }
                ResponseFormatter::success($type);
                break;
            }

            $filters  = [];
            if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
                $filters['is_active'] = $_GET['is_active'];
            }

            $page     = max(1, (int) ($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'nt.id';
            $orderDir = $_GET['order_dir'] ?? 'ASC';

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
            $data  = json_decode((string) file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Notification type created successfully', 201);
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
            ResponseFormatter::success(['updated' => $updated], 'Notification type updated successfully');
            break;

        // ----------------------------------------------------
        // DELETE
        // ----------------------------------------------------
        case 'DELETE':
            if ($id !== null) {
                $deleted = $controller->delete($id);
                ResponseFormatter::success(['deleted' => $deleted], 'Notification type deleted successfully');
                break;
            }

            $input    = json_decode((string) file_get_contents('php://input'), true) ?? [];
            $deleteId = isset($input['id']) && ctype_digit((string) $input['id']) ? (int) $input['id'] : null;

            if ($deleteId === null) {
                ResponseFormatter::error('Missing id', 400);
                break;
            }

            $deleted = $controller->delete($deleteId);
            ResponseFormatter::success(['deleted' => $deleted], 'Notification type deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('warning', '[NotificationTypes] Validation failed', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (PDOException $e) {
    safe_log('error', '[NotificationTypes] Database error', [
        'tenant_id' => $tenantId,
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('A database error occurred.', 500);

} catch (Throwable $e) {
    safe_log('error', '[NotificationTypes] Unexpected error', [
        'tenant_id' => $tenantId,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error('An unexpected error occurred.', 500);
}
