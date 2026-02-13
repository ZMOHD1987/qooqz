<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/products';
require_once $modelsPath . '/repositories/PdoProductsRepository.php';
require_once $modelsPath . '/services/ProductsService.php';
require_once $modelsPath . '/controllers/ProductsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoProductsRepository($pdo);
$service = new ProductsService($repo);
$controller = new ProductsController($service);

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
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : [];

    $lang    = $_GET['lang'] ?? 'ar';
    $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit   = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
    $offset  = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'id';
    $orderDir = $_GET['order_dir'] ?? 'DESC';

    // Collect filters
    $filters = [
        'product_type_id' => $_GET['product_type_id'] ?? null,
        'sku'             => $_GET['sku'] ?? null,
        'slug'            => $_GET['slug'] ?? null,
        'barcode'         => $_GET['barcode'] ?? null,
        'brand_id'        => $_GET['brand_id'] ?? null,
        'is_active'       => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null
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
                $item = $controller->get($tenantId, (int)$_GET['id'], $lang);
                ResponseFormatter::success($item);
            } else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
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
            // Check subscription product limit before creating
            try {
                $limitStmt = $pdo->prepare(
                    "SELECT s.id, sp.max_products, sp.plan_name
                     FROM subscriptions s
                     JOIN subscription_plans sp ON s.plan_id = sp.id
                     WHERE s.tenant_id = :tid AND s.status IN ('active','trial')
                     ORDER BY s.id DESC LIMIT 1"
                );
                $limitStmt->execute([':tid' => $tenantId]);
                $activePlan = $limitStmt->fetch(PDO::FETCH_ASSOC);
                if ($activePlan && (int)$activePlan['max_products'] > 0) {
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = :tid");
                    $countStmt->execute([':tid' => $tenantId]);
                    $currentCount = (int)$countStmt->fetchColumn();
                    if ($currentCount >= (int)$activePlan['max_products']) {
                        ResponseFormatter::error(
                            'Product limit reached (' . $currentCount . '/' . $activePlan['max_products'] . '). Upgrade your plan to add more products.',
                            403
                        );
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Don't block product creation if limit check fails
            }

            $newId = $controller->create($tenantId, $data);

            // حفظ الترجمة الأساسية (الاسم) في product_translations إذا تم تقديم اسم
            if (!empty($data['name']) && $newId) {
                try {
                    $langCode = $_GET['lang'] ?? 'ar';
                    $transStmt = $pdo->prepare("
                        INSERT INTO product_translations (product_id, language_code, name, short_description, description)
                        VALUES (:product_id, :language_code, :name, :short_desc, :description)
                        ON DUPLICATE KEY UPDATE name = VALUES(name), short_description = VALUES(short_description), description = VALUES(description)
                    ");
                    $transStmt->execute([
                        ':product_id' => $newId,
                        ':language_code' => $langCode,
                        ':name' => $data['name'],
                        ':short_desc' => $data['short_description'] ?? '',
                        ':description' => $data['description'] ?? ''
                    ]);
                } catch (Throwable $e) {
                    safe_log('warning','products.translation_save', ['error'=>$e->getMessage()]);
                }
            }

            // Auto-populate SEO meta
            try {
                SeoAutoManager::sync($pdo, 'product', (int)$newId, [
                    'name'          => $data['name'] ?? '',
                    'slug'          => $data['slug'] ?? '',
                    'description'   => $data['description'] ?? '',
                    'tenant_id'     => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'product', (int)$newId);
            } catch (\Throwable $e) {
                // SEO sync failure should not break product creation
            }

            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            $updatedId = $controller->update($tenantId, $data);

            // تحديث الترجمة الأساسية (الاسم)
            if (!empty($data['name']) && $updatedId) {
                try {
                    $langCode = $_GET['lang'] ?? 'ar';
                    $transStmt = $pdo->prepare("
                        INSERT INTO product_translations (product_id, language_code, name, short_description, description)
                        VALUES (:product_id, :language_code, :name, :short_desc, :description)
                        ON DUPLICATE KEY UPDATE name = VALUES(name), short_description = VALUES(short_description), description = VALUES(description)
                    ");
                    $transStmt->execute([
                        ':product_id' => $updatedId,
                        ':language_code' => $langCode,
                        ':name' => $data['name'],
                        ':short_desc' => $data['short_description'] ?? '',
                        ':description' => $data['description'] ?? ''
                    ]);
                } catch (Throwable $e) {
                    safe_log('warning','products.translation_update', ['error'=>$e->getMessage()]);
                }
            }

            // Auto-update SEO meta
            try {
                SeoAutoManager::sync($pdo, 'product', (int)$updatedId, [
                    'name'          => $data['name'] ?? '',
                    'slug'          => $data['slug'] ?? '',
                    'description'   => $data['description'] ?? '',
                    'tenant_id'     => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'product', (int)$updatedId);
            } catch (\Throwable $e) {
                // SEO sync failure should not break product update
            }

            ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
            break;

        case 'DELETE':
            if (empty($data['id'])) {
                ResponseFormatter::error('Missing product ID for deletion', 400);
            }
            $deleted = $controller->delete($tenantId, (int)$data['id']);

            // Auto-delete SEO meta
            try {
                SeoAutoManager::delete($pdo, 'product', (int)$data['id']);
            } catch (\Throwable $e) {
                // SEO delete failure should not break product deletion
            }

            ResponseFormatter::success(['deleted' => $deleted], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning','products.validation', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error','products.runtime', ['error'=>$e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical','products.fatal', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    ResponseFormatter::error($e->getMessage(), 500);
}