<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/auctions';
require_once $modelsPath . '/Contracts/AuctionBidsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAuctionBidsRepository.php';
require_once $modelsPath . '/validators/AuctionBidsValidator.php';
require_once $modelsPath . '/services/AuctionBidsService.php';
require_once $modelsPath . '/controllers/AuctionBidsController.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
    $repo       = new PdoAuctionBidsRepository($pdo);
    $service    = new AuctionBidsService($repo);
    $controller = new AuctionBidsController($service);

    $auctionId = isset($_GET['auction_id']) && is_numeric($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
    $page      = isset($_GET['page'])  ? max(1, (int)$_GET['page'])                   : 1;
    $limit     = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit']))        : 25;
    $offset    = ($page - 1) * $limit;
    $orderBy   = $_GET['order_by']  ?? 'id';
    $orderDir  = $_GET['order_dir'] ?? 'DESC';

    $filters = [
        'user_id'    => $_GET['user_id']    ?? null,
        'bid_type'   => $_GET['bid_type']   ?? null,
        'is_winning' => isset($_GET['is_winning']) ? (int)$_GET['is_winning'] : null,
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
                ResponseFormatter::error('auction_id is required', 400);
            }
            break;

        case 'POST':
            $data  = json_decode(file_get_contents('php://input'), true) ?: [];
            $newId = $controller->create($data);
            ResponseFormatter::success(['id' => $newId], 'Bid placed successfully', 201);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $id   = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required for deletion', 400);
                break;
            }
            $deleted = $controller->delete($id);
            ResponseFormatter::success(['deleted' => $deleted], 'Bid deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'auction_bids.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'auction_bids.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'auction_bids.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}

