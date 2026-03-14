<?php
declare(strict_types=1);

final class PdoEntitiesAttributeValuesRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'attribute_id', 'value', 'created_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'entity_id', 'attribute_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT eav.*,
                   e.store_name,
                   e.status as entity_status,
                   ea.slug as attribute_slug,
                   ea.attribute_type,
                   eat.name as attribute_name,
                   eat.description as attribute_description
            FROM entities_attribute_values eav
            LEFT JOIN entities e ON eav.entity_id = e.id
            LEFT JOIN entities_attributes ea ON eav.attribute_id = ea.id
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (is_numeric($filters[$col])) {
                    $sql .= " AND eav.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        // فلتر إضافي للبحث في القيمة
        if (isset($filters['value']) && !empty($filters['value'])) {
            $sql .= " AND eav.value LIKE :value";
            $params[":value"] = '%' . $filters['value'] . '%';
        }

        // فلتر إضافي لاسم المتجر
        if (isset($filters['store_name']) && !empty($filters['store_name'])) {
            $sql .= " AND e.store_name LIKE :store_name";
            $params[":store_name"] = '%' . $filters['store_name'] . '%';
        }

        // فلتر إضافي لحالة الكيان
        if (isset($filters['entity_status']) && in_array($filters['entity_status'], ['pending', 'approved', 'suspended', 'rejected'])) {
            $sql .= " AND e.status = :entity_status";
            $params[":entity_status"] = $filters['entity_status'];
        }

        // فلتر إضافي لنوع الخاصية
        if (isset($filters['attribute_type']) && in_array($filters['attribute_type'], ['text', 'number', 'select', 'boolean'])) {
            $sql .= " AND ea.attribute_type = :attribute_type";
            $params[":attribute_type"] = $filters['attribute_type'];
        }

        // فلتر إضافي لslug الخاصية
        if (isset($filters['attribute_slug']) && !empty($filters['attribute_slug'])) {
            $sql .= " AND ea.slug LIKE :attribute_slug";
            $params[":attribute_slug"] = '%' . $filters['attribute_slug'] . '%';
        }

        // فلتر إضافي لاسم الخاصية (الترجمة)
        if (isset($filters['attribute_name']) && !empty($filters['attribute_name'])) {
            $sql .= " AND eat.name LIKE :attribute_name";
            $params[":attribute_name"] = '%' . $filters['attribute_name'] . '%';
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY eav.{$orderBy} {$orderDir}";

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
    public function count(array $filters = [], string $lang = 'ar'): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM entities_attribute_values eav
            LEFT JOIN entities e ON eav.entity_id = e.id
            LEFT JOIN entities_attributes ea ON eav.attribute_id = ea.id
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        // تطبيق نفس الفلاتر الموجودة في دالة all
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (is_numeric($filters[$col])) {
                    $sql .= " AND eav.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        if (isset($filters['value']) && !empty($filters['value'])) {
            $sql .= " AND eav.value LIKE :value";
            $params[":value"] = '%' . $filters['value'] . '%';
        }

        if (isset($filters['store_name']) && !empty($filters['store_name'])) {
            $sql .= " AND e.store_name LIKE :store_name";
            $params[":store_name"] = '%' . $filters['store_name'] . '%';
        }

        if (isset($filters['entity_status']) && in_array($filters['entity_status'], ['pending', 'approved', 'suspended', 'rejected'])) {
            $sql .= " AND e.status = :entity_status";
            $params[":entity_status"] = $filters['entity_status'];
        }

        if (isset($filters['attribute_type']) && in_array($filters['attribute_type'], ['text', 'number', 'select', 'boolean'])) {
            $sql .= " AND ea.attribute_type = :attribute_type";
            $params[":attribute_type"] = $filters['attribute_type'];
        }

        if (isset($filters['attribute_slug']) && !empty($filters['attribute_slug'])) {
            $sql .= " AND ea.slug LIKE :attribute_slug";
            $params[":attribute_slug"] = '%' . $filters['attribute_slug'] . '%';
        }

        if (isset($filters['attribute_name']) && !empty($filters['attribute_name'])) {
            $sql .= " AND eat.name LIKE :attribute_name";
            $params[":attribute_name"] = '%' . $filters['attribute_name'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT eav.*,
                   e.store_name,
                   e.status as entity_status,
                   ea.slug as attribute_slug,
                   ea.attribute_type,
                   eat.name as attribute_name,
                   eat.description as attribute_description
            FROM entities_attribute_values eav
            LEFT JOIN entities e ON eav.entity_id = e.id
            LEFT JOIN entities_attributes ea ON eav.attribute_id = ea.id
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            WHERE eav.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by entity and attribute
    // ================================
    public function findByEntityAndAttribute(int $entityId, int $attributeId, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT eav.*,
                   e.store_name,
                   e.status as entity_status,
                   ea.slug as attribute_slug,
                   ea.attribute_type,
                   eat.name as attribute_name,
                   eat.description as attribute_description
            FROM entities_attribute_values eav
            LEFT JOIN entities e ON eav.entity_id = e.id
            LEFT JOIN entities_attributes ea ON eav.attribute_id = ea.id
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            WHERE eav.entity_id = :entity_id AND eav.attribute_id = :attribute_id
            LIMIT 1
        ");
        $stmt->execute([':entity_id' => $entityId, ':attribute_id' => $attributeId, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get all values for an entity
    // ================================
    public function getEntityValues(int $entityId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT eav.*,
                   ea.slug as attribute_slug,
                   ea.attribute_type,
                   eat.name as attribute_name,
                   eat.description as attribute_description
            FROM entities_attribute_values eav
            LEFT JOIN entities_attributes ea ON eav.attribute_id = ea.id
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            WHERE eav.entity_id = :entity_id
            ORDER BY ea.sort_order, ea.id
        ");
        $stmt->execute([':entity_id' => $entityId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get all values for an attribute
    // ================================
    public function getAttributeValues(int $attributeId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT eav.*,
                   e.store_name,
                   e.status as entity_status,
                   ea.slug as attribute_slug,
                   ea.attribute_type,
                   eat.name as attribute_name,
                   eat.description as attribute_description
            FROM entities_attribute_values eav
            LEFT JOIN entities e ON eav.entity_id = e.id
            LEFT JOIN entities_attributes ea ON eav.attribute_id = ea.id
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            WHERE eav.attribute_id = :attribute_id
            ORDER BY eav.id
        ");
        $stmt->execute([':attribute_id' => $attributeId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================

    // الأعمدة المسموحة في جدول entities_attribute_values فقط
    private const ATTRIBUTE_VALUE_COLUMNS = [
        'entity_id', 'attribute_id', 'value'
    ];

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط من البيانات الواردة
        $params = [];
        foreach (self::ATTRIBUTE_VALUE_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                // تحويل القيم الفارغة إلى null للأعمدة الاختيارية
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            }
        }

        // التحقق من الحقول المطلوبة
        if (empty($params[':entity_id']) || empty($params[':attribute_id'])) {
            throw new InvalidArgumentException("entity_id and attribute_id are required");
        }

        // التحقق من وجود الكيان والخاصية
        $this->validateEntityAndAttribute((int)$params[':entity_id'], (int)$params[':attribute_id']);

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE entities_attribute_values SET
                    entity_id = :entity_id,
                    attribute_id = :attribute_id,
                    value = :value
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entities_attribute_values (entity_id, attribute_id, value)
            VALUES (:entity_id, :attribute_id, :value)
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Bulk save values for an entity
    // ================================
    public function saveEntityValues(int $entityId, array $values): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];
            
            foreach ($values as $valueData) {
                $valueData['entity_id'] = $entityId;
                
                // التحقق من وجود القيمة مسبقاً
                $existing = $this->findByEntityAndAttribute($entityId, (int)$valueData['attribute_id']);
                
                if ($existing) {
                    $valueData['id'] = $existing['id'];
                }
                
                $savedIds[] = $this->save($valueData);
            }
            
            $this->pdo->commit();
            return $savedIds;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entities_attribute_values WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Delete all values for an entity
    // ================================
    public function deleteEntityValues(int $entityId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entities_attribute_values WHERE entity_id = :entity_id"
        );
        return $stmt->execute([':entity_id' => $entityId]);
    }

    // ================================
    // Delete all values for an attribute
    // ================================
    public function deleteAttributeValues(int $attributeId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entities_attribute_values WHERE attribute_id = :attribute_id"
        );
        return $stmt->execute([':attribute_id' => $attributeId]);
    }

    // ================================
    // Get statistics
    // ================================
    public function getStatistics(): array
    {
        $stats = [];

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entities_attribute_values");
        $stats['total_values'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT entity_id) FROM entities_attribute_values");
        $stats['entities_with_values'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT attribute_id) FROM entities_attribute_values");
        $stats['attributes_with_values'] = (int)$stmt->fetchColumn();

        return $stats;
    }

    // ================================
    // Validate entity and attribute exist
    // ================================
    private function validateEntityAndAttribute(int $entityId, int $attributeId): void
    {
        // التحقق من وجود الكيان
        $entityStmt = $this->pdo->prepare("SELECT id FROM entities WHERE id = :entity_id LIMIT 1");
        $entityStmt->execute([':entity_id' => $entityId]);
        if (!$entityStmt->fetch()) {
            throw new RuntimeException("Entity not found");
        }

        // التحقق من وجود الخاصية
        $attributeStmt = $this->pdo->prepare("SELECT id FROM entities_attributes WHERE id = :attribute_id LIMIT 1");
        $attributeStmt->execute([':attribute_id' => $attributeId]);
        if (!$attributeStmt->fetch()) {
            throw new RuntimeException("Attribute not found");
        }
    }
}