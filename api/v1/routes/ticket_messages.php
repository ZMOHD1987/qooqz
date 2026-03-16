<?php
declare(strict_types=1);

 $baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

 $modelsPath = API_VERSION_PATH . '/models/tickets';
require_once $modelsPath . '/Contracts/TicketMessagesRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoTicketMessagesRepository.php';
require_once $modelsPath . '/validators/TicketMessagesValidator.php';
require_once $modelsPath . '/services/TicketMessagesService.php';
require_once $modelsPath . '/controllers/TicketMessagesController.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

 $pdo    = $GLOBALS['ADMIN_DB'];
 $method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

 $tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

try {
    $repo       = new PdoTicketMessagesRepository($pdo);
    $service    = new TicketMessagesService($repo);
    $controller = new TicketMessagesController($service);

    $lang     = $_GET['lang']      ?? 'ar';
    $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page'])                   : 1;
    $limit    = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit']))        : 50; // More messages per page usually
    $offset   = ($page - 1) * $limit;
    
    // Messages usually make sense in ascending order (oldest to newest)
    $orderBy  = $_GET['order_by']  ?? 'created_at'; 
    $orderDir = $_GET['order_dir'] ?? 'ASC';

    $filters = [
        'ticket_id'      => isset($_GET['ticket_id']) && is_numeric($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null,
        'sender_user_id' => isset($_GET['sender_user_id']) && is_numeric($_GET['sender_user_id']) ? (int)$_GET['sender_user_id'] : null,
        'is_internal'    => isset($_GET['is_internal']) ? (int)$_GET['is_internal'] : null,
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get($tenantId, (int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            } else {
                // Require ticket_id for listing usually, but we allow all if admin
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0,
                    ],
                ]);
            }
            break;

        case 'POST':
            $data  = json_decode(file_get_contents('php://input'), true) ?: [];
            // Auto-add sender if not present (optional logic based on auth)
            if (empty($data['sender_user_id']) && isset($_SESSION['user_id'])) {
                $data['sender_user_id'] = (int)$_SESSION['user_id'];
            }
            
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Message sent successfully', 201);
            break;

        case 'PUT':
            $data      = json_decode(file_get_contents('php://input'), true) ?: [];
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Message updated successfully');
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required for deletion', 400);
                break;
            }
            $deleted = $controller->delete($tenantId, $id);
            ResponseFormatter::success(['deleted' => $deleted], 'Message deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'ticket_messages.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'ticket_messages.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'ticket_messages.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error('An unexpected error occurred', 500);
}