<?php
// htdocs/api/controllers/ProductController.php
// Controller لإدارة المنتجات (CRUD, الصور, التصنيفات, المخزون, قوائم للواجهة)

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/upload.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';

class ProductController
{
    /**
     * إنشاء منتج جديد (التجار فقط أو Admin)
     * POST /api/products
     */
    public static function create()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        // إذا لم يكن admin، يجب أن يكون vendor
        if (!AuthMiddleware::isAdmin()) {
            if ($user['user_type'] !== USER_TYPE_VENDOR) {
                Response::forbidden('Only vendors or admins can create products');
            }
        }

        $input = $_POST;

        $rules = [
            'sku' => 'required|string|min:1|max:100|unique:products,sku',
            'slug' => 'optional|string|min:1|max:150|unique:products,slug',
            'product_type' => 'optional|string',
            'brand_id' => 'optional|integer',
            'barcode' => 'optional|string',
            'stock_quantity' => 'optional|integer',
            'low_stock_threshold' => 'optional|integer',
            'manage_stock' => 'optional|boolean',
            'price' => 'required|numeric',
            'name' => 'required|string|min:1|max:255',
            'description' => 'optional|string',
            'categories' => 'optional|array'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $productModel = new Product();

        // vendor_id assignment
        if (AuthMiddleware::isAdmin() && isset($input['vendor_id'])) {
            $vendorId = (int)$input['vendor_id'];
        } else {
            // find vendor by user
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            if (!$vendor) {
                Response::error('Vendor account not found for this user', 400);
            }
            $vendorId = $vendor['id'];
        }

        // ensure slug
        $slug = $validated['slug'] ?? Utils::createSlug($validated['name']);
        if (!$productModel->isSlugUnique($slug)) {
            $slug .= '-' . substr(Utils::generateUUID(), 0, 6);
        }

        $createData = [
            'vendor_id' => $vendorId,
            'sku' => $validated['sku'],
            'slug' => $slug,
            'barcode' => $validated['barcode'] ?? null,
            'product_type' => $validated['product_type'] ?? PRODUCT_TYPE_SIMPLE,
            'brand_id' => $validated['brand_id'] ?? null,
            'manufacturer_id' => $input['manufacturer_id'] ?? null,
            'is_active' => $input['is_active'] ?? 1,
            'is_featured' => $input['is_featured'] ?? 0,
            'is_new' => $input['is_new'] ?? 1,
            'stock_quantity' => $validated['stock_quantity'] ?? 0,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 5,
            'stock_status' => $input['stock_status'] ?? STOCK_STATUS_IN_STOCK,
            'manage_stock' => isset($validated['manage_stock']) ? (int)$validated['manage_stock'] : 1,
            'allow_backorder' => $input['allow_backorder'] ?? 0,
            'weight' => $input['weight'] ?? null,
            'length' => $input['length'] ?? null,
            'width' => $input['width'] ?? null,
            'height' => $input['height'] ?? null,
            'tax_rate' => $input['tax_rate'] ?? DEFAULT_TAX_RATE
        ];

        $newProduct = $productModel->create($createData);

        if (!$newProduct) {
            Response::error('Failed to create product', 500);
        }

        // حفظ الترجمة الأساسية
        $productModel->saveTranslation($newProduct['id'], DEFAULT_LANGUAGE, [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'short_description' => $input['short_description'] ?? null
        ]);

        // حفظ التسعير
        $productModel->savePricing($newProduct['id'], [
            'price' => (float)$validated['price'],
            'cost_price' => $input['cost_price'] ?? null,
            'compare_at_price' => $input['compare_at_price'] ?? null,
            'discount_type' => $input['discount_type'] ?? null,
            'discount_value' => $input['discount_value'] ?? null
        ]);

        // ربط التصنيفات إن وُجدت
        if (!empty($validated['categories']) && is_array($validated['categories'])) {
            foreach ($validated['categories'] as $catId) {
                $productModel->attachCategory($newProduct['id'], (int)$catId, false);
            }
        }

        // إضافة صور إن تم رفعها (expect files 'images[]')
        if (!empty($_FILES['images'])) {
            $files = Upload::restructureFiles($_FILES['images']);
            foreach ($files as $idx => $file) {
                $res = Upload::uploadImage($file, 'products', 1200, 1200, true);
                if ($res['success']) {
                    $productModel->addImage($newProduct['id'], $res['file_url'], $idx === 0, $idx);
                }
            }
        }

        $product = $productModel->findById($newProduct['id']);
        Response::created(['product' => $product], 'Product created successfully');
    }

