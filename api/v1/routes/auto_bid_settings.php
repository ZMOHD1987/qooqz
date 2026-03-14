<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/auctions';
require_once $modelsPath . '/Contracts/AutoBidSettingsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAutoBidSettingsRepository.php';
require_once $modelsPath . '/validators/AutoBidSettingsValidator.php';
require_once $modelsPath . '/services/AutoBidSettingsService.php';
require_once $modelsPath . '/controllers/AutoBidSettingsController.php';

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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo        = $GLOBALS['ADMIN_DB'];
    $repo       = new PdoAutoBidSettingsRepository($pdo);
    $service    = new AutoBidSettingsService($repo);
    $controller = new AutoBidSettingsController($service);

    $auctionId = isset($_GET['auction_id']) && is_numeric($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
    $page      = isset($_GET['page'])  ? max(1, (int)$_GET['page'])                   : 1;
    $limit     = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit']))        : 25;
    $offset    = ($page - 1) * $limit;
    $orderBy   = $_GET['order_by']  ?? 'id';
    $orderDir  = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'user_id'   => $_GET['user_id']   ?? null,
        'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
    ];

    switch ($method) {
        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $item = $controller->get((int)$_GET['id']);
                ResponseFormatter::success($item);
            } elseif ($auctionId > 0) {
                $result = $controller->list($auctionId, $limit, $offset, $filters, $orderBy, $orderDir);
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $result['total'],
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $result['total'] > 0 ? (int)ceil($result['total'] / $limit) : 0,
                    ],
                ]);
            } else {
                ResponseFormatter::error('auction_id or id is required', 400);
            }
            break;

        case 'POST':
            $data  = json_decode(file_get_contents('php://input'), true) ?: [];
            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Auto-bid setting created successfully', 201);
            break;

        case 'PUT':
            $data      = json_decode(file_get_contents('php://input'), true) ?: [];
            $updatedId = $controller->update($data);
            ResponseFormatter::success(['id' => $updatedId], 'Auto-bid setting updated successfully');
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required for deletion', 400);
                break;
            }
            $deleted = $controller->delete($id);
            ResponseFormatter::success(['deleted' => $deleted], 'Auto-bid setting deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'auto_bid_settings.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'auto_bid_settings.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'auto_bid_settings.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}

