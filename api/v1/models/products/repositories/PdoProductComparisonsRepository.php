<?php
declare(strict_types=1);

final class PdoProductComparisonsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'pc.id', 'pc.user_id', 'pc.product_id', 'pc.created_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'user_id', 'product_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * قائمة المقارنات مع إمكانية الفلترة والترتيب والصفحات
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'pc.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        // اسم المنتج الأساسي حسب اللغة
        $productNameField = $lang === 'ar' ? 'pt.name' : 'COALESCE(pt.name, p.name)'; // fallback

        $sql = "
            SELECT pc.*,
                   {$productNameField} AS main_product_name,
                   (SELECT COUNT(*) FROM product_comparison_items WHERE comparison_id = pc.id) AS items_count
            FROM product_comparisons pc
            INNER JOIN products p ON pc.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        // الفلاتر
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND pc.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // ترتيب
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'pc.id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

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

    /**
     * عدد المقارنات
     */
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM product_comparisons pc
            INNER JOIN products p ON pc.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND pc.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * جلب مقارنة محددة مع عناصرها
     */
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $productNameField = $lang === 'ar' ? 'pt.name' : 'COALESCE(pt.name, p.name)';

        $stmt = $this->pdo->prepare("
            SELECT pc.*,
                   {$productNameField} AS main_product_name
            FROM product_comparisons pc
            INNER JOIN products p ON pc.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id AND pc.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => $lang]);
        $comparison = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comparison) return null;

        // جلب العناصر (المنتجات المُقارنة)
        $stmtItems = $this->pdo->prepare("
            SELECT pci.*,
                   COALESCE(pt.name, p.name) AS product_name,
                   p.sku AS product_sku
            FROM product_comparison_items pci
            INNER JOIN products p ON pci.product_id = p.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE pci.comparison_id = :comparison_id
        ");
        $stmtItems->execute([':comparison_id' => $id, ':lang' => $lang]);
        $comparison['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        return $comparison;
    }

    /**
     * إنشاء مقارنة جديدة
     */
    public function create(int $tenantId, array $data): int
    {
        // التحقق من أن المنتج الرئيسي ينتمي للمستأجر
        $this->validateProductBelongsToTenant((int)$data['product_id'], $tenantId);

        $stmt = $this->pdo->prepare("
            INSERT INTO product_comparisons (user_id, product_id, created_at)
            VALUES (:user_id, :product_id, NOW())
        ");
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':product_id' => $data['product_id']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * تحديث مقارنة (يُسمح فقط بتغيير user_id أو product_id مع التحقق)
     */
    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Comparison ID required for update.');
        }

        // التحقق من وجود المقارنة وانتمائها للمستأجر عبر المنتج الرئيسي
        $check = $this->pdo->prepare("
            SELECT pc.id
            FROM product_comparisons pc
            INNER JOIN products p ON pc.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id AND pc.id = :id
        ");
        $check->execute([':tenant_id' => $tenantId, ':id' => $data['id']]);
        if (!$check->fetch()) {
            throw new InvalidArgumentException('Comparison not found or access denied.');
        }

        $fields = [];
        $params = [':id' => $data['id']];

        if (isset($data['user_id'])) {
            $fields[] = "user_id = :user_id";
            $params[':user_id'] = $data['user_id'];
        }
        if (isset($data['product_id'])) {
            // التحقق من أن المنتج الجديد ينتمي للمستأجر
            $this->validateProductBelongsToTenant((int)$data['product_id'], $tenantId);
            $fields[] = "product_id = :product_id";
            $params[':product_id'] = $data['product_id'];
        }

        if (empty($fields)) {
            return true; // لا شيء للتحديث
        }

        $sql = "UPDATE product_comparisons SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * حذف مقارنة (سيتم حذف العناصر المرتبطة تلقائياً إذا كان ON DELETE CASCADE)
     */
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE pc FROM product_comparisons pc
            INNER JOIN products p ON pc.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id AND pc.id = :id
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    /**
     * التحقق من أن المنتج ينتمي للمستأجر
     */
    private function validateProductBelongsToTenant(int $productId, int $tenantId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id FROM products p
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE p.id = :product_id AND e.tenant_id = :tenant_id
        ");
        $stmt->execute([':product_id' => $productId, ':tenant_id' => $tenantId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Product not found or does not belong to this tenant.');
        }
    }
}