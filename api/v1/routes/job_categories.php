<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/jobs';
require_once $modelsPath . '/repositories/PdoJobCategoriesRepository.php';
require_once $modelsPath . '/repositories/PdoJobCategoryTranslationsRepository.php';
require_once $modelsPath . '/validators/JobCategoriesValidator.php';
require_once $modelsPath . '/services/JobCategoriesService.php';
require_once $modelsPath . '/controllers/JobCategoriesController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo = new PdoJobCategoriesRepository($pdo);
$translationsRepo = new PdoJobCategoryTranslationsRepository($pdo);
$validator = new App\Models\Jobs\Validators\JobCategoriesValidator();
$service = new JobCategoriesService($repo, $translationsRepo, $validator);
$controller = new JobCategoriesController($service);

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

    // Parse RESTful URL for ID (e.g., /api/job_categories/3)
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = preg_replace('#^/api#', '', $uri);
    $parts = array_filter(explode('/', trim($uri, '/')));
    $parts = array_values($parts);
    // If we have job_categories/123, extract the ID
    if (count($parts) >= 2 && $parts[0] === 'job_categories' && is_numeric($parts[1])) {
        $_GET['id'] = (int)$parts[1];
    }

    $lang = $_GET['lang'] ?? 'ar';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 100;
    $offset = ($page - 1) * $limit;
    $orderBy = $_GET['order_by'] ?? 'sort_order';
    $orderDir = $_GET['order_dir'] ?? 'ASC';

    // Collect filters
    $filters = [
        'parent_id' => $_GET['parent_id'] ?? null,
        'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
        'search' => $_GET['search'] ?? null
    ];

    // Remove null filters
    $filters = array_filter($filters, fn($v) => $v !== null);

    switch ($method) {
        case 'OPTIONS':
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(204);
            exit;

        case 'GET':
            // Get category tree
            if (isset($_GET['tree'])) {
                $parentId = isset($_GET['parent_id']) && is_numeric($_GET['parent_id']) 
                    ? (int)$_GET['parent_id'] 
                    : null;
                $tree = $controller->getTree($tenantId, $parentId, $lang);
                ResponseFormatter::success($tree);
            }
            // Get root categories
            elseif (isset($_GET['root'])) {
                $items = $controller->getRootCategories($tenantId, $lang);
                ResponseFormatter::success($items);
            }
            // Get children of a category
            elseif (isset($_GET['parent_id']) && isset($_GET['children'])) {
                $parentId = (int)$_GET['parent_id'];
                $items = $controller->getChildren($tenantId, $parentId, $lang);
                ResponseFormatter::success($items);
            }
            // Get single category by ID
            elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $categoryId = (int)$_GET['id'];
                
                // Get translations if requested
                if (isset($_GET['with_translations']) && $_GET['with_translations'] === '1') {
                    $category = $controller->get($tenantId, $categoryId, $lang);
                    if ($category) {
                        $category['translations'] = $controller->getTranslations($categoryId);
                        $category['available_languages'] = $controller->getAvailableLanguages($categoryId);
                        $category['missing_languages'] = $controller->getMissingLanguages($categoryId);
                    }
                    ResponseFormatter::success($category);
                } else {
                    $item = $controller->get($tenantId, $categoryId, $lang);
                    ResponseFormatter::success($item);
                }
            }
            // Get category by slug
            elseif (isset($_GET['slug'])) {
                $item = $controller->getBySlug($tenantId, $_GET['slug'], $lang);
                ResponseFormatter::success($item);
            }
            // Get translations for a category
            elseif (isset($_GET['category_id']) && isset($_GET['translations'])) {
                $categoryId = (int)$_GET['category_id'];
                $translations = $controller->getTranslations($categoryId);
                ResponseFormatter::success($translations);
            }
            // Get available languages for a category
            elseif (isset($_GET['category_id']) && isset($_GET['available_languages'])) {
                $categoryId = (int)$_GET['category_id'];
                $languages = $controller->getAvailableLanguages($categoryId);
                ResponseFormatter::success($languages);
            }
            // Get missing languages for a category
            elseif (isset($_GET['category_id']) && isset($_GET['missing_languages'])) {
                $categoryId = (int)$_GET['category_id'];
                $languages = $controller->getMissingLanguages($categoryId);
                ResponseFormatter::success($languages);
            }
            // List all categories with filters
            else {
                $result = $controller->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
                $total = $result['total'];
                ResponseFormatter::success([
                    'items' => $result['items'],
                    'meta' => [
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $limit,
                        'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                        'from' => $total > 0 ? $offset + 1 : 0,
                        'to' => $total > 0 ? min($offset + $limit, $total) : 0
                    ]
                ]);
            }
            break;

        case 'POST':
            // Add language_code to data if not present
            if (!isset($data['language_code'])) {
                $data['language_code'] = $lang;
            }

            $newId = $controller->create($tenantId, $data);

            ResponseFormatter::success(['id' => $newId], 'Category created successfully', 201);
            break;

        case 'PUT':
            // التحقق من وجود ID في الـ URL
            if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                ResponseFormatter::error('ID is required in URL for update', 400);
                exit;
            }

            // إضافة ID إلى البيانات
            $data['id'] = (int)$_GET['id'];

            // Add language_code to data if not present
            if (!isset($data['language_code'])) {
                $data['language_code'] = $lang;
            }

            $updatedId = $controller->update($tenantId, $data);

            ResponseFormatter::success(['id' => $updatedId], 'Category updated successfully');
            break;

        case 'PATCH':
            // Update sort order
            if (isset($_GET['id']) && isset($data['sort_order'])) {
                $categoryId = (int)$_GET['id'];
                $sortOrder = (int)$data['sort_order'];
                $updated = $controller->updateSortOrder($tenantId, $categoryId, $sortOrder);
                ResponseFormatter::success(['updated' => $updated], 'Sort order updated successfully');
            }
            // Move to different parent
            elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'move') {
                $categoryId = (int)$_GET['id'];
                $newParentId = isset($data['parent_id']) && $data['parent_id'] !== null 
                    ? (int)$data['parent_id'] 
                    : null;
                $moved = $controller->moveToParent($tenantId, $categoryId, $newParentId);
                ResponseFormatter::success(['moved' => $moved], 'Category moved successfully');
            }
            // Reorder categories (batch update)
            elseif (isset($_GET['action']) && $_GET['action'] === 'reorder') {
                if (empty($data['order']) || !is_array($data['order'])) {
                    ResponseFormatter::error('Order data is required', 400);
                    exit;
                }
                $reordered = $controller->reorder($tenantId, $data['order']);
                ResponseFormatter::success(['reordered' => $reordered], 'Categories reordered successfully');
            }
            // Save/Update translation
            elseif (isset($_GET['category_id']) && isset($_GET['translation'])) {
                $categoryId = (int)$_GET['category_id'];
                $languageCode = $data['language_code'] ?? $lang;
                $saved = $controller->saveTranslation($categoryId, $languageCode, $data);
                ResponseFormatter::success(['saved' => $saved], 'Translation saved successfully');
            }
            // Bulk save translations
            elseif (isset($_GET['category_id']) && isset($_GET['bulk_translations'])) {
                $categoryId = (int)$_GET['category_id'];
                if (empty($data['translations']) || !is_array($data['translations'])) {
                    ResponseFormatter::error('Translations data is required', 400);
                    exit;
                }
                $saved = $controller->bulkSaveTranslations($categoryId, $data['translations']);
                ResponseFormatter::success(['saved' => $saved], 'Translations saved successfully');
            }
            // Delete translation
            elseif (isset($_GET['category_id']) && isset($_GET['delete_translation']) && isset($_GET['lang_code'])) {
                $categoryId = (int)$_GET['category_id'];
                $langCode = $_GET['lang_code'];
                $deleted = $controller->deleteTranslation($categoryId, $langCode);
                ResponseFormatter::success(['deleted' => $deleted], 'Translation deleted successfully');
            }
            else {
                ResponseFormatter::error('Invalid PATCH request', 400);
            }
            break;

        case 'DELETE':
            // التحقق من وجود ID في الـ URL
            if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                ResponseFormatter::error('ID is required in URL for delete', 400);
                exit;
            }

            // إضافة ID إلى البيانات
            $data['id'] = (int)$_GET['id'];

            $deleted = $controller->delete($tenantId, (int)$data['id']);
            ResponseFormatter::success(['deleted' => $deleted], 'Category deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (\InvalidArgumentException $e) {
    safe_log('warning', 'job_categories.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    safe_log('error', 'job_categories.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    safe_log('critical', 'job_categories.fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error($e->getMessage(), 500);
}
