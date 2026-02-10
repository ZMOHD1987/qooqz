<?php
declare(strict_types=1);

final class PdoEntitiesAttributesRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'slug', 'attribute_type', 'is_required', 
        'sort_order', 'created_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'slug', 'attribute_type', 'is_required'
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
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT ea.*,
                   eat.name,
                   eat.description,
                   l.name as language_name,
                   l.direction as language_direction
            FROM entities_attributes ea
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            LEFT JOIN languages l ON eat.language_code = l.code
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['slug'])) {
                    $sql .= " AND ea.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } elseif (in_array($col, ['attribute_type'])) {
                    $sql .= " AND ea.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                } elseif (in_array($col, ['is_required'])) {
                    $sql .= " AND ea.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        // فلتر إضافي للبحث في اسم الخاصية (الترجمة)
        if (isset($filters['name']) && !empty($filters['name'])) {
            $sql .= " AND eat.name LIKE :name";
            $params[":name"] = '%' . $filters['name'] . '%';
        }

        // فلتر إضافي للبحث في وصف الخاصية (الترجمة)
        if (isset($filters['description']) && !empty($filters['description'])) {
            $sql .= " AND eat.description LIKE :description";
            $params[":description"] = '%' . $filters['description'] . '%';
        }

        // فلتر حسب اللغة
        if (isset($filters['language_code']) && !empty($filters['language_code'])) {
            $sql .= " AND eat.language_code = :language_code";
            $params[":language_code"] = $filters['language_code'];
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sort_order';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ea.{$orderBy} {$orderDir}";

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
            FROM entities_attributes ea
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            WHERE 1=1
        ";
        $params = [':lang' => $lang];

        // تطبيق نفس الفلاتر الموجودة في دالة all
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['slug'])) {
                    $sql .= " AND ea.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } elseif (in_array($col, ['attribute_type'])) {
                    $sql .= " AND ea.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                } elseif (in_array($col, ['is_required'])) {
                    $sql .= " AND ea.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        if (isset($filters['name']) && !empty($filters['name'])) {
            $sql .= " AND eat.name LIKE :name";
            $params[":name"] = '%' . $filters['name'] . '%';
        }

        if (isset($filters['description']) && !empty($filters['description'])) {
            $sql .= " AND eat.description LIKE :description";
            $params[":description"] = '%' . $filters['description'] . '%';
        }

        if (isset($filters['language_code']) && !empty($filters['language_code'])) {
            $sql .= " AND eat.language_code = :language_code";
            $params[":language_code"] = $filters['language_code'];
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
            SELECT ea.*,
                   eat.name,
                   eat.description,
                   l.name as language_name,
                   l.direction as language_direction
            FROM entities_attributes ea
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            LEFT JOIN languages l ON eat.language_code = l.code
            WHERE ea.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by slug
    // ================================
    public function findBySlug(string $slug, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ea.*,
                   eat.name,
                   eat.description,
                   l.name as language_name,
                   l.direction as language_direction
            FROM entities_attributes ea
            LEFT JOIN entities_attribute_translations eat 
                ON ea.id = eat.attribute_id AND eat.language_code = :lang
            LEFT JOIN languages l ON eat.language_code = l.code
            WHERE ea.slug = :slug
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get all translations for an attribute
    // ================================
    public function getTranslations(int $attributeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT eat.*, l.name as language_name, l.direction as language_direction
            FROM entities_attribute_translations eat
            LEFT JOIN languages l ON eat.language_code = l.code
            WHERE eat.attribute_id = :attribute_id
            ORDER BY l.name
        ");
        $stmt->execute([':attribute_id' => $attributeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================

    // الأعمدة المسموحة في جدول entities_attributes فقط
    private const ATTRIBUTE_COLUMNS = [
        'slug', 'attribute_type', 'is_required', 'sort_order'
    ];

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط من البيانات الواردة
        $params = [];
        foreach (self::ATTRIBUTE_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                // تحويل القيم الفارغة إلى null للأعمدة الاختيارية
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            }
        }

        // توليد slug تلقائياً إذا كان فارغاً
        if (empty($params[':slug']) || $params[':slug'] === null) {
            $name = $data['name'] ?? 'attribute';
            $params[':slug'] = preg_replace('/[^a-z0-9\p{Arabic}\-]+/u', '-', mb_strtolower(trim($name)));
            $params[':slug'] = trim($params[':slug'], '-');
            if (empty($params[':slug'])) {
                $params[':slug'] = 'attribute-' . time();
            }
            // إضافة رقم عشوائي لتجنب التكرار
            $params[':slug'] .= '-' . mt_rand(1000, 9999);
        }

        // تعيين قيم افتراضية
        if (!isset($params[':attribute_type'])) {
            $params[':attribute_type'] = 'text';
        }
        if (!isset($params[':is_required'])) {
            $params[':is_required'] = 0;
        }
        if (!isset($params[':sort_order'])) {
            $params[':sort_order'] = 0;
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE entities_attributes SET
                    slug = :slug,
                    attribute_type = :attribute_type,
                    is_required = :is_required,
                    sort_order = :sort_order
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entities_attributes (slug, attribute_type, is_required, sort_order)
            VALUES (:slug, :attribute_type, :is_required, :sort_order)
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Save translation
    // ================================
    public function saveTranslation(int $attributeId, string $languageCode, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO entities_attribute_translations (attribute_id, language_code, name, description)
            VALUES (:attribute_id, :language_code, :name, :description)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                description = VALUES(description)
        ");
        
        return $stmt->execute([
            ':attribute_id' => $attributeId,
            ':language_code' => $languageCode,
            ':name' => $data['name'] ?? '',
            ':description' => $data['description'] ?? null
        ]);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            // حذف الترجمات أولاً
            $stmt = $this->pdo->prepare(
                "DELETE FROM entities_attribute_translations WHERE attribute_id = :attribute_id"
            );
            $stmt->execute([':attribute_id' => $id]);

            // حذف الخاصية الرئيسية
            $stmt = $this->pdo->prepare(
                "DELETE FROM entities_attributes WHERE id = :id"
            );
            $result = $stmt->execute([':id' => $id]);

            $this->pdo->commit();
            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ================================
    // Get available languages
    // ================================
    public function getLanguages(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM languages ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}