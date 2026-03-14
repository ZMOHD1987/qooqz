<?php
declare(strict_types=1);

/**
 * Tenants Routes
 * HTTP layer for tenant management
 */

error_log('[Tenants Route] ════════════════════════════════════');
error_log('[Tenants Route] Request started');
error_log('[Tenants Route] Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log('[Tenants Route] URI: ' . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));

try {
    // ✅ Load dependencies
    $basePath = __DIR__ . '/../';
    
    require_once $basePath . 'shared/services/PermissionService.php';
    require_once $basePath . 'v1/models/tenants/repositories/PdoTenantsRepository.php';
    require_once $basePath . 'v1/models/tenants/validators/TenantsValidator.php';
    require_once $basePath . 'v1/models/tenants/services/TenantsService.php';
    require_once $basePath . 'v1/models/tenants/controllers/TenantsController.php';

    error_log('[Tenants Route] All classes loaded');

    // ✅ Check PDO
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO connection not available');
    }

    error_log('[Tenants Route] PDO connection OK');

    // ✅ Create instances
    $permissionService = new PermissionService($pdo);
    $repository = new PdoTenantsRepository($pdo);
    $validator = new TenantsValidator();
    $service = new TenantsService($repository, $validator);
    $controller = new TenantsController($service, $permissionService);

    error_log('[Tenants Route] All instances created');

    // Parse request
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = array_filter(explode('/', $uri));
    
    // Extract ID from URL: /api/tenants/123
    $id = null;
    $segmentsArray = array_values($segments);
    
    // Find 'tenants' index and get next segment
    $tenantIndex = array_search('tenants', $segmentsArray);
    if ($tenantIndex !== false && isset($segmentsArray[$tenantIndex + 1])) {
        $possibleId = $segmentsArray[$tenantIndex + 1];
        if (is_numeric($possibleId)) {
            $id = (int)$possibleId;
        }
    }

    error_log('[Tenants Route] Parsed - Method: ' . $method . ', ID: ' . ($id ?? 'none'));

    // ════════════════════════════════════════════════════════════
    // GET /api/tenants - List
    // ════════════════════════════════════════════════════════════
    if ($method === 'GET' && !$id) {
        error_log('[Tenants Route] Handling GET list');
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 10;
        $offset = ($page - 1) * $perPage;

        $filters = [];
        if (!empty($_GET['search'])) {
            $filters['search'] = trim($_GET['search']);
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = trim($_GET['status']);
        }
        if (!empty($_GET['owner_user_id'])) {
            $filters['owner_user_id'] = (int)$_GET['owner_user_id'];
        }

        $items = $controller->list($perPage, $offset, $filters);
        $total = $controller->count($filters);

        error_log('[Tenants Route] Found ' . count($items) . ' items, total: ' . $total);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'page' => $page,
                    'last_page' => ceil($total / $perPage)
                ]
            ],
            'meta' => [
                'time' => date('c'),
                'request_id' => uniqid()
            ]
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // GET /api/tenants/{id} - Single
    // ════════════════════════════════════════════════════════════
    if ($method === 'GET' && $id) {
        error_log('[Tenants Route] Handling GET single: ' . $id);
        
        $item = $controller->get($id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $item,
            'meta' => [
                'time' => date('c'),
                'request_id' => uniqid()
            ]
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // POST /api/tenants - Create
    // ════════════════════════════════════════════════════════════
    if ($method === 'POST') {
        error_log('[Tenants Route] Handling POST create');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        error_log('[Tenants Route] Input: ' . json_encode($input));

        $result = $controller->create($input);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Tenant created successfully',
            'data' => $result,
            'meta' => [
                'time' => date('c'),
                'request_id' => uniqid()
            ]
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // PUT /api/tenants/{id} - Update
    // ════════════════════════════════════════════════════════════
    if (($method === 'PUT' || $method === 'PATCH') && $id) {
        error_log('[Tenants Route] Handling PUT/PATCH update: ' . $id);
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        error_log('[Tenants Route] Input: ' . json_encode($input));

        $result = $controller->update($input, $id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Tenant updated successfully',
            'data' => $result,
            'meta' => [
                'time' => date('c'),
                'request_id' => uniqid()
            ]
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // DELETE /api/tenants/{id}
    // ════════════════════════════════════════════════════════════
    if ($method === 'DELETE' && $id) {
        error_log('[Tenants Route] Handling DELETE: ' . $id);
        
        $controller->delete(['id' => $id]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Tenant deleted successfully',
            'meta' => [
                'time' => date('c'),
                'request_id' => uniqid()
            ]
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // Method not allowed
    // ════════════════════════════════════════════════════════════
    error_log('[Tenants Route] No matching route - Method: ' . $method . ', ID: ' . ($id ?? 'none'));
    
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
    ]);

} catch (UnauthorizedException $e) {
    error_log('[Tenants Route] UnauthorizedException: ' . $e->getMessage());
    
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'UnauthorizedException'
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log('[Tenants Route] InvalidArgumentException: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'InvalidArgumentException'
    ]);
    
} catch (Exception $e) {
    error_log('[Tenants Route] Exception: ' . $e->getMessage());
    error_log('[Tenants Route] Trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}

error_log('[Tenants Route] Request completed');
error_log('[Tenants Route] ════════════════════════════════════');