    /**
     * تحديث منتج
     * PUT /api/products/{id}
     */
    public static function update($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        if (!$id) Response::validationError(['id' => ['Product id is required']]);
        $id = (int)$id;

        $productModel = new Product();
        $existing = $productModel->findById($id);
        if (!$existing) Response::error('Product not found', 404);

        // تحقق الملكية (vendors only update their products)
        if (!AuthMiddleware::isAdmin()) {
            if ($existing['vendor_id'] != $user['id'] && $user['user_type'] === USER_TYPE_VENDOR) {
                // vendors store vendor.user_id points to users.id; our product vendor_id is vendors.id
                // Need to fetch vendor to compare user_id
                $vendorModel = new Vendor();
                $vendor = $vendorModel->findById($existing['vendor_id']);
                if (!$vendor || $vendor['user_id'] != $user['id']) {
                    Response::forbidden('You do not have permission to update this product');
                }
            } elseif ($user['user_type'] !== USER_TYPE_VENDOR) {
                Response::forbidden('Only vendor who owns the product or admin can update');
            }
        }

        $input = $_POST;
        $rules = [
            'sku' => "optional|string|min:1|max:100|unique:products,sku,{$id}",
            'slug' => "optional|string|min:1|max:150|unique:products,slug,{$id}",
            'stock_quantity' => 'optional|integer',
            'manage_stock' => 'optional|boolean',
            'price' => 'optional|numeric',
            'name' => 'optional|string|max:255',
            'description' => 'optional|string',
            'is_active' => 'optional|boolean'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $updateData = [];
        $fields = ['sku','slug','barcode','product_type','brand_id','manufacturer_id','is_active','is_featured','is_bestseller','is_new','stock_quantity','low_stock_threshold','stock_status','manage_stock','allow_backorder','weight','length','width','height','tax_rate'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $input)) $updateData[$f] = $input[$f];
        }

        // Update product table
        $ok = $productModel->update($id, $updateData);

        if ($ok) {
            // update translation if provided
            if (isset($validated['name']) || isset($validated['description'])) {
                $productModel->saveTranslation($id, DEFAULT_LANGUAGE, [
                    'name' => $validated['name'] ?? $existing['translations'][DEFAULT_LANGUAGE]['name'] ?? null,
                    'description' => $validated['description'] ?? $existing['translations'][DEFAULT_LANGUAGE]['description'] ?? null,
                    'short_description' => $input['short_description'] ?? ($existing['translations'][DEFAULT_LANGUAGE]['short_description'] ?? null)
                ]);
            }

            // update pricing if provided
            if (isset($input['price']) || isset($input['compare_at_price']) || isset($input['cost_price'])) {
                $productModel->savePricing($id, [
                    'price' => isset($input['price']) ? (float)$input['price'] : ($existing['pricing']['price'] ?? 0),
                    'cost_price' => $input['cost_price'] ?? ($existing['pricing']['cost_price'] ?? null),
                    'compare_at_price' => $input['compare_at_price'] ?? ($existing['pricing']['compare_at_price'] ?? null),
                    'discount_type' => $input['discount_type'] ?? ($existing['pricing']['discount_type'] ?? null),
                    'discount_value' => $input['discount_value'] ?? ($existing['pricing']['discount_value'] ?? null)
                ]);
            }

            // attach/detach categories
            if (isset($input['categories']) && is_array($input['categories'])) {
                // detach all then attach provided (simple approach)
                $categoryModel = new Category();
                $categoryModel->detachAllProducts($id); // Note: Category model had detachAllProducts(categoryId) not product version; do direct query
                // implement direct removal
                $mysqli = connectDB();
                $stmt = $mysqli->prepare("DELETE FROM product_categories WHERE product_id = ?");
                if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }

                foreach ($input['categories'] as $catId) {
                    $productModel->attachCategory($id, (int)$catId, false);
                }
            }

            // handle image uploads
            if (!empty($_FILES['images'])) {
                $files = Upload::restructureFiles($_FILES['images']);
                foreach ($files as $idx => $file) {
                    $res = Upload::uploadImage($file, 'products', 1200, 1200, true);
                    if ($res['success']) {
                        $productModel->addImage($id, $res['file_url'], $idx === 0, $idx);
                    }
                }
            }

