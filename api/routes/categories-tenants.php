<?php
declare(strict_types=1);

// api/routes/categories-tenants.php
// Full-featured API endpoint for tenant categories

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/shared/core/ResponseFormatter.php';
require_once dirname(__DIR__) . '/shared/config/db.php';

require_once API_VERSION_PATH . '/models/categories/repositories/PdoTenantCategoriesRepository.php';
require_once API_VERSION_PATH . '/models/categories/services/TenantCategoriesService.php';
require_once API_VERSION_PATH . '/models/categories/controllers/TenantCategoriesController.php';

// Ensure database connection
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
}

// Initialize repository, service, and controller
$repo = new PdoTenantCategoriesRepository($pdo);
$service = new TenantCategoriesService($repo);
$controller = new TenantCategoriesController($service);

// Get request method and input
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = json_decode(file_get_contents('php://input'), true) ?? [];

// Extract ID from URL path if present
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$id = null;
if (preg_match('/\/categories-tenants\/(\d+)/', $requestUri, $matches)) {
    $id = (int)$matches[1];
}

try {
    switch ($method) {

        case 'GET':
            // If ID is provided, fetch single record
            $idGet = isset($_GET['id']) ? (int)$_GET['id'] : $id;

            if ($idGet) {
                $item = $controller->get($idGet);
                if ($item) {
                    ResponseFormatter::success([$item]); // Return as array for consistency
                } else {
                    ResponseFormatter::error('Tenant Category not found', 404);
                }
            } else {
                $list = $controller->list();
                ResponseFormatter::success($list);
            }
            break;

        case 'POST':
            // Validate required fields
            if (empty($data['tenant_id']) || empty($data['category_id'])) {
                throw new InvalidArgumentException('tenant_id and category_id are required');
            }
            $created = $controller->create($data);
            ResponseFormatter::success($created, 'Tenant Category created');
            break;

        case 'PUT':
            $data['id'] = $id ?: ($data['id'] ?? null);

            // Toggle if only id and is_active are sent (2 fields)
            if (isset($data['is_active']) && isset($data['id']) && count($data) === 2) {
                // Toggle status
                $toggled = $controller->toggleStatus($data);
                ResponseFormatter::success($toggled, 'Status toggled');
            } else {
                if (empty($data['id'])) {
                    throw new InvalidArgumentException('ID is required for update');
                }
                $updated = $controller->update($data);
                ResponseFormatter::success($updated, 'Tenant Category updated');
            }
            break;

        case 'DELETE':
            $data['id'] = $id ?: ($data['id'] ?? null);
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID is required for deletion');
            }
            $controller->delete($data);
            ResponseFormatter::success(['deleted' => true], 'Tenant Category deleted');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }

} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);

} catch (Throwable $e) {
    error_log('[Categories-Tenants API] ' . $e->getMessage());
    ResponseFormatter::error('Internal server error', 500);
}