<?php
declare(strict_types=1);

final class PdoProductBundlesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'bundle_name', 'bundle_name_ar',
        'original_total_price', 'bundle_price', 'discount_amount',
        'discount_percentage', 'stock_quantity', 'is_active',
        'start_date', 'end_date', 'sold_count', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'is_active', 'bundle_name', 'bundle_name_ar'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // جلب قائمة الحزم (مع Join entities للتأكد من tenant)
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
        $nameField = $lang === 'ar' ? 'pb.bundle_name_ar' : 'pb.bundle_name';
        $descField = $lang === 'ar' ? 'pb.description_ar' : 'pb.description';

        $sql = "
            SELECT pb.*,
                   {$nameField} AS name,
                   {$descField} AS description,
                   (SELECT COUNT(*) FROM product_bundle_items WHERE bundle_id = pb.id) AS items_count
            FROM product_bundles pb
            INNER JOIN entities e ON pb.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        // تطبيق الفلاتر
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['bundle_name', 'bundle_name_ar'])) {
                    $sql .= " AND pb.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND pb.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // ترتيب
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY pb.{$orderBy} {$orderDir}";

        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // عدد الحزم
    // ================================
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM product_bundles pb
            INNER JOIN entities e ON pb.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['bundle_name', 'bundle_name_ar'])) {
                    $sql .= " AND pb.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND pb.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // جلب حزمة واحدة مع عناصرها
    // ================================
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $nameField = $lang === 'ar' ? 'pb.bundle_name_ar' : 'pb.bundle_name';
        $descField = $lang === 'ar' ? 'pb.description_ar' : 'pb.description';

        $stmt = $this->pdo->prepare("
            SELECT pb.*,
                   {$nameField} AS name,
                   {$descField} AS description
            FROM product_bundles pb
            INNER JOIN entities e ON pb.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND pb.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $bundle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bundle) return null;

        // جلب العناصر
        $stmtItems = $this->pdo->prepare("
            SELECT pbi.*,
                   p.name AS product_name,
                   p.sku AS product_sku
            FROM product_bundle_items pbi
            LEFT JOIN products p ON p.id = pbi.product_id
            WHERE pbi.bundle_id = :bundle_id
        ");
        $stmtItems->execute([':bundle_id' => $id]);
        $bundle['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        return $bundle;
    }

    // ================================
    // التحقق من أن entity_id تابع للمستأجر
    // ================================
    private function validateEntityBelongsToTenant(int $entityId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM entities WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute([':id' => $entityId, ':tenant_id' => $tenantId]);
        return $stmt->fetch() !== false;
    }

    // ================================
    // إنشاء / تحديث حزمة
    // ================================
    private const BUNDLE_COLUMNS = [
        'entity_id', 'bundle_name', 'bundle_name_ar', 'description',
        'description_ar', 'bundle_image', 'original_total_price', 'bundle_price',
        'discount_amount', 'discount_percentage', 'stock_quantity', 'is_active',
        'start_date', 'end_date', 'sold_count'
    ];

    public function save(int $tenantId, array $data): int
    {
        if (empty($data['entity_id'])) {
            throw new InvalidArgumentException('entity_id is required.');
        }
        if (!$this->validateEntityBelongsToTenant((int)$data['entity_id'], $tenantId)) {
            throw new InvalidArgumentException('Invalid entity_id for this tenant.');
        }

        $isUpdate = !empty($data['id']);
        $params = [];

        foreach (self::BUNDLE_COLUMNS as $col) {
            $params[':' . $col] = (array_key_exists($col, $data) && $data[$col] !== '') ? $data[$col] : null;
        }

        if (empty($params[':bundle_name']) && empty($params[':bundle_name_ar'])) {
            throw new InvalidArgumentException('Bundle name (English or Arabic) is required.');
        }

        // حساب الخصم تلقائياً
        if (isset($params[':original_total_price'], $params[':bundle_price'])
            && $params[':original_total_price'] > 0
            && $params[':bundle_price'] !== null
        ) {
            $original = (float)$params[':original_total_price'];
            $bundle   = (float)$params[':bundle_price'];
            $discountAmount = $original - $bundle;
            $discountPercent = ($discountAmount / $original) * 100;
            $params[':discount_amount'] = round($discountAmount, 2);
            $params[':discount_percentage'] = round($discountPercent, 2);
        }

        if ($isUpdate) {
            // التحقق من ملكية الحزمة
            $check = $this->pdo->prepare("
                SELECT pb.id FROM product_bundles pb
                INNER JOIN entities e ON pb.entity_id = e.id
                WHERE e.tenant_id = :tenant_id AND pb.id = :id
            ");
            $check->execute([':tenant_id' => $tenantId, ':id' => $data['id']]);
            if (!$check->fetch()) {
                throw new InvalidArgumentException('Bundle not found or access denied.');
            }

            $params[':id'] = (int)$data['id'];
            $sql = "UPDATE product_bundles SET ";
            $set = [];
            foreach (self::BUNDLE_COLUMNS as $col) {
                $set[] = "$col = :$col";
            }
            $set[] = "updated_at = CURRENT_TIMESTAMP";
            $sql .= implode(', ', $set) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $bundleId = (int)$data['id'];
        } else {
            $cols = implode(', ', self::BUNDLE_COLUMNS);
            $placeholders = ':' . implode(', :', self::BUNDLE_COLUMNS);
            $sql = "INSERT INTO product_bundles ($cols) VALUES ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $bundleId = (int)$this->pdo->lastInsertId();
        }

        // التعامل مع العناصر
        if (isset($data['items']) && is_array($data['items'])) {
            $this->saveItems($bundleId, $data['items']);
        }

        return $bundleId;
    }

    // ================================
    // حفظ العناصر (حذف + إدراج)
    // ================================
    private function saveItems(int $bundleId, array $items): void
    {
        $this->pdo->prepare("DELETE FROM product_bundle_items WHERE bundle_id = ?")->execute([$bundleId]);
        if (empty($items)) return;

        $sql = "INSERT INTO product_bundle_items (bundle_id, product_id, quantity, product_price) VALUES ";
        $values = [];
        foreach ($items as $index => $item) {
            if (empty($item['product_id'])) continue;
            $values[] = "($bundleId, " . (int)$item['product_id'] . ", " . (int)($item['quantity'] ?? 1) . ", " . (float)($item['product_price'] ?? 0) . ")";
        }
        if (!empty($values)) {
            $this->pdo->exec($sql . implode(', ', $values));
        }
    }

    // ================================
    // حذف حزمة
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE pb FROM product_bundles pb
            INNER JOIN entities e ON pb.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND pb.id = :id
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }
}