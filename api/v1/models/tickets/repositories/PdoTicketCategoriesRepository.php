<?php
declare(strict_types=1);

final class PdoTicketCategoriesRepository implements TicketCategoriesRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'ticket_categories';
    private const TABLE_TRANS = 'ticket_category_translations';
    private const ALLOWED_ORDER_BY = ['id', 'name', 'priority_level', 'is_active', 'created_at'];
    private const FILTERABLE_COLUMNS = ['is_active', 'parent_id'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        // تم تصحيح أسماء المعاملات لتجنب تكرار :lang
        $sql = "
            SELECT c.*, 
                   t.name, 
                   t.description,
                   p_t.name AS parent_name
            FROM " . self::TABLE . " c
            LEFT JOIN " . self::TABLE_TRANS . " t 
                ON c.id = t.category_id AND t.language_code = :lang_main
            LEFT JOIN " . self::TABLE_TRANS . " p_t
                ON c.parent_id = p_t.category_id AND p_t.language_code = :lang_parent
            WHERE (c.tenant_id = :tenant_id OR c.tenant_id IS NULL)
        ";
        
        // تعريف المعاملات بأسماء فريدة
        $params = [
            ':tenant_id' => $tenantId, 
            ':lang_main' => $lang,
            ':lang_parent' => $lang
        ];

        // إضافة الفلاتر
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND c.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // البحث في الاسم
        if (!empty($filters['search'])) {
            $sql .= " AND (t.name LIKE :search OR t.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // الترتيب
        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        
        if ($orderBy === 'name') {
            $sql .= " ORDER BY t.name {$orderDir}";
        } else {
            $sql .= " ORDER BY c.{$orderBy} {$orderDir}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        
        // ربط المعاملات
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset ?? 0, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE (tenant_id = :tenant_id OR tenant_id IS NULL)";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, 
                   t.name, 
                   t.description
            FROM " . self::TABLE . " c
            LEFT JOIN " . self::TABLE_TRANS . " t 
                ON c.id = t.category_id AND t.language_code = :lang
            WHERE c.id = :id AND (c.tenant_id = :tenant_id OR c.tenant_id IS NULL)
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) return null;

        // جلب كل الترجمات
        $transStmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE_TRANS . " WHERE category_id = :id");
        $transStmt->execute([':id' => $id]);
        $row['translations'] = $transStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        return $row;
    }

    public function save(int $tenantId, array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            $isUpdate = !empty($data['id']);
            $categoryId = $isUpdate ? (int)$data['id'] : null;

            // 1. حفظ البيانات الأساسية
            $baseParams = [
                ':parent_id'      => isset($data['parent_id']) ? (int)$data['parent_id'] : null,
                ':priority_level' => $data['priority_level'] ?? 3,
                ':is_active'      => $data['is_active'] ?? 1,
            ];

            if ($isUpdate) {
                $stmt = $this->pdo->prepare("
                    UPDATE " . self::TABLE . " SET
                        parent_id = :parent_id,
                        priority_level = :priority_level,
                        is_active = :is_active
                    WHERE id = :id AND tenant_id = :tenant_id
                ");
                $baseParams[':id'] = $categoryId;
                $baseParams[':tenant_id'] = $tenantId;
                $stmt->execute($baseParams);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO " . self::TABLE . " (
                        tenant_id, parent_id, priority_level, is_active
                    ) VALUES (
                        :tenant_id, :parent_id, :priority_level, :is_active
                    )
                ");
                $baseParams[':tenant_id'] = $tenantId;
                $stmt->execute($baseParams);
                $categoryId = (int)$this->pdo->lastInsertId();
            }

            // 2. حفظ الترجمات (Upsert)
            $currentLang = $data['language_code'] ?? 'ar';
            $name = $data['name'] ?? '';
            $desc = $data['description'] ?? null;

            $translationsToSave = [];
            if (isset($data['translations']) && is_array($data['translations'])) {
                $translationsToSave = $data['translations'];
            } elseif ($name) {
                $translationsToSave[] = [
                    'language_code' => $currentLang,
                    'name' => $name,
                    'description' => $desc
                ];
            }

            foreach ($translationsToSave as $trans) {
                if (empty($trans['name'])) continue;
                
                $transStmt = $this->pdo->prepare("
                    INSERT INTO " . self::TABLE_TRANS . " (category_id, language_code, name, description)
                    VALUES (:cat_id, :lang, :name, :desc)
                    ON DUPLICATE KEY UPDATE 
                        name = VALUES(name), 
                        description = VALUES(description)
                ");
                $transStmt->execute([
                    ':cat_id' => $categoryId,
                    ':lang'   => $trans['language_code'],
                    ':name'   => $trans['name'],
                    ':desc'   => $trans['description'] ?? null
                ]);
            }

            $this->pdo->commit();
            return $categoryId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE id = :id AND tenant_id = :tenant_id"
        );
        return $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
    }
}