<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/auctions';
require_once $modelsPath . '/Contracts/AuctionTranslationsRepositoryInterface.php';
require_once $modelsPath . '/repositories/PdoAuctionTranslationsRepository.php';
require_once $modelsPath . '/validators/AuctionTranslationsValidator.php';
require_once $modelsPath . '/services/AuctionTranslationsService.php';
require_once $modelsPath . '/controllers/AuctionTranslationsController.php';

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
    $repo       = new PdoAuctionTranslationsRepository($pdo);
    $service    = new AuctionTranslationsService($repo);
    $controller = new AuctionTranslationsController($service);

    $auctionId    = isset($_GET['auction_id']) && is_numeric($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
    $languageCode = $_GET['language_code'] ?? '';

    switch ($method) {
        case 'GET':
            if ($auctionId > 0 && $languageCode !== '') {
                $item = $controller->get($auctionId, $languageCode);
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
            ResponseFormatter::success(['id' => $id], 'Translation saved successfully', $method === 'POST' ? 201 : 200);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $aid  = (int)($data['auction_id']    ?? $_GET['auction_id']    ?? 0);
            $lang = (string)($data['language_code'] ?? $_GET['language_code'] ?? '');
            if ($aid <= 0 || $lang === '') {
                ResponseFormatter::error('auction_id and language_code are required', 400);
                break;
            }
            $deleted = $controller->delete($aid, $lang);
            ResponseFormatter::success(['deleted' => $deleted], 'Translation deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'auction_translations.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'auction_translations.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    safe_log('critical', 'auction_translations.fatal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}

