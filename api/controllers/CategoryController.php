<?php
// htdocs/api/controllers/CategoryController.php
// Controller لإدارة الفئات (Categories) - CRUD, tree, attach/detach products, reorder, stats

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';

class CategoryController
{
    /**
     * إنشاء فئة جديدة (Admin only)
     * POST /api/categories
     */
    public static function create()
    {
        RoleMiddleware::canCreate('categories'); // سيقوم بالمصادقة والتفويض

        $input = $_POST;
        $rules = [
            'name' => 'required|string|min:2|max:150',
            'slug' => 'optional|string|min:2|max:150|unique:categories,slug',
            'parent_id' => 'optional|integer',
            'description' => 'optional|string|max:2000',
            'is_active' => 'optional|boolean',
            'sort_order' => 'optional|integer'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $categoryModel = new Category();

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Utils::createSlug($validated['name']);
            if (! $categoryModel->isSlugUnique($validated['slug'])) {
                $validated['slug'] .= '-' . substr(Utils::generateUUID(), 0, 6);
            }
        }

        $newCat = $categoryModel->create($validated);

        if (!$newCat) {
            Response::error('Failed to create category', 500);
        }

        Response::created(['category' => $newCat], 'Category created successfully');
    }

    /**
     * تحديث فئة (Admin only)
     * PUT /api/categories/{id}
     */
    public static function update($id = null)
    {
        RoleMiddleware::canUpdate('categories');

        if (!$id) Response::validationError(['id' => ['Category id is required']]);
        $id = (int)$id;

        $input = $_POST;
        $rules = [
            'name' => 'optional|string|min:2|max:150',
            'slug' => "optional|string|min:2|max:150|unique:categories,slug,{$id}",
            'parent_id' => 'optional|integer',
            'description' => 'optional|string|max:2000',
            'is_active' => 'optional|boolean',
            'sort_order' => 'optional|integer'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $categoryModel = new Category();
        $exists = $categoryModel->findById($id);
        if (!$exists) Response::error('Category not found', 404);

        // Prevent setting parent to self or descendant (basic check)
        if (isset($validated['parent_id'])) {
            $parentId = (int)$validated['parent_id'];
            if ($parentId === $id) {
                Response::validationError(['parent_id' => ['Cannot set parent_id to self']]);
            }
            // optional: deeper descendant check could be added
        }

        $ok = $categoryModel->update($id, $validated);
        if ($ok) {
            $updated = $categoryModel->findById($id);
            Response::success(['category' => $updated], 'Category updated successfully');
        }

        Response::error('Failed to update category', 500);
    }

    /**
     * حذف فئة (soft/hard) - Admin
     * DELETE /api/categories/{id}
     */
    public static function delete($id = null)
    {
        RoleMiddleware::canDelete('categories');

        if (!$id) Response::validationError(['id' => ['Category id is required']]);
        $id = (int)$id;

        $hard = isset($_GET['hard']) && $_GET['hard'] == '1';

        $categoryModel = new Category();
        $exists = $categoryModel->findById($id);
        if (!$exists) Response::error('Category not found', 404);

        $success = $categoryModel->delete($id, $hard);
        if ($success) {
            Response::success(null, $hard ? 'Category permanently deleted' : 'Category soft deleted');
        }

        Response::error('Failed to delete category', 500);
    }

    /**
     * استعادة فئة (soft restore) - Admin
     * POST /api/categories/{id}/restore
     */
    public static function restore($id = null)
    {
        RoleMiddleware::canUpdate('categories');

        if (!$id) Response::validationError(['id' => ['Category id is required']]);
        $id = (int)$id;

        $categoryModel = new Category();
        $exists = $categoryModel->findById($id);
        if (!$exists) Response::error('Category not found', 404);

        $ok = $categoryModel->restore($id);
        if ($ok) {
            $cat = $categoryModel->findById($id);
            Response::success(['category' => $cat], 'Category restored');
        }

        Response::error('Failed to restore category', 500);
    }

    /**
     * جلب فئة (عرض عام)
     * GET /api/categories/{id_or_slug}
     */
    public static function show($idOrSlug = null)
    {
        if (!$idOrSlug) Response::validationError(['id' => ['Category id or slug is required']]);

        $categoryModel = new Category();
        $cat = is_numeric($idOrSlug) ? $categoryModel->findById((int)$idOrSlug) : $categoryModel->findBySlug($idOrSlug);

        if (!$cat) Response::error('Category not found', 404);

        Response::success($cat);
    }

    /**
     * قائمة الفئات - شجرية أو مسطحة
     * GET /api/categories
     */
    public static function index()
    {
        $q = $_GET;
        $tree = !isset($q['tree']) || $q['tree'] !== '0'; // default true
        $filters = [];
        if (isset($q['is_active'])) $filters['is_active'] = (int)$q['is_active'];
        if (isset($q['search'])) $filters['search'] = $q['search'];

        $categoryModel = new Category();
        $result = $categoryModel->getAll($filters, $tree);

        Response::success($result);
    }

    /**
     * إرفاق منتج إلى فئة (Admin)
     * POST /api/categories/{category_id}/attach-product
     */
    public static function attachProduct($categoryId = null)
    {
        RoleMiddleware::canUpdate('categories');

        if (!$categoryId) Response::validationError(['category_id' => ['Category id is required']]);
        $categoryId = (int)$categoryId;

        $input = $_POST;
        $productId = isset($input['product_id']) ? (int)$input['product_id'] : null;
        if (!$productId) Response::validationError(['product_id' => ['Product id is required']]);

        $isPrimary = isset($input['is_primary']) && $input['is_primary'] == '1';

        $categoryModel = new Category();
        $cat = $categoryModel->findById($categoryId);
        if (!$cat) Response::error('Category not found', 404);

        $productModel = new Product();
        $prod = $productModel->findById($productId);
        if (!$prod) Response::error('Product not found', 404);

        $ok = $categoryModel->attachProduct($categoryId, $productId, $isPrimary);
        if ($ok) {
            Response::success(null, 'Product attached to category');
        }

        Response::error('Failed to attach product', 500);
    }

    /**
     * فصل منتج من فئة (Admin)
     * POST /api/categories/{category_id}/detach-product
     */
    public static function detachProduct($categoryId = null)
    {
        RoleMiddleware::canUpdate('categories');

        if (!$categoryId) Response::validationError(['category_id' => ['Category id is required']]);
        $categoryId = (int)$categoryId;

        $input = $_POST;
        $productId = isset($input['product_id']) ? (int)$input['product_id'] : null;
        if (!$productId) Response::validationError(['product_id' => ['Product id is required']]);

        $categoryModel = new Category();
        $ok = $categoryModel->detachProduct($categoryId, $productId);
        if ($ok) {
            Response::success(null, 'Product detached from category');
        }

        Response::error('Failed to detach product', 500);
    }

    /**
     * جلب منتجات فئة (عام)
     * GET /api/categories/{id}/products
     */
    public static function products($categoryId = null)
    {
        if (!$categoryId) Response::validationError(['category_id' => ['Category id is required']]);
        $categoryId = (int)$categoryId;

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

        $categoryModel = new Category();
        $cat = $categoryModel->findById($categoryId);
        if (!$cat) Response::error('Category not found', 404);

        $res = $categoryModel->getProducts($categoryId, $perPage, $page);
        Response::success($res);
    }

    /**
     * إعادة ترتيب الفئات (Admin)
     * POST /api/categories/reorder
     * يتوقع body JSON: { "order": { "<categoryId>": <sortOrder>, ... } }
     */
    public static function reorder()
    {
        RoleMiddleware::canUpdate('categories');

        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $order = $body['order'] ?? null;
        if (!is_array($order)) {
            Response::validationError(['order' => ['Order data is required and must be an object']]);
        }

        $categoryModel = new Category();
        $ok = $categoryModel->reorder($order);
        if ($ok) {
            Response::success(null, 'Categories reordered successfully');
        }

        Response::error('Failed to reorder categories', 500);
    }

    /**
     * إحصائيات الفئات (Admin)
     * GET /api/categories/stats
     */
    public static function stats()
    {
        RoleMiddleware::canRead('categories');

        $categoryModel = new Category();
        $stats = $categoryModel->getStatistics();

        Response::success($stats);
    }
}

// ملاحظة: الروتين الفعلي للطرق يتم في ملف الراوتر العام.
// أمثلة:
// POST /api/categories => CategoryController::create();
// GET  /api/categories => CategoryController::index();

?>