            $updated = $productModel->findById($id);
            Response::success(['product' => $updated], 'Product updated successfully');
        }

        Response::error('Failed to update product', 500);
    }

    /**
     * جلب منتج بالـ id أو slug
     * GET /api/products/{id_or_slug}
     */
    public static function show($idOrSlug = null)
    {
        if (!$idOrSlug) Response::validationError(['id' => ['Product id or slug required']]);

        $productModel = new Product();
        $product = is_numeric($idOrSlug) ? $productModel->findById((int)$idOrSlug) : $productModel->findBySlug($idOrSlug);

        if (!$product) Response::error('Product not found', 404);

        Response::success($product);
    }

    /**
     * قائمة المنتجات مع فلترة للواجهة و admin
     * GET /api/products
     */
    public static function index()
    {
        $q = $_GET;
        $page = isset($q['page']) ? (int)$q['page'] : 1;
        $perPage = isset($q['per_page']) ? (int)$q['per_page'] : 20;

        $filters = [];
        if (!empty($q['vendor_id'])) $filters['vendor_id'] = (int)$q['vendor_id'];
        if (!empty($q['category_id'])) $filters['category_id'] = (int)$q['category_id'];
        if (!empty($q['min_price'])) $filters['min_price'] = (float)$q['min_price'];
        if (!empty($q['max_price'])) $filters['max_price'] = (float)$q['max_price'];
        if (isset($q['is_featured'])) $filters['is_featured'] = (int)$q['is_featured'];
        if (isset($q['is_new'])) $filters['is_new'] = (int)$q['is_new'];
        if (!empty($q['search'])) $filters['search'] = $q['search'];
        if (!empty($q['sort'])) $filters['sort'] = $q['sort'];

        $productModel = new Product();
        $result = $productModel->getAll($filters, $page, $perPage);

        Response::success($result);
    }

    /**
     * حذف منتج (soft/hard)
     * DELETE /api/products/{id}
     */
    public static function delete($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        if (!$id) Response::validationError(['id' => ['Product id is required']]);
        $id = (int)$id;

        $productModel = new Product();
        $product = $productModel->findById($id);
        if (!$product) Response::error('Product not found', 404);

        // Authorization: admin or owner vendor
        if (!AuthMiddleware::isAdmin()) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findById($product['vendor_id']);
            if (!$vendor || $vendor['user_id'] != $user['id']) {
                Response::forbidden('You do not have permission to delete this product');
            }
        } else {
            // admin allowed
        }

        $hard = isset($_GET['hard']) && $_GET['hard'] == '1';
        $success = $hard ? $productModel->delete($id) : $productModel->softDelete($id);

        if ($success) {
            Response::success(null, $hard ? 'Product permanently deleted' : 'Product soft deleted');
        }

        Response::error('Failed to delete product', 500);
    }

    /**
     * تحديث المخزون (Vendor or Admin)
     * POST /api/products/{id}/stock
     */
    public static function updateStock($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Product id is required']]);
        $id = (int)$id;

        $productModel = new Product();
        $product = $productModel->findById($id);
        if (!$product) Response::error('Product not found', 404);

        // Ownership check for vendor
        if (!AuthMiddleware::isAdmin()) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findById($product['vendor_id']);
            if (!$vendor || $vendor['user_id'] != $user['id']) {
                Response::forbidden('You do not have permission to update stock for this product');
            }
        }

        $input = $_POST;
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : null;
        $operation = $input['operation'] ?? 'set'; // add, subtract, set

        if ($quantity === null) Response::validationError(['quantity' => ['Quantity is required']]);

        $ok = $productModel->updateStock($id, $quantity, $operation);
        if ($ok) {
            $updated = $productModel->findById($id);
            Response::success(['product' => $updated], 'Stock updated successfully');
        }

        Response::error('Failed to update stock', 500);
    }

    /**
     * تحميل صورة لمنتج منفرد
     * POST /api/products/{id}/images
     * expects file 'image'
     */
    public static function uploadImage($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Product id is required']]);
        $id = (int)$id;

        $productModel = new Product();
        $product = $productModel->findById($id);
        if (!$product) Response::error('Product not found', 404);

        // Ownership check
        if (!AuthMiddleware::isAdmin()) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findById($product['vendor_id']);
            if (!$vendor || $vendor['user_id'] != $user['id']) {
                Response::forbidden('You do not have permission to upload images for this product');
            }
        }

        if (!isset($_FILES['image'])) {
            Response::validationError(['image' => ['Image file is required']]);
        }

        $res = Upload::uploadImage($_FILES['image'], 'products', 1200, 1200, true);
        if (!$res['success']) {
            Response::error($res['message'] ?? 'Upload failed', 400);
        }

        $isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] == '1';
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

        $ok = $productModel->addImage($id, $res['file_url'], $isPrimary, $sortOrder);
        if ($ok) {
            $img = $productModel->getPrimaryImage($id);
            Response::success(['image_url' => $res['file_url'], 'primary_image' => $img], 'Image uploaded');
        }

        Response::error('Failed to save image', 500);
    }

    /**
     * إظهار المنتجات المميزة أو البحث سريع (helper)
     * GET /api/products/featured
     */
    public static function featured()
    {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $productModel = new Product();
        $data = $productModel->getFeatured($limit);
        Response::success($data);
    }
}

// End ProductController
?>