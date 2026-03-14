<?php
declare(strict_types=1);

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

date_default_timezone_set('Asia/Riyadh');

// Load dependencies
$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// Load model classes
$modelsPath = API_VERSION_PATH . '/models/seo_meta';
require_once $modelsPath . '/repositories/PdoSeoMetaRepository.php';
require_once $modelsPath . '/validators/SeoMetaValidator.php';
require_once $modelsPath . '/services/SeoMetaService.php';
require_once $modelsPath . '/controllers/SeoMetaController.php';

// CORS headers
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

// Session
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Database connection
if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
    ResponseFormatter::error('Database connection failed', 500);
    exit;
}

try {
    $pdo        = $GLOBALS['ADMIN_DB'];
    $repo       = new PdoSeoMetaRepository($pdo);
    $service    = new SeoMetaService($repo);
    $controller = new SeoMetaController($service);

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $rawInput = file_get_contents('php://input');
    $data     = $rawInput ? json_decode($rawInput, true) : [];
    if ($method === 'POST' && !empty($_POST)) {
        $data = array_merge($data ?: [], $_POST);
    }

    // Detect sub-routes
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path       = parse_url($requestUri, PHP_URL_PATH);

    // /api/seo_meta/translations → translation CRUD
    // /api/seo_meta/stats        → stats
    $isTranslationsRoute = str_contains($path, '/seo_meta/translations');
    $isStatsRoute        = str_contains($path, '/seo_meta/stats');

    // -------------------------------------------------------
    // Sub-route: /api/seo_meta/stats
    // -------------------------------------------------------
    if ($isStatsRoute && $method === 'GET') {
        $result = $controller->stats();
        ResponseFormatter::success($result);
        exit;
    }

    // -------------------------------------------------------
    // Sub-route: /api/seo_meta/translations
    // -------------------------------------------------------
    if ($isTranslationsRoute) {
        switch ($method) {
            case 'GET':
                if (isset($_GET['seo_meta_id'])) {
                    $translations = $controller->getTranslations((int)$_GET['seo_meta_id']);
                    ResponseFormatter::success($translations);
                } else {
                    ResponseFormatter::error('seo_meta_id is required', 400);
                }
                break;

            case 'POST':
            case 'PUT':
                $id = $controller->saveTranslation($data);
                ResponseFormatter::success(['id' => $id], 'Translation saved', 201);
                break;

            case 'DELETE':
                $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
                if ($id <= 0) {
                    ResponseFormatter::error('Translation ID is required', 400);
                    exit;
                }
                $controller->deleteTranslation($id);
                ResponseFormatter::success(null, 'Translation deleted');
                break;

            default:
                ResponseFormatter::error('Method not allowed', 405);
        }
        exit;
    }

    // -------------------------------------------------------
    // Main CRUD: /api/seo_meta
    // -------------------------------------------------------
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $item = $controller->getById((int)$_GET['id']);
                if (!$item) {
                    ResponseFormatter::error('SEO meta not found', 404);
                    exit;
                }
                ResponseFormatter::success($item);
            } elseif (isset($_GET['entity_type'], $_GET['entity_id'])) {
                $tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
                $item = $controller->getByEntity($_GET['entity_type'], (int)$_GET['entity_id'], $tenantId);
                if (!$item) {
                    ResponseFormatter::error('SEO meta not found for this entity', 404);
                    exit;
                }
                ResponseFormatter::success($item);
            } else {
                $limit   = isset($_GET['limit'])   ? (int)$_GET['limit']   : 25;
                $offset  = isset($_GET['offset'])  ? (int)$_GET['offset']  : 0;
                $orderBy = $_GET['order_by']  ?? 'id';
                $orderDir= $_GET['order_dir'] ?? 'DESC';

                $filters = [];
                foreach (['entity_type', 'entity_id', 'tenant_id', 'search'] as $f) {
                    if (isset($_GET[$f]) && $_GET[$f] !== '') {
                        $filters[$f] = $_GET[$f];
                    }
                }

                $result = $controller->list($limit, $offset, $filters, $orderBy, $orderDir);
                ResponseFormatter::success($result);
            }
            break;

        case 'POST':
            $id = $controller->create($data);
            ResponseFormatter::success(['id' => $id], 'SEO meta created', 201);
            break;

        case 'PUT':
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            $controller->update($id, $data);
            ResponseFormatter::success(null, 'SEO meta updated');
            break;

        case 'DELETE':
            $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                ResponseFormatter::error('ID is required', 400);
                exit;
            }
            $controller->delete($id);
            ResponseFormatter::success(null, 'SEO meta deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    error_log("Error in seo_meta: " . $e->getMessage());
    ResponseFormatter::error('Internal Server Error: ' . $e->getMessage(), 500);
}

