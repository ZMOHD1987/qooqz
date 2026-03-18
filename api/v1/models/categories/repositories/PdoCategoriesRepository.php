<?php
declare(strict_types=1);

final class PdoCategoriesRepository implements CategoriesRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ============================================================
    // دالة PUBLIC لتحصل على كائن PDO
    // ============================================================
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /* ============================================================
     * GET ALL CATEGORIES
     * ============================================================ */
    public function all(
        ?int $tenantId,
        ?int $parentId = null,
        bool $featuredOnly = false,
        string $lang = 'en',
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 50,
        int $offset = 0,
        bool $skipTcFilter = false
    ): array {
        // Super-admin bypass: when tenantId is null, skip tenant filter and tc-join
        $isSuperAdmin = ($tenantId === null);
        $hasAssignments = (!$isSuperAdmin && !$skipTcFilter)
            ? $this->hasTenantCategoryAssignments($tenantId)
            : false;

        $sql = "
            SELECT
                c.*,
                COALESCE(ct.name, c.name) AS name,
                COALESCE(ct.description, c.description) AS description,
                COALESCE(ct.slug, c.slug) AS slug,
                COALESCE(ct.meta_title, '') AS meta_title,
                COALESCE(ct.meta_description, '') AS meta_description,
                COALESCE(ct.meta_keywords, '') AS meta_keywords,
                i.id AS image_id,
                i.url AS image_url,
                i.thumb_url AS image_thumb_url,
                p.name AS parent_name
            FROM categories c
            LEFT JOIN category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN categories p
                ON c.parent_id = p.id
            LEFT JOIN images i
                ON i.owner_id = c.id
               AND i.image_type_id = (
                   SELECT id FROM image_types WHERE name = 'category' LIMIT 1
               )
               AND i.is_main = 1
        ";

        if ($hasAssignments) {
            $sql .= "
            INNER JOIN tenant_categories tc_assign
                ON c.id = tc_assign.category_id
               AND tc_assign.tenant_id = :tenantId_tc
               AND tc_assign.is_active = 1
            ";
        }

        $params = [':lang' => $lang];

        // Tenant filter:
        // - Super admin (tenantId null): no filter.
        // - hasAssignments: the INNER JOIN on tenant_categories already restricts to this
        //   tenant's assigned categories, so we must NOT also filter by c.tenant_id because
        //   shared/global categories (c.tenant_id != tenantId) would be wrongly excluded.
        // - Regular tenant without assignments: filter by c.tenant_id.
        if ($isSuperAdmin || $hasAssignments) {
            $sql .= " WHERE 1=1";
        } else {
            $sql .= " WHERE c.tenant_id = :tenantId";
            $params[':tenantId'] = $tenantId;
        }

        if ($hasAssignments) {
            $params[':tenantId_tc'] = $tenantId;
        }

        if ($parentId !== null) {
            if ($parentId === 0) {
                $sql .= " AND c.parent_id IS NULL";
            } else {
                $sql .= " AND c.parent_id = :parentId";
                $params[':parentId'] = $parentId;
            }
        }

        if ($featuredOnly) {
            $sql .= " AND c.is_featured = 1";
        }

        if ($isActive !== null) {
            $sql .= " AND c.is_active = :is_active";
            $params[':is_active'] = $isActive ? 1 : 0;
        }

        if ($search) {
            $sql .= " AND (c.name LIKE :search1 OR c.slug LIKE :search2 OR ct.name LIKE :search3 OR ct.slug LIKE :search4)";
            $searchVal = "%{$search}%";
            $params[':search1'] = $searchVal;
            $params[':search2'] = $searchVal;
            $params[':search3'] = $searchVal;
            $params[':search4'] = $searchVal;
        }

        $sql .= " ORDER BY c.sort_order ASC, c.id ASC";
        
        if ($limit > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        
        // ربط القيم مع تحديد النوع
        foreach ($params as $key => $value) {
            if (in_array($key, [':limit', ':offset'])) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ============================================================
     * GET CATEGORY BY ID
     * ============================================================ */
    public function findById(?int $tenantId, int $id): ?array
    {
        if ($tenantId === null) {
            // Super-admin bypass: find by ID alone, no tenant scope
            $stmt = $this->pdo->prepare("
                SELECT * FROM categories WHERE id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM categories
                WHERE tenant_id = :tenantId AND id = :id
                LIMIT 1
            ");
            $stmt->execute([
                ':tenantId' => $tenantId,
                ':id'       => $id
            ]);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByIdWithTranslations(?int $tenantId, int $id): ?array
    {
        $row = $this->findById($tenantId, $id);
        if (!$row) return null;

        $row['translations'] = $this->getTranslations($id);
        $row['image'] = $this->getMainImage($tenantId, $id);

        return $row;
    }

    /* ============================================================
     * CREATE / UPDATE CATEGORY
     * ============================================================ */
    public function save(?int $tenantId, array $data, ?int $userId = null): int
    {
        // When super admin calls with null tenantId, derive tenant from data
        if ($tenantId === null) {
            if (empty($data['tenant_id'])) {
                throw new \InvalidArgumentException('tenant_id is required in data when super admin saves a category');
            }
            $tenantId = (int)$data['tenant_id'];
        }

        $isUpdate = !empty($data['id']);
        $oldData = $isUpdate ? $this->findByIdWithTranslations($tenantId, (int)$data['id']) : null;

        $this->pdo->beginTransaction();
        try {
            if ($isUpdate) {
                $stmt = $this->pdo->prepare("
                    UPDATE categories SET
                        parent_id   = :parent_id,
                        slug        = :slug,
                        name        = :name,
                        description = :description,
                        sort_order  = :sort_order,
                        is_active   = :is_active,
                        is_featured = :is_featured,
                        updated_at  = NOW()
                    WHERE tenant_id = :tenantId AND id = :id
                ");
                $stmt->execute([
                    ':parent_id'   => $data['parent_id'] ?? null,
                    ':slug'        => $data['slug'],
                    ':name'        => $data['name'],
                    ':description' => $data['description'] ?? null,
                    ':sort_order'  => (int)($data['sort_order'] ?? 0),
                    ':is_active'   => (int)($data['is_active'] ?? 1),
                    ':is_featured' => (int)($data['is_featured'] ?? 0),
                    ':tenantId'    => $tenantId,
                    ':id'          => (int)$data['id']
                ]);
                $categoryId = (int)$data['id'];
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO categories
                    (tenant_id, parent_id, slug, name, description,
                     sort_order, is_active, is_featured, created_at)
                    VALUES
                    (:tenantId, :parent_id, :slug, :name, :description,
                     :sort_order, :is_active, :is_featured, NOW())
                ");
                $stmt->execute([
                    ':tenantId'    => $tenantId,
                    ':parent_id'   => $data['parent_id'] ?? null,
                    ':slug'        => $data['slug'],
                    ':name'        => $data['name'],
                    ':description' => $data['description'] ?? null,
                    ':sort_order'  => (int)($data['sort_order'] ?? 0),
                    ':is_active'   => (int)($data['is_active'] ?? 1),
                    ':is_featured' => (int)($data['is_featured'] ?? 0)
                ]);
                $categoryId = (int)$this->pdo->lastInsertId();
            }

            /* -------- IMAGE -------- */
            if (!empty($data['image_id'])) {
                $this->pdo->prepare("
                    UPDATE images
                    SET owner_id = :owner_id, is_main = 1
                    WHERE id = :image_id AND tenant_id = :tenantId
                ")->execute([
                    ':owner_id' => $categoryId,
                    ':image_id' => (int)$data['image_id'],
                    ':tenantId' => $tenantId
                ]);
            }

            /* -------- TRANSLATIONS -------- */
            if (isset($data['translations']) && is_array($data['translations'])) {
                $this->saveTranslations($categoryId, $data['translations']);
            }

            /* -------- DELETED TRANSLATIONS -------- */
            if (!empty($data['deleted_translations']) && is_array($data['deleted_translations'])) {
                foreach ($data['deleted_translations'] as $translation) {
                    if (isset($translation['language_code'])) {
                        $this->deleteTranslation($categoryId, $translation['language_code']);
                    }
                }
            }

            /* -------- LOG -------- */
            if ($userId) {
                $this->logAction(
                    $tenantId,
                    $userId,
                    $isUpdate ? 'update' : 'create',
                    $categoryId,
                    $oldData,
                    $data
                );
            }

            $this->pdo->commit();
            return $categoryId;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* ============================================================
     * DELETE CATEGORY
     * ============================================================ */
    public function delete(?int $tenantId, int $categoryId, ?int $userId = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            $oldData = $this->findByIdWithTranslations($tenantId, $categoryId);
            if (!$oldData) {
                $this->pdo->rollBack();
                return false;
            }

            // حذف الترجمات
            $this->pdo->prepare("
                DELETE FROM category_translations
                WHERE category_id = :categoryId
            ")->execute([':categoryId' => $categoryId]);

            // حذف الصور
            $this->pdo->prepare("
                DELETE FROM images
                WHERE owner_id = :categoryId
                  AND image_type_id = (
                      SELECT id FROM image_types WHERE name = 'category' LIMIT 1
                  )
            ")->execute([':categoryId' => $categoryId]);

            // حذف الفئة — super admin (tenantId null) can delete across all tenants
            if ($tenantId === null) {
                $this->pdo->prepare("
                    DELETE FROM categories WHERE id = :categoryId
                ")->execute([':categoryId' => $categoryId]);
            } else {
                $this->pdo->prepare("
                    DELETE FROM categories
                    WHERE tenant_id = :tenantId AND id = :categoryId
                ")->execute([
                    ':tenantId'   => $tenantId,
                    ':categoryId' => $categoryId
                ]);
            }

            // استخدم tenant_id من الكيان القديم للتسجيل إذا كان null
            $logTenantId = $tenantId ?? (int)($oldData['tenant_id'] ?? 0);

            // تسجيل اللوج
            if ($userId) {
                $this->logAction($logTenantId, $userId, 'delete', $categoryId, $oldData, null);
            }

            $this->pdo->commit();
            return true;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* ============================================================
     * DELETE SINGLE TRANSLATION
     * ============================================================ */
    public function deleteTranslation(int $categoryId, string $lang): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM category_translations
            WHERE category_id = :categoryId AND language_code = :lang
        ");
        return $stmt->execute([
            ':categoryId' => $categoryId,
            ':lang'       => $lang
        ]);
    }

    /* ============================================================
     * TRANSLATIONS
     * ============================================================ */
    public function saveTranslations(int $categoryId, array $translations): void
    {
        // تنظيف الترجمات الفارغة
        $translations = array_filter($translations, function($trans) {
            return isset($trans['language_code']) && !empty($trans['language_code']);
        });

        if (empty($translations)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO category_translations
            (category_id, language_code, name, description, slug,
             meta_title, meta_description, meta_keywords)
            VALUES
            (:category_id, :lang, :name, :description, :slug,
             :meta_title, :meta_description, :meta_keywords)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                slug = VALUES(slug),
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description),
                meta_keywords = VALUES(meta_keywords)
        ");

        foreach ($translations as $trans) {
            $lang = $trans['language_code'] ?? null;
            if (!$lang) continue;

            $stmt->execute([
                ':category_id'      => $categoryId,
                ':lang'             => $lang,
                ':name'             => $trans['name'] ?? null,
                ':description'      => $trans['description'] ?? null,
                ':slug'             => $trans['slug'] ?? null,
                ':meta_title'       => $trans['meta_title'] ?? null,
                ':meta_description' => $trans['meta_description'] ?? null,
                ':meta_keywords'    => $trans['meta_keywords'] ?? null
            ]);
        }
    }

    public function getTranslations(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT language_code, name, description, slug,
                   meta_title, meta_description, meta_keywords
            FROM category_translations
            WHERE category_id = :id
        ");
        $stmt->execute([':id' => $categoryId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['language_code']] = [
                'name'             => $r['name'],
                'description'      => $r['description'],
                'slug'             => $r['slug'],
                'meta_title'       => $r['meta_title'],
                'meta_description' => $r['meta_description'],
                'meta_keywords'    => $r['meta_keywords']
            ];
        }
        return $out;
    }

    /* ============================================================
     * IMAGES - public للاستخدام من CategoriesService
     * ============================================================ */
    public function getMainImage(?int $tenantId, int $categoryId): ?array
    {
        if ($tenantId === null) {
            // Super-admin: find image without tenant scope
            $stmt = $this->pdo->prepare("
                SELECT id, url, thumb_url
                FROM images
                WHERE owner_id = :owner_id
                  AND is_main = 1
                  AND image_type_id = (
                      SELECT id FROM image_types WHERE name = 'category' LIMIT 1
                  )
                LIMIT 1
            ");
            $stmt->execute([':owner_id' => $categoryId]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id, url, thumb_url
                FROM images
                WHERE tenant_id = :tenantId
                  AND owner_id = :owner_id
                  AND is_main = 1
                  AND image_type_id = (
                      SELECT id FROM image_types WHERE name = 'category' LIMIT 1
                  )
                LIMIT 1
            ");
            $stmt->execute([
                ':tenantId' => $tenantId,
                ':owner_id' => $categoryId
            ]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * LOGGING - متوافق مع جدول audit_logs
     * ============================================================ */
    private function logAction(
        int $tenantId,
        int $userId,
        string $action,
        int $entityId,
        ?array $oldData,
        ?array $newData
    ): void {
        // Strip sensitive fields before persisting
        $sensitiveFields = ['password', 'token', 'api_key', 'secret_key', 'refresh_token', 'access_token', 'session_id'];

        if ($oldData) {
            foreach ($sensitiveFields as $field) {
                unset($oldData[$field]);
            }
        }

        if ($newData) {
            foreach ($sensitiveFields as $field) {
                unset($newData[$field]);
            }
        }

        // Delegate to AuditLogsService (full diff/metadata/trace support) when available;
        // fall back to a direct INSERT for environments where the service is not loaded.
        if (class_exists('AuditLogsService')) {
            AuditLogsService::log(
                'category.' . $action,  // e.g. "category.create", "category.update"
                'category',
                $entityId,
                null,           // payload: superseded by old_values / new_values
                $tenantId,
                $userId,
                $oldData,       // old_values — diff is auto-computed by repository
                $newData        // new_values
            );
            return;
        }

        // Fallback: direct insert using the full schema (all new columns)
        try {
            $diff = null;
            if ($oldData !== null && $newData !== null) {
                $diff = PdoAuditLogsRepository::computeDiff($oldData, $newData);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (tenant_id, entity_type, entity_id, user_id, action,
                     ip_address, user_agent, old_values, new_values, diff,
                     http_method, http_url, session_id)
                VALUES
                    (:tenantId, :entity_type, :entity_id, :userId, :action,
                     :ip, :user_agent, :old_values, :new_values, :diff,
                     :http_method, :http_url, :session_id)
            ");

            $stmt->execute([
                ':tenantId'    => $tenantId,
                ':entity_type' => 'category',
                ':entity_id'   => $entityId,
                ':userId'      => $userId,
                ':action'      => 'category.' . $action,
                ':ip'          => $_SERVER['REMOTE_ADDR']     ?? null,
                ':user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':old_values'  => $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                ':new_values'  => $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                ':diff'        => $diff    !== null ? json_encode($diff,    JSON_UNESCAPED_UNICODE) : null,
                ':http_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                ':http_url'    => $_SERVER['REQUEST_URI']    ?? null,
                ':session_id'  => session_id() ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('AuditLog (categories fallback) failed: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * ADDITIONAL METHODS
     * ============================================================ */
    
    public function getActiveCategories(?int $tenantId, string $lang = 'en'): array
    {
        $sql = "
            SELECT
                c.*,
                COALESCE(ct.name, c.name) AS name,
                COALESCE(ct.description, c.description) AS description,
                COALESCE(ct.slug, c.slug) AS slug,
                i.url AS image_url
            FROM categories c
            LEFT JOIN category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN images i
                ON i.owner_id = c.id
               AND i.image_type_id = (
                   SELECT id FROM image_types WHERE name = 'category' LIMIT 1
               )
               AND i.is_main = 1
            WHERE c.is_active = 1
        ";

        $params = [':lang' => $lang];

        if ($tenantId !== null) {
            $sql .= " AND c.tenant_id = :tenantId";
            $params[':tenantId'] = $tenantId;
        }

        $sql .= " ORDER BY c.sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeaturedCategories(?int $tenantId, string $lang = 'en'): array
    {
        $sql = "
            SELECT
                c.*,
                COALESCE(ct.name, c.name) AS name,
                COALESCE(ct.description, c.description) AS description,
                COALESCE(ct.slug, c.slug) AS slug,
                i.url AS image_url
            FROM categories c
            LEFT JOIN category_translations ct
                ON c.id = ct.category_id AND ct.language_code = :lang
            LEFT JOIN images i
                ON i.owner_id = c.id
               AND i.image_type_id = (
                   SELECT id FROM image_types WHERE name = 'category' LIMIT 1
               )
               AND i.is_main = 1
            WHERE c.is_active = 1
              AND c.is_featured = 1
        ";

        $params = [':lang' => $lang];

        if ($tenantId !== null) {
            $sql .= " AND c.tenant_id = :tenantId";
            $params[':tenantId'] = $tenantId;
        }

        $sql .= " ORDER BY c.sort_order ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findIdBySlug(?int $tenantId, string $slug): ?int
    {
        if ($tenantId === null) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM categories WHERE slug = :slug LIMIT 1
            ");
            $stmt->execute([':slug' => $slug]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id FROM categories
                WHERE tenant_id = :tenantId AND slug = :slug
                LIMIT 1
            ");
            $stmt->execute([':tenantId' => $tenantId, ':slug' => $slug]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['id'] : null;
    }

    public function countAll(?int $tenantId, array $filters = [], bool $skipTcFilter = false): int
    {
        // Super-admin bypass: when tenantId is null, skip tenant filter and tc-join
        $isSuperAdmin = ($tenantId === null);
        $hasAssignments = (!$isSuperAdmin && !$skipTcFilter)
            ? $this->hasTenantCategoryAssignments($tenantId)
            : false;

        if ($hasAssignments) {
            // The INNER JOIN already restricts to this tenant's assigned categories;
            // do NOT also add WHERE c.tenant_id = :tenantId (would drop shared categories).
            $sql = "SELECT COUNT(*) as total
                    FROM categories c
                    INNER JOIN tenant_categories tc_assign
                        ON c.id = tc_assign.category_id
                       AND tc_assign.tenant_id = :tenantId_tc
                       AND tc_assign.is_active = 1
                    WHERE 1=1";
            $params = [':tenantId_tc' => $tenantId];
        } elseif (!$isSuperAdmin) {
            $sql = "SELECT COUNT(*) as total FROM categories c WHERE c.tenant_id = :tenantId";
            $params = [':tenantId' => $tenantId];
        } else {
            // Super admin — no tenant filter at all
            $sql = "SELECT COUNT(*) as total FROM categories c WHERE 1=1";
            $params = [];
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 0) {
                $sql .= " AND c.parent_id IS NULL";
            } else {
                $sql .= " AND c.parent_id = :parent_id";
                $params[':parent_id'] = $filters['parent_id'];
            }
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= " AND c.is_active = :is_active";
            $params[':is_active'] = (int)$filters['is_active'];
        }

        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $sql .= " AND c.is_featured = :is_featured";
            $params[':is_featured'] = (int)$filters['is_featured'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE :search1 OR c.slug LIKE :search2)";
            $searchVal = "%{$filters['search']}%";
            $params[':search1'] = $searchVal;
            $params[':search2'] = $searchVal;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    public function slugExists(?int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        if ($tenantId === null) {
            // Super-admin: check globally across all tenants
            $sql = "SELECT COUNT(*) as count FROM categories WHERE slug = :slug";
            $params = [':slug' => $slug];
        } else {
            $sql = "SELECT COUNT(*) as count FROM categories WHERE tenant_id = :tenantId AND slug = :slug";
            $params = [':tenantId' => $tenantId, ':slug' => $slug];
        }

        if ($excludeId) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result['count'] ?? 0) > 0;
    }

    public function hasChildren(int $categoryId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM categories WHERE parent_id = :categoryId
        ");
        $stmt->execute([':categoryId' => $categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result['count'] ?? 0) > 0;
    }

    private function hasTenantCategoryAssignments(?int $tenantId): bool
    {
        if ($tenantId === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as cnt FROM tenant_categories WHERE tenant_id = :tenantId LIMIT 1"
        );
        $stmt->execute([':tenantId' => $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($result['cnt'] ?? 0)) > 0;
    }
}