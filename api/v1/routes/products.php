<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/products';
require_once $modelsPath . '/repositories/PdoProductsRepository.php';
require_once $modelsPath . '/services/ProductsService.php';
require_once $modelsPath . '/controllers/ProductsController.php';

// Bad words service
$badWordsPath = API_VERSION_PATH . '/models/bad_words';
require_once $badWordsPath . '/repositories/PdoBadWordsRepository.php';
require_once $badWordsPath . '/services/BadWordsService.php';

// Audit logs service
$auditPath = API_VERSION_PATH . '/models/audit_logs';
require_once $auditPath . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $auditPath . '/repositories/PdoAuditLogsRepository.php';
require_once $auditPath . '/services/AuditLogsService.php';

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

            // Bad words check on text fields
            try {
                $badWordsRepo    = new PdoBadWordsRepository($pdo);
                $badWordsService = new BadWordsService($badWordsRepo);
                $fieldsToCheck   = ['name', 'description', 'short_description', 'specifications'];
                $badFound        = [];
                foreach ($fieldsToCheck as $field) {
                    $val = $data[$field] ?? '';
                    if ($val === '' || $val === null) continue;
                    $check = $badWordsService->checkText((string)$val);
                    if (!$check['clean']) {
                        foreach ($check['found'] as $hit) {
                            $badFound[] = $hit['word'];
                        }
                    }
                }
                if (!empty($badFound)) {
                    ResponseFormatter::error(
                        'Content contains prohibited words: ' . implode(', ', array_unique($badFound)),
                        422
                    );
                    break;
                }
            } catch (\Throwable $e) {
                // Don't block product creation if bad-words check fails (service unavailable)
                safe_log('warning', 'products.bad_words_check_failed', ['error' => $e->getMessage()]);
            }

            $newId = $controller->create($tenantId, $data);

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

            // Audit log: product created
            AuditLogsService::log(
                'product.create',
                'product',
                (int)$newId,
                ['name' => $data['name'] ?? '', 'sku' => $data['sku'] ?? ''],
                $tenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );

            ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
            break;

        case 'PUT':
            // Bad words check on text fields
            try {
                $badWordsRepo    = new PdoBadWordsRepository($pdo);
                $badWordsService = new BadWordsService($badWordsRepo);
                $fieldsToCheck   = ['name', 'description', 'short_description', 'specifications'];
                $badFound        = [];
                foreach ($fieldsToCheck as $field) {
                    $val = $data[$field] ?? '';
                    if ($val === '' || $val === null) continue;
                    $check = $badWordsService->checkText((string)$val);
                    if (!$check['clean']) {
                        foreach ($check['found'] as $hit) {
                            $badFound[] = $hit['word'];
                        }
                    }
                }
                if (!empty($badFound)) {
                    ResponseFormatter::error(
                        'Content contains prohibited words: ' . implode(', ', array_unique($badFound)),
                        422
                    );
                    break;
                }
            } catch (\Throwable $e) {
                safe_log('warning', 'products.bad_words_check_failed', ['error' => $e->getMessage()]);
            }

            $updatedId = $controller->update($tenantId, $data);

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

            // Audit log: product updated
            AuditLogsService::log(
                'product.update',
                'product',
                (int)$updatedId,
                ['name' => $data['name'] ?? '', 'id' => $updatedId],
                $tenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );

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

            // Audit log: product deleted
            AuditLogsService::log(
                'product.delete',
                'product',
                (int)$data['id'],
                ['id' => $data['id']],
                $tenantId,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );

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
