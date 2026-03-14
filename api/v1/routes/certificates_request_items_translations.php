<?php
declare(strict_types=1);

 $baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

 $modelsPath = API_VERSION_PATH . '/models/certificates';
require_once $modelsPath . '/repositories/PdoCertificatesRequestItemsTranslationsRepository.php';
require_once $modelsPath . '/validators/CertificatesRequestItemsTranslationsValidator.php';
require_once $modelsPath . '/services/CertificatesRequestItemsTranslationsService.php';
require_once $modelsPath . '/controllers/CertificatesRequestItemsTranslationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

 $pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

 $repo = new PdoCertificatesRequestItemsTranslationsRepository($pdo);
 $service = new CertificatesRequestItemsTranslationsService($repo, new CertificatesRequestItemsTranslationsValidator());
 $controller = new CertificatesRequestItemsTranslationsController($service);

// ================================
// Tenant & Auth check
// ================================
 $tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

// ================================
// Handle request
// ================================
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [
        'id'            => $_GET['id'] ?? null,
        'item_id'       => $_GET['item_id'] ?? null,
        'language_code' => $_GET['language_code'] ?? null,
    ];

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                // Single item
                $item = $controller->get($tenantId, (int)$_GET['id']);
                if ($item) {
                    ResponseFormatter::success($item);
                } else {
                    ResponseFormatter::error('Translation not found', 404);
                }
            } else {
                // List
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta'  => [
                        'total'       => $total,
                        'page'        => $page,
                        'per_page'    => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from'        => $total > 0 ? $offset + 1 : 0,
                        'to'          => $total > 0 ? min($offset + $limit, $total) : 0
                    ]
                ]);
            }
            break;

        case 'POST':
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing ID for update', 400);
                exit;
            }
            $updatedId = $controller->update($tenantId, $data);
            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            // Check ID in GET or Body
            $idToDelete = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['id']) ? (int)$data['id'] : null);
            
            if (!$idToDelete) {
                ResponseFormatter::error('Missing ID for deletion', 400);
                exit;
            }
            
            $deleted = $controller->delete($tenantId, $idToDelete);
            if ($deleted) {
                ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
            } else {
                ResponseFormatter::error('Translation not found or already deleted', 404);
            }
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','cert_items_translations.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','cert_items_translations.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical','cert_items_translations.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error('Internal server error', 500);
}
