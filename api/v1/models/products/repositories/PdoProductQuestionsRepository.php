<?php
declare(strict_types=1);

final class PdoProductQuestionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'pq.id', 'pq.product_id', 'pq.user_id', 'pq.is_approved', 'pq.helpful_count', 'pq.created_at', 'pq.updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'product_id', 'user_id', 'is_approved'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * جلب قائمة الأسئلة مع الفلاتر والترتيب والصفحات
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'pq.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        // اسم المنتج المترجم
        $productNameField = $lang === 'ar' ? 'pt.name' : 'COALESCE(pt.name, p.name)';

        $sql = "
            SELECT pq.*,
                   {$productNameField} AS product_name
            FROM product_questions pq
            INNER JOIN products p ON pq.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [
            ':tenant_id' => $tenantId,
            ':lang'      => $lang
        ];

        // إضافة الفلاتر
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND pq.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // ترتيب
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'pq.id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        // صفحات
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * عدد الأسئلة
     */
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM product_questions pq
            INNER JOIN products p ON pq.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND pq.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * جلب سؤال محدد
     */
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $productNameField = $lang === 'ar' ? 'pt.name' : 'COALESCE(pt.name, p.name)';

        $sql = "
            SELECT pq.*,
                   {$productNameField} AS product_name
            FROM product_questions pq
            INNER JOIN products p ON pq.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id AND pq.id = :id
            LIMIT 1
        ";
        $params = [
            ':tenant_id' => $tenantId,
            ':id'        => $id,
            ':lang'      => $lang
        ];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        return $question ?: null;
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

    /**
     * إنشاء سؤال جديد
     */
    public function create(int $tenantId, array $data): int
    {
        // التحقق من أن المنتج ينتمي للمستأجر
        $this->validateProductBelongsToTenant((int)$data['product_id'], $tenantId);

        $sql = "
            INSERT INTO product_questions (product_id, user_id, question, is_approved, helpful_count, created_at, updated_at)
            VALUES (:product_id, :user_id, :question, :is_approved, :helpful_count, NOW(), NOW())
        ";
        $params = [
            ':product_id'    => $data['product_id'],
            ':user_id'       => $data['user_id'],
            ':question'      => $data['question'],
            ':is_approved'   => $data['is_approved'] ?? 0,
            ':helpful_count' => $data['helpful_count'] ?? 0
        ];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * تحديث سؤال
     */
    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Question ID required for update.');
        }

        // التحقق من وجود السؤال وانتمائه للمستأجر
        $check = $this->pdo->prepare("
            SELECT pq.id
            FROM product_questions pq
            INNER JOIN products p ON pq.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id AND pq.id = :id
        ");
        $check->execute([':tenant_id' => $tenantId, ':id' => $data['id']]);
        if (!$check->fetch()) {
            throw new InvalidArgumentException('Question not found or access denied.');
        }

        $fields = [];
        $params = [':id' => $data['id']];

        if (isset($data['product_id'])) {
            // إذا تم تغيير المنتج، نتحقق من المنتج الجديد
            $this->validateProductBelongsToTenant((int)$data['product_id'], $tenantId);
            $fields[] = "product_id = :product_id";
            $params[':product_id'] = $data['product_id'];
        }
        if (isset($data['user_id'])) {
            $fields[] = "user_id = :user_id";
            $params[':user_id'] = $data['user_id'];
        }
        if (isset($data['question'])) {
            $fields[] = "question = :question";
            $params[':question'] = $data['question'];
        }
        if (array_key_exists('is_approved', $data)) {
            $fields[] = "is_approved = :is_approved";
            $params[':is_approved'] = $data['is_approved'] ? 1 : 0;
        }
        if (array_key_exists('helpful_count', $data)) {
            $fields[] = "helpful_count = :helpful_count";
            $params[':helpful_count'] = $data['helpful_count'];
        }

        // نضيف updated_at تلقائياً
        $fields[] = "updated_at = NOW()";

        if (empty($fields) && empty($fieldsWithoutTime)) { // لو لم يتغير شيء، نرجع true
            return true;
        }

        $sql = "UPDATE product_questions SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * حذف سؤال
     */
    public function delete(int $tenantId, int $id): bool
    {
        $sql = "
            DELETE pq FROM product_questions pq
            INNER JOIN products p ON pq.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id AND pq.id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    /**
     * حذف جميع أسئلة منتج معين
     */
    public function deleteByProduct(int $tenantId, int $productId): bool
    {
        $this->validateProductBelongsToTenant($productId, $tenantId);
        $sql = "DELETE FROM product_questions WHERE product_id = :product_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':product_id' => $productId]);
    }

    /**
     * زيادة عدد المساعدة (helpful_count)
     */
    public function incrementHelpful(int $tenantId, int $id): bool
    {
        $check = $this->pdo->prepare("
            SELECT pq.id
            FROM product_questions pq
            INNER JOIN products p ON pq.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id AND pq.id = :id
        ");
        $check->execute([':tenant_id' => $tenantId, ':id' => $id]);
        if (!$check->fetch()) {
            throw new InvalidArgumentException('Question not found or access denied.');
        }

        $stmt = $this->pdo->prepare("UPDATE product_questions SET helpful_count = helpful_count + 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}