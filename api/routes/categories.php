<?php
declare(strict_types=1);

// ===== مسار api =====
$baseDir = dirname(__DIR__);

// ===== تحميل bootstrap =====
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/helpers/SeoAutoManager.php';
require_once $baseDir . '/shared/config/db.php';

// ===== تحميل ملفات categories =====
require_once API_VERSION_PATH . '/models/categories/repositories/PdoCategoriesRepository.php';
require_once API_VERSION_PATH . '/models/categories/validators/CategoriesValidator.php';
require_once API_VERSION_PATH . '/models/categories/services/CategoriesService.php';
require_once API_VERSION_PATH . '/models/categories/controllers/CategoriesController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

$defaultTenantId = 1;

// إنشاء الاعتمادات
$repo = new PdoCategoriesRepository($pdo);
$validator = new CategoriesValidator();
$service = new CategoriesService($repo, $validator);
$controller = new CategoriesController($service);

// دالة لتحديد الحد الأقصى
function clampLimit(int $val, int $max = 1000): int {
    return ($val <= 0 ? 50 : min($val, $max));
}

// بدء الجلسة إذا لم تبدأ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    $tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : $defaultTenantId;
    $format = strtolower($_GET['format'] ?? 'json');
    $lang = $_GET['lang'] ?? 'ar';

    /* ===================== GET ACTIVE ===================== */
    if ($method === 'GET' && str_contains($uri, '/categories/active')) {
        $data = $controller->getActive($tenantId);
        ResponseFormatter::success($data);
        return;
    }

    /* ===================== GET FEATURED ===================== */
    if ($method === 'GET' && str_contains($uri, '/categories/featured')) {
        $data = $controller->getFeatured($tenantId);
        ResponseFormatter::success($data);
        return;
    }

    /* ===================== GET TREE ===================== */
    if ($method === 'GET' && str_contains($uri, '/categories/tree')) {
        $data = $controller->tree($tenantId);
        ResponseFormatter::success($data);
        return;
    }

    /* ===================== GET BY ID ===================== */
    if ($method === 'GET' && preg_match('#/categories/(\d+)#', $uri, $matches)) {
        $id = (int)$matches[1];
        
        // إذا كان هناك slug في الـ GET، استخدم خاصية slug
        if (isset($_GET['slug'])) {
            $data = ['slug' => $_GET['slug']];
            // البحث عن الفئة باستخدام slug
            $categoryId = $repo->findIdBySlug($tenantId, $_GET['slug']);
            if ($categoryId) {
                $id = $categoryId;
            }
        }
        
        try {
            $row = $controller->getById($tenantId, $id);
            ResponseFormatter::success($row);
        } catch (RuntimeException $e) {
            ResponseFormatter::error($e->getMessage(), 404);
        }
        return;
    }

    /* ===================== VALIDATE SLUG ===================== */
    if ($method === 'POST' && str_contains($uri, '/categories/validate-slug')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = $controller->validateSlug($tenantId, $data);
        ResponseFormatter::success($result);
        return;
    }

    /* ===================== GET LIST ===================== */
    if ($method === 'GET' && str_contains($uri, '/categories')) {
        $data = $controller->list($tenantId);
        
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="categories_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            
            if (!empty($data['items'])) {
                fputcsv($out, array_keys($data['items'][0]));
                foreach ($data['items'] as $row) {
                    fputcsv($out, $row);
                }
            }
            fclose($out);
            exit;
        }
        
        ResponseFormatter::success($data);
        return;
    }

    /* ===================== BULK OPERATIONS ===================== */
    if ($method === 'POST' && str_contains($uri, '/categories/bulk')) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = $controller->bulkUpdate($tenantId, $data);
        ResponseFormatter::success($result);
        return;
    }

    /* ===================== POST CREATE ===================== */
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $created = $controller->create($tenantId, $data);

        // Auto-populate SEO meta
        try {
            $catId = $created['id'] ?? null;
            if ($catId) {
                SeoAutoManager::sync($pdo, 'category', (int)$catId, [
                    'name'          => $created['name'] ?? $data['name'] ?? '',
                    'slug'          => $created['slug'] ?? $data['slug'] ?? '',
                    'description'   => $created['description'] ?? $data['description'] ?? '',
                    'tenant_id'     => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'category', (int)$catId);
            }
        } catch (\Throwable $e) {
            // SEO sync failure should not break category creation
        }

        ResponseFormatter::success($created, 'Category created successfully', 201);
        return;
    }

    /* ===================== PUT UPDATE ===================== */
    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $updated = $controller->update($tenantId, $data);

        // Auto-update SEO meta
        try {
            $catId = $updated['id'] ?? $data['id'] ?? null;
            if ($catId) {
                SeoAutoManager::sync($pdo, 'category', (int)$catId, [
                    'name'          => $updated['name'] ?? $data['name'] ?? '',
                    'slug'          => $updated['slug'] ?? $data['slug'] ?? '',
                    'description'   => $updated['description'] ?? $data['description'] ?? '',
                    'tenant_id'     => $tenantId,
                ]);
                SeoAutoManager::syncAllTranslations($pdo, 'category', (int)$catId);
            }
        } catch (\Throwable $e) {
            // SEO sync failure should not break category update
        }

        ResponseFormatter::success($updated, 'Category updated successfully', 200);
        return;
    }

    /* ===================== DELETE SINGLE TRANSLATION ===================== */
    if ($method === 'DELETE' && preg_match('#/categories/(\d+)/translations/([a-zA-Z_-]+)#', $uri, $matches)) {
        $categoryId = (int) $matches[1];
        $languageCode = (string) $matches[2];

        $result = $controller->deleteTranslation($tenantId, $categoryId, $languageCode);
        ResponseFormatter::success($result, 'Translation deleted successfully', 200);
        return;
    }

    /* ===================== DELETE CATEGORY ===================== */
    if ($method === 'DELETE' && preg_match('#/categories/(\d+)#', $uri, $matches)) {
        $id = (int) $matches[1];
        $data = ['id' => $id];
        
        // إضافة user_id إذا كان موجوداً في الجلسة
        if (isset($_SESSION['user_id'])) {
            $data['user_id'] = $_SESSION['user_id'];
        }
        
        $controller->delete($tenantId, $data);

        // Auto-delete SEO meta
        try {
            SeoAutoManager::delete($pdo, 'category', $id);
        } catch (\Throwable $e) {
            // SEO delete failure should not break category deletion
        }

        ResponseFormatter::success(['deleted' => true], 'Category deleted successfully', 200);
        return;
    }

    // معالجة DELETE العام
    if ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller->delete($tenantId, $data);

        // Auto-delete SEO meta
        try {
            $delId = $data['id'] ?? null;
            if ($delId) {
                SeoAutoManager::delete($pdo, 'category', (int)$delId);
            }
        } catch (\Throwable $e) {
            // SEO delete failure should not break category deletion
        }

        ResponseFormatter::success(['deleted' => true], 'Category deleted successfully', 200);
        return;
    }

    ResponseFormatter::error('Method not allowed or endpoint not found', 405);
    return;

} catch (InvalidArgumentException $e) {
    // محاولة فك الترميز إذا كان JSON
    $message = $e->getMessage();
    $decoded = json_decode($message, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        ResponseFormatter::error($decoded, 422);
    } else {
        ResponseFormatter::error($message, 422);
    }
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (Throwable $e) {
    safe_log('error', 'Categories route failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    ResponseFormatter::error('Internal server error: ' . $e->getMessage(), 500);
}