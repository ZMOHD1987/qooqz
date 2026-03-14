<?php
declare(strict_types=1);

final class PdoBrandsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'tenant_id', 'entity_id', 'slug', 'is_active', 'is_featured', 'sort_order', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'tenant_id', 'entity_id', 'is_active', 'is_featured', 'slug'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * جلب قائمة العلامات التجارية مع الفلاتر والترتيب والصفحات
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC',
        string $lang = 'en'
    ): array {
        $sql = "
            SELECT b.id, b.tenant_id, b.entity_id, b.slug, b.website_url,
                   b.is_active, b.is_featured, b.sort_order, b.created_at, b.updated_at,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'tenant_id') continue;
                if ($col === 'slug') {
                    $sql .= " AND b.slug LIKE :slug";
                    $params[':slug'] = '%' . $filters['slug'] . '%';
                } elseif (in_array($col, ['is_active', 'is_featured'])) {
                    $sql .= " AND b.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                } else {
                    $sql .= " AND b.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sort_order';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY b.{$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        if ($offset !== null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM brands WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'tenant_id') continue;
                if ($col === 'slug') {
                    $sql .= " AND slug LIKE :slug";
                    $params[':slug'] = '%' . $filters['slug'] . '%';
                } elseif (in_array($col, ['is_active', 'is_featured'])) {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
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

    /**
     * العثور على علامة تجارية بواسطة slug مع الترجمات
     */
    public function find(int $tenantId, string $slug, string $lang = 'en', bool $allTranslations = false): ?array
    {
        if ($allTranslations) {
            $row = $this->findBySlugRaw($tenantId, $slug);
            if ($row) {
                $row['translations'] = $this->getTranslationsAsArray($row['id']);
            }
            return $row;
        }

        $stmt = $this->pdo->prepare("
            SELECT b.id, b.tenant_id, b.entity_id, b.slug, b.website_url,
                   b.is_active, b.is_featured, b.sort_order, b.created_at, b.updated_at,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.slug = :slug
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * العثور على علامة تجارية بواسطة ID مع الترجمات
     */
    public function findById(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): ?array
    {
        if ($allTranslations) {
            $row = $this->findByIdRaw($tenantId, $id);
            if ($row) {
                $row['translations'] = $this->getTranslationsAsArray($row['id']);
            }
            return $row;
        }

        $stmt = $this->pdo->prepare("
            SELECT b.id, b.tenant_id, b.entity_id, b.slug, b.website_url,
                   b.is_active, b.is_featured, b.sort_order, b.created_at, b.updated_at,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * جلب الصف الأساسي بدون ترجمات بواسطة slug
     */
    private function findBySlugRaw(int $tenantId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM brands
            WHERE tenant_id = :tenantId AND slug = :slug
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * جلب الصف الأساسي بدون ترجمات بواسطة ID
     */
    private function findByIdRaw(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM brands
            WHERE tenant_id = :tenantId AND id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * جلب جميع الترجمات كمصفوفة (لكل لغة عنصر مع language_code)
     */
    public function getTranslationsAsArray(int $brandId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name, description, meta_title, meta_description
            FROM brand_translations
            WHERE brand_id = :brand_id
        ");
        $stmt->execute([':brand_id' => $brandId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * حفظ (إدراج أو تحديث) علامة تجارية
     */
    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->findByIdRaw($tenantId, (int)$data['id']) : null;

        $fields = [
            'tenant_id'    => $tenantId,
            'entity_id'    => $data['entity_id']    ?? null,
            'slug'         => $data['slug']         ?? null,
            'website_url'  => $data['website_url']  ?? null,
            'is_active'    => (int)($data['is_active'] ?? 1),
            'is_featured'  => (int)($data['is_featured'] ?? 0),
            'sort_order'   => (int)($data['sort_order'] ?? 0),
        ];

        if ($isUpdate) {
            $id = (int)$data['id'];
            $sets = [];
            $params = [':id' => $id, ':tenantId' => $tenantId];
            foreach ($fields as $col => $val) {
                if (array_key_exists($col, $data) || $col === 'tenant_id') {
                    if ($col === 'tenant_id') continue;
                    $sets[] = "$col = :$col";
                    $params[":$col"] = $val;
                }
            }
            $sets[] = "updated_at = NOW()";

            $sql = "UPDATE brands SET " . implode(', ', $sets) . " WHERE tenant_id = :tenantId AND id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $cols = array_keys($fields);
            $placeholders = array_map(fn($c) => ":$c", $cols);
            $sql = "INSERT INTO brands (" . implode(', ', $cols) . ", created_at) VALUES (" . implode(', ', $placeholders) . ", NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($fields);
            $id = (int)$this->pdo->lastInsertId();
        }

        // حفظ الترجمات
        if (!empty($data['translations'])) {
            $this->saveTranslations($id, $data['translations']);
        }

        // حذف الترجمات المحددة (deleted_translations)
        if (!empty($data['deleted_translations']) && is_array($data['deleted_translations'])) {
            $this->deleteTranslations($id, $data['deleted_translations']);
        }

        // تسجيل العملية (اختياري)
        if ($userId) {
            $this->logAction($tenantId, $userId, $isUpdate ? 'update' : 'create', $id, $oldData, $data);
        }

        return $id;
    }

    /**
     * حذف الترجمات المحددة
     */
    private function deleteTranslations(int $brandId, array $deletedTranslations): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM brand_translations
            WHERE brand_id = :brand_id AND language_code = :lang
        ");
        foreach ($deletedTranslations as $del) {
            if (isset($del['language_code'])) {
                $stmt->execute([':brand_id' => $brandId, ':lang' => $del['language_code']]);
            }
        }
    }

    /**
     * حذف بواسطة slug
     */
    public function delete(int $tenantId, string $slug, ?int $userId = null): bool
    {
        $oldData = $this->findBySlugRaw($tenantId, $slug);
        if (!$oldData) return false;

        $this->pdo->beginTransaction();
        try {
            // حذف الترجمات
            $this->pdo->prepare("DELETE FROM brand_translations WHERE brand_id = :brand_id")
                ->execute([':brand_id' => $oldData['id']]);

            $stmt = $this->pdo->prepare("DELETE FROM brands WHERE tenant_id = :tenantId AND slug = :slug");
            $result = $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);

            if ($userId) {
                $this->logAction($tenantId, $userId, 'delete', $oldData['id'], $oldData, null);
            }
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * حذف بواسطة ID
     */
    public function deleteById(int $tenantId, int $id, ?int $userId = null): bool
    {
        $oldData = $this->findByIdRaw($tenantId, $id);
        if (!$oldData) return false;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM brand_translations WHERE brand_id = :brand_id")
                ->execute([':brand_id' => $id]);

            $stmt = $this->pdo->prepare("DELETE FROM brands WHERE tenant_id = :tenantId AND id = :id");
            $result = $stmt->execute([':tenantId' => $tenantId, ':id' => $id]);

            if ($userId) {
                $this->logAction($tenantId, $userId, 'delete', $id, $oldData, null);
            }
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * العلامات النشطة
     */
    public function getActiveBrands(int $tenantId, string $lang = 'en'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.slug, b.website_url, b.sort_order,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.meta_title, '') AS meta_title,
                   COALESCE(bt.meta_description, '') AS meta_description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.is_active = 1
            ORDER BY b.sort_order ASC, b.slug ASC
        ");
        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * العلامات المميزة (featured)
     */
    public function getFeaturedBrands(int $tenantId, string $lang = 'en'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.slug, b.website_url, b.sort_order,
                   COALESCE(bt.name, '') AS name,
                   COALESCE(bt.description, '') AS description
            FROM brands b
            LEFT JOIN brand_translations bt 
                ON b.id = bt.brand_id AND bt.language_code = :lang
            WHERE b.tenant_id = :tenantId AND b.is_featured = 1 AND b.is_active = 1
            ORDER BY b.sort_order ASC, b.slug ASC
        ");
        $stmt->execute([':tenantId' => $tenantId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * حفظ الترجمات (إدراج أو تحديث)
     */
    public function saveTranslations(int $brandId, array $translations): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO brand_translations (brand_id, language_code, name, description, meta_title, meta_description)
            VALUES (:brand_id, :lang, :name, :description, :meta_title, :meta_description)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description)
        ");

        foreach ($translations as $lang => $data) {
            $stmt->execute([
                ':brand_id'          => $brandId,
                ':lang'              => $lang,
                ':name'              => $data['name']              ?? null,
                ':description'       => $data['description']       ?? null,
                ':meta_title'        => $data['meta_title']        ?? null,
                ':meta_description'  => $data['meta_description']  ?? null
            ]);
        }
    }

    /**
     * تسجيل العملية في سجل الأحداث
     */
    private function logAction(int $tenantId, int $userId, string $action, int $entityId, ?array $oldData, ?array $newData): void
    {
        $changes = null;
        if ($action === 'update' && $oldData && $newData) {
            $changes = json_encode(['old' => $oldData, 'new' => $newData]);
        } elseif ($action === 'delete' && $oldData) {
            $changes = json_encode(['deleted' => $oldData]);
        } elseif ($action === 'create' && $newData) {
            $changes = json_encode(['created' => $newData]);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_logs (tenant_id, user_id, entity_type, entity_id, action, changes, ip_address, created_at)
            VALUES (:tenantId, :userId, 'brand', :entityId, :action, :changes, :ip, NOW())
        ");
        $stmt->execute([
            ':tenantId' => $tenantId,
            ':userId'   => $userId,
            ':entityId' => $entityId,
            ':action'   => $action,
            ':changes'  => $changes,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}