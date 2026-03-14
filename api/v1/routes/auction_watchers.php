<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/auctions';
require_once $modelsPath . '/Contracts/AuctionWatchersRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAuctionWatchersRepository.php';
require_once $modelsPath . '/validators/AuctionWatchersValidator.php';
require_once $modelsPath . '/services/AuctionWatchersService.php';
require_once $modelsPath . '/controllers/AuctionWatchersController.php';

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
    $repo       = new PdoAuctionWatchersRepository($pdo);
    $service    = new AuctionWatchersService($repo);
    $controller = new AuctionWatchersController($service);

    $auctionId = isset($_GET['auction_id']) && is_numeric($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
    $userId    = isset($_GET['user_id'])    && is_numeric($_GET['user_id'])    ? (int)$_GET['user_id']    : 0;

    switch ($method) {
        case 'GET':
            if ($auctionId > 0 && $userId > 0) {
                $item = $controller->get($auctionId, $userId);
                ResponseFormatter::success($item);
            } elseif ($auctionId > 0) {
                $items = $controller->list($auctionId);
                ResponseFormatter::success($items);
            } else {
                ResponseFormatter::error('auction_id is required', 400);
            }
            break;

        case 'POST':
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = $controller->save($data);
            ResponseFormatter::success(['id' => $id], 'Watcher saved successfully', $method === 'POST' ? 201 : 200);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $aid  = (int)($data['auction_id'] ?? $_GET['auction_id'] ?? 0);
            $uid  = (int)($data['user_id']    ?? $_GET['user_id']    ?? 0);
            if ($aid <= 0 || $uid <= 0) {
                ResponseFormatter::error('auction_id and user_id are required', 400);
                break;
            }
            $deleted = $controller->delete($aid, $uid);
            ResponseFormatter::success(['deleted' => $deleted], 'Watcher removed successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'auction_watchers.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'auction_watchers.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'auction_watchers.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}

