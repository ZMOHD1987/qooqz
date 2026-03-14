<?php
declare(strict_types=1);

// ============================================================
// Dependencies — Interface must be loaded before Repository
// ============================================================
require_once API_VERSION_PATH . '/models/products/Contracts/ProductRelationsRepositoryInterface.php';
require_once API_VERSION_PATH . '/models/products/repositories/PdoProductRelationsRepository.php';
require_once API_VERSION_PATH . '/models/products/validators/ProductRelationsValidator.php';
require_once API_VERSION_PATH . '/models/products/services/ProductRelationsService.php';
require_once API_VERSION_PATH . '/models/products/controllers/ProductRelationsController.php';

// ============================================================
// Bootstrap (already loaded by Kernel — guard against direct access)
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
$controller = new ProductRelationsController(
    new ProductRelationsService(
        new PdoProductRelationsRepository($pdo),
        new ProductRelationsValidator()
    )
);

// ============================================================
// Tenant resolution
// ============================================================
$tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
if ($tenantId === 0) {
    ResponseFormatter::error('Unauthorized', 401);
    exit;
}

// ============================================================
// Request parsing
// ============================================================
$method = $_SERVER['REQUEST_METHOD'];

// Extract numeric ID from URI segments (e.g. /product_relations/42)
$uriPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uriPath, '/'));
$id       = null;
foreach ($segments as $seg) {
    if (ctype_digit($seg) && (int)$seg > 0) {
        $id = (int)$seg;
        break;
    }
}

// Shared query-string helpers
$lang         = in_array($_GET['lang'] ?? 'ar', ['ar', 'en'], true) ? ($_GET['lang'] ?? 'ar') : 'ar';
$productId    = isset($_GET['product_id']) && ctype_digit((string)$_GET['product_id'])
                    ? (int)$_GET['product_id'] : null;
$relationType = isset($_GET['relation_type']) && $_GET['relation_type'] !== ''
                    ? (string)$_GET['relation_type'] : null;

// ============================================================
// Route dispatch
// ============================================================
try {

    switch ($method) {

        // ----------------------------------------------------
        // GET  /product_relations?product_id=X   → related products for a product
        // GET  /product_relations/42              → single relation
        // GET  /product_relations                 → paginated list
        // ----------------------------------------------------
        case 'GET':

            if ($productId !== null) {
                // Related products for a specific product
                $data = $controller->getRelatedProducts($tenantId, $productId, $relationType, $lang);
                ResponseFormatter::success($data);
                break;
            }

            if ($id !== null) {
                // Single relation
                $relation = $controller->get($tenantId, $id, $lang);
                if ($relation === null) {
                    ResponseFormatter::error('Relation not found', 404);
                    break;
                }
                ResponseFormatter::success($relation);
                break;
            }

            // Paginated list
            $filters = [];
            foreach (['product_id', 'related_product_id', 'relation_type'] as $key) {
                if (isset($_GET[$key]) && $_GET[$key] !== '') {
                    $filters[$key] = $_GET[$key];
                }
            }

            $page     = max(1, (int)($_GET['page']  ?? 1));
            $limit    = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset   = ($page - 1) * $limit;
            $orderBy  = $_GET['order_by']  ?? 'pr.id';
            $orderDir = $_GET['order_dir'] ?? 'DESC';

            $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);

            ResponseFormatter::success([
                'items' => $result['items'],
                'meta'  => [
                    'total'       => $result['total'],
                    'page'        => $page,
                    'per_page'    => $limit,
                    'total_pages' => $result['total'] > 0
                                        ? (int)ceil($result['total'] / $limit)
                                        : 0,
                ],
            ]);
            break;

        // ----------------------------------------------------
        // POST  /product_relations   → create
        // ----------------------------------------------------
        case 'POST':
            $data  = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $newId = $controller->create($tenantId, $data);
            ResponseFormatter::success(['id' => $newId], 'Relation created successfully', 201);
            break;

        // ----------------------------------------------------
        // PUT  /product_relations   → update (id in body)
        // ----------------------------------------------------
        case 'PUT':
            $data = json_decode((string)file_get_contents('php://input'), true) ?? [];

            // Allow ID from URI or body
            if ($id !== null && empty($data['id'])) {
                $data['id'] = $id;
            }

            if (empty($data['id'])) {
                ResponseFormatter::error('Relation ID is required for update', 422);
                break;
            }

            $updated = $controller->update($tenantId, $data);
            ResponseFormatter::success(['updated' => $updated], 'Relation updated successfully');
            break;

        // ----------------------------------------------------
        // DELETE  /product_relations?product_id=X   → delete by product
        // DELETE  /product_relations/42              → delete single
        // DELETE  /product_relations  (id in body)   → delete single
        // ----------------------------------------------------
        case 'DELETE':

            if ($productId !== null) {
                $deleted = $controller->deleteByProduct($tenantId, $productId, $relationType);
                ResponseFormatter::success(['deleted' => $deleted], 'Relations deleted successfully');
                break;
            }

            if ($id !== null) {
                $deleted = $controller->delete($tenantId, $id);
                ResponseFormatter::success(['deleted' => $deleted], 'Relation deleted successfully');
                break;
            }

            // Fallback: id in request body
            $input    = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $deleteId = isset($input['id']) && ctype_digit((string)$input['id'])
                            ? (int)$input['id'] : null;

            if ($deleteId === null) {
                ResponseFormatter::error('Missing id or product_id', 400);
                break;
            }

            $deleted = $controller->delete($tenantId, $deleteId);
            ResponseFormatter::success(['deleted' => $deleted], 'Relation deleted successfully');
            break;

        // ----------------------------------------------------
        // Unsupported methods
        // ----------------------------------------------------
        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    safe_log('warning', '[ProductRelations] Validation failed', [
        'tenant_id' => $tenantId,
        'method'    => $method,
        'error'     => $e->getMessage(),
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (PDOException $e) {
    safe_log('error', '[ProductRelations] Database error', [
        'tenant_id' => $tenantId,
        'method'    => $method,
        'code'      => $e->getCode(),
        'error'     => $e->getMessage(),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
    ]);
    ResponseFormatter::error('A database error occurred. Please try again later.', 500);

} catch (Throwable $e) {
    safe_log('error', '[ProductRelations] Unexpected error', [
        'tenant_id' => $tenantId,
        'method'    => $method,
        'error'     => $e->getMessage(),
        'trace'     => $e->getTraceAsString(),
    ]);
    ResponseFormatter::error('An unexpected error occurred. Please try again later.', 500);
}