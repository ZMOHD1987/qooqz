<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/notification';
require_once $modelsPath . '/repositories/PdoNotificationTypesRepository.php';
require_once $modelsPath . '/validators/NotificationTypesValidator.php';
require_once $modelsPath . '/services/NotificationTypesService.php';
require_once $modelsPath . '/controllers/NotificationTypesController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// No tenant isolation for notification_types (global table)
$repo       = new PdoNotificationTypesRepository($pdo);
$validator  = new NotificationTypesValidator();
$service    = new NotificationTypesService($repo, $validator);
$controller = new NotificationTypesController($service);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get((int)$_GET['id']);
            ResponseFormatter::success($item);
            exit;
        }

        $filters = [
            'code'      => $_GET['code']      ?? null,
            'name'      => $_GET['name']      ?? null,
            'is_active' => isset($_GET['is_active']) && is_numeric($_GET['is_active']) ? (int)$_GET['is_active'] : null,
        ];

        $orderBy  = $_GET['order_by']  ?? 'id';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page']) : 1;
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

    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    if ($method === 'POST') {
        $newId = $controller->create($data);
        ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
        exit;
    }

    if ($method === 'PUT') {
        $updatedId = $controller->update($data);
        ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
        exit;
    }

    if ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            ResponseFormatter::error('Missing or invalid id for deletion', 400);
            exit;
        }
        $controller->delete((int)$id);
        ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'notification_types.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'notification_types.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'notification_types.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'GET'   => $_GET,
    ]);
    ResponseFormatter::error('Internal server error', 500);
}
