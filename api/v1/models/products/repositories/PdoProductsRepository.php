<?php
declare(strict_types=1);

final class PdoProductsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id','sku','slug','barcode','brand_id','is_active',
        'is_featured','is_bestseller','is_new','stock_quantity',
        'low_stock_threshold','stock_status','manage_stock','allow_backorder',
        'total_sales','rating_average','rating_count','views_count',
        'created_at','updated_at','published_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'product_type_id','sku','slug','barcode','brand_id','is_active',
        'is_featured','is_bestseller','is_new','stock_status','manage_stock','allow_backorder'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT p.*,
                   COALESCE(pt.name, '') AS name,
                   pt.short_description,
                   pt.description AS translated_description,
                   pt.meta_title,
                   pt.meta_description,
                   pt.meta_keywords,
                   i.id AS image_id,
                   i.url AS image_url,
                   i.thumb_url AS image_thumb_url,
                   pp.price,
                   pp.compare_at_price,
                   pp.cost_price,
                   pp.currency_code,
                   pp.tax_rate,
                   pp.pricing_type
            FROM products p
            LEFT JOIN product_translations pt
                ON p.id = pt.product_id AND pt.language_code = :lang
            LEFT JOIN images i
                ON i.owner_id = p.id
               AND i.is_main = 1
               AND i.image_type_id = (
                   SELECT id FROM image_types WHERE name = 'product' LIMIT 1
               )
            LEFT JOIN product_pricing pp
                ON pp.product_id = p.id
               AND pp.variant_id IS NULL
               AND pp.is_active = 1
            WHERE p.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['sku','slug','barcode'])) {
                    $sql .= " AND p.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND p.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY p.{$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count for pagination
    // ================================
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM products WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['sku','slug','barcode'])) {
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   COALESCE(pt.name, '') AS name,
                   pt.short_description,
                   pt.description AS translated_description,
                   pt.specifications,
                   pt.meta_title,
                   pt.meta_description,
                   pt.meta_keywords,
                   i.id AS image_id,
                   i.url AS image_url,
                   i.thumb_url AS image_thumb_url
            FROM products p
            LEFT JOIN product_translations pt
                ON p.id = pt.product_id AND pt.language_code = :lang
            LEFT JOIN images i
                ON i.owner_id = p.id
               AND i.is_main = 1
               AND i.image_type_id = (
                   SELECT id FROM image_types WHERE name = 'product' LIMIT 1
               )
            WHERE p.tenant_id = :tenant_id AND p.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id'=>$tenantId, ':id'=>$id, ':lang'=>$lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================

    // الأعمدة المسموحة في جدول products فقط
    private const PRODUCT_COLUMNS = [
        'product_type_id', 'sku', 'slug', 'barcode', 'brand_id',
        'is_active', 'is_featured', 'is_bestseller', 'is_new',
        'stock_quantity', 'low_stock_threshold', 'stock_status',
        'manage_stock', 'allow_backorder', 'total_sales',
        'rating_average', 'rating_count', 'views_count', 'published_at'
    ];

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط من البيانات الواردة
        $params = [];
        foreach (self::PRODUCT_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                // تحويل القيم الفارغة إلى null للأعمدة الاختيارية
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // توليد SKU تلقائياً إذا كان فارغاً
        if (empty($params[':sku']) || $params[':sku'] === null) {
            $params[':sku'] = 'PRD-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
        }

        // توليد slug تلقائياً إذا كان فارغاً
        if (empty($params[':slug']) || $params[':slug'] === null) {
            $name = $data['name'] ?? $params[':sku'];
            $params[':slug'] = preg_replace('/[^a-z0-9\p{Arabic}\-]+/u', '-', mb_strtolower(trim($name)));
            $params[':slug'] = trim($params[':slug'], '-');
            if (empty($params[':slug'])) {
                $params[':slug'] = 'product-' . time();
            }
            // إضافة رقم عشوائي لتجنب التكرار
            $params[':slug'] .= '-' . mt_rand(1000, 9999);
        }

        // product_type_id مطلوب (NOT NULL) - تعيين قيمة افتراضية 1 إذا لم يتم تحديده
        if (empty($params[':product_type_id'])) {
            $params[':product_type_id'] = 1;
        }

        if ($isUpdate) {
            $params[':tenant_id'] = $tenantId;
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE products SET
                    product_type_id = :product_type_id,
                    sku = :sku,
                    slug = :slug,
                    barcode = :barcode,
                    brand_id = :brand_id,
                    is_active = :is_active,
                    is_featured = :is_featured,
                    is_bestseller = :is_bestseller,
                    is_new = :is_new,
                    stock_quantity = :stock_quantity,
                    low_stock_threshold = :low_stock_threshold,
                    stock_status = :stock_status,
                    manage_stock = :manage_stock,
                    allow_backorder = :allow_backorder,
                    total_sales = :total_sales,
                    rating_average = :rating_average,
                    rating_count = :rating_count,
                    views_count = :views_count,
                    published_at = :published_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE tenant_id = :tenant_id AND id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $params[':tenant_id'] = $tenantId;

        $stmt = $this->pdo->prepare("
            INSERT INTO products (
                tenant_id, product_type_id, sku, slug, barcode, brand_id,
                is_active, is_featured, is_bestseller, is_new,
                stock_quantity, low_stock_threshold, stock_status,
                manage_stock, allow_backorder, total_sales,
                rating_average, rating_count, views_count, published_at
            ) VALUES (
                :tenant_id, :product_type_id, :sku, :slug, :barcode, :brand_id,
                :is_active, :is_featured, :is_bestseller, :is_new,
                :stock_quantity, :low_stock_threshold, :stock_status,
                :manage_stock, :allow_backorder, :total_sales,
                :rating_average, :rating_count, :views_count, :published_at
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM products WHERE tenant_id = :tenant_id AND id = :id"
        );
        return $stmt->execute([':tenant_id'=>$tenantId, ':id'=>$id]);
    }
}