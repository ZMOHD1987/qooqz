<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

// ================================
// Load model files
// ================================
$modelsPath = API_VERSION_PATH . '/models/addresses';
require_once $modelsPath . '/repositories/PdoAddressesRepository.php';
require_once $modelsPath . '/validators/AddressesValidator.php';
require_once $modelsPath . '/services/AddressesService.php';
require_once $modelsPath . '/controllers/AddressesController.php';

// ================================
// Session
// ================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================
// DB
// ================================
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

// ================================
// Init layers
// ================================
$repo       = new PdoAddressesRepository($pdo);
$service    = new AddressesService($repo);
$controller = new AddressesController($service);
$validator  = new AddressesValidator();

// ================================
// Tenant & Auth check
// ================================
$user = $_SESSION['user'] ?? [];

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
    $raw    = file_get_contents('php://input');
    $data   = $raw ? json_decode($raw, true) : [];

    $page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset   = ($page - 1) * $limit;
    $orderBy  = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // ================================
    // Collect filters + language
    // ================================
    $language = $_GET['language'] ?? $_GET['lang'] ?? 'ar';
    
    $filters = [
        'id'         => isset($_GET['id']) ? (int)$_GET['id'] : null,
        'owner_type' => $_GET['owner_type'] ?? null,
        'owner_id'   => isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : null,
        'city_id'    => isset($_GET['city_id']) ? (int)$_GET['city_id'] : null,
        'country_id' => isset($_GET['country_id']) ? (int)$_GET['country_id'] : null,
        'is_primary' => isset($_GET['is_primary']) ? (int)$_GET['is_primary'] : null,
        'language'   => $language,
    ];

    // ================================
    // Parse URL for RESTful ID
    // ================================
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $pathInfo = parse_url($requestUri, PHP_URL_PATH);
    $pathParts = explode('/', trim($pathInfo, '/'));
    
    // Look for numeric ID after 'addresses'
    $urlId = null;
    foreach ($pathParts as $i => $part) {
        if ($part === 'addresses' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $urlId = (int)$pathParts[$i + 1];
            break;
        }
    }

    switch ($method) {

        // ================================
        // OPTIONS
        // ================================
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        // ================================
        // GET
        // ================================
        case 'GET':
            // Check for ID in URL or query string
            $getId = $urlId ?? (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null);
            
            if ($getId) {
                // Single item
                $item = $controller->get($getId, $language);
                ResponseFormatter::success($item);

            } else {
                // List
                $result = $controller->list(
                    $limit,
                    $offset,
                    $filters,
                    $orderBy,
                    $orderDir
                );

                $total = $result['total'];

                ResponseFormatter::success([
                    'data' => $result['items'],
                    'meta' => [
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

        // ================================
        // POST
        // ================================
        case 'POST':
            $validator->validateCreate($data);

            // Ensure tenant_id and owner fields
            $data['tenant_id'] = $tenantId;
            if (!isset($data['owner_type'])) {
                $data['owner_type'] = 'user';
            }
            if (!isset($data['owner_id'])) {
                $data['owner_id'] = $user['id'] ?? null;
            }

            $newId = $controller->create($data);

            ResponseFormatter::success(
                ['id' => $newId],
                'Created successfully',
                201
            );
            break;

        // ================================
        // PUT
        // ================================
        case 'PUT':
            $updateId = $urlId ?? (isset($data['id']) ? (int)$data['id'] : null);
            
            if (!$updateId) {
                ResponseFormatter::error('ID is required for update', 400);
                exit;
            }

            $validator->validateUpdate($data);

            $controller->update($updateId, $data);

            ResponseFormatter::success(
                ['id' => $updateId],
                'Updated successfully'
            );
            break;

        // ================================
        // DELETE
        // ================================
        case 'DELETE':
            $deleteId = $urlId ?? (isset($data['id']) ? (int)$data['id'] : null);
            
            if (!$deleteId) {
                ResponseFormatter::error('Missing address ID for deletion', 400);
                exit;
            }

            $deleted = $controller->delete($deleteId);

            ResponseFormatter::success(
                ['deleted' => $deleted],
                'Deleted successfully'
            );
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {

    safe_log('warning', 'addresses.validation', [
        'error' => $e->getMessage()
    ]);
    ResponseFormatter::error($e->getMessage(), 422);

} catch (RuntimeException $e) {

    safe_log('error', 'addresses.runtime', [
        'error' => $e->getMessage()
    ]);
    ResponseFormatter::error($e->getMessage(), 400);

} catch (Throwable $e) {

    safe_log('critical', 'addresses.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error('Internal Server Error', 500);
}