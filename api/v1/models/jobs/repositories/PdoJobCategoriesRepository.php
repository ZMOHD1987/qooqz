<?php
declare(strict_types=1);

final class PdoJobCategoriesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['id', 'tenant_id', 'parent_id', 'slug', 'sort_order', 'is_active', 'created_at'];
    private const FILTERABLE_COLUMNS = ['tenant_id', 'parent_id', 'is_active'];
    private const CATEGORY_COLUMNS = ['parent_id', 'slug', 'sort_order', 'is_active'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function all(int $tenantId, ?int $limit = null, ?int $offset = null, array $filters = [], string $orderBy = 'sort_order', string $orderDir = 'ASC', string $lang = 'ar'): array
    {
        try {
            $sql = "
                SELECT jc.*,
                       COALESCE(jct.name, '') AS name,
                       COALESCE(jct.description, '') AS description,
                       l.name AS language_name,
                       l.direction AS language_direction,
                       (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id AND tenant_id = jc.tenant_id) AS children_count,
                       img.url AS image_url,
                       img.thumb_url AS image_thumb_url
                FROM job_categories jc
                LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang
                LEFT JOIN languages l ON jct.language_code = l.code
                LEFT JOIN images img ON jc.id = img.owner_id AND img.image_type_id = 11 AND img.tenant_id = jc.tenant_id
                WHERE jc.tenant_id = :tenant_id
            ";
            $params = [':tenant_id' => $tenantId, ':lang' => $lang];

            foreach (self::FILTERABLE_COLUMNS as $col) {
                if (isset($filters[$col]) && $filters[$col] !== '') {
                    if ($col === 'parent_id' && $filters[$col] === 'null') {
                        $sql .= " AND jc.{$col} IS NULL";
                    } else {
                        $sql .= " AND jc.{$col} = :{$col}";
                        $params[":{$col}"] = $filters[$col];
                    }
                }
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (jct.name LIKE :search OR jc.slug LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sort_order';
            $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY jc.{$orderBy} {$orderDir}";

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
        } catch (PDOException $e) {
            error_log("Error in all(): " . $e->getMessage());
            throw new RuntimeException('Failed to fetch categories', 0, $e);
        }
    }

    public function count(int $tenantId, array $filters = [], string $lang = 'ar'): int
    {
        try {
            $sql = "SELECT COUNT(DISTINCT jc.id) FROM job_categories jc";
            if (!empty($filters['search'])) {
                $sql .= " LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang";
            }
            $sql .= " WHERE jc.tenant_id = :tenant_id";
            $params = [':tenant_id' => $tenantId];
            if (!empty($filters['search'])) {
                $params[':lang'] = $lang;
                $sql .= " AND (jct.name LIKE :search OR jc.slug LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            foreach (self::FILTERABLE_COLUMNS as $col) {
                if (isset($filters[$col]) && $filters[$col] !== '') {
                    if ($col === 'parent_id' && $filters[$col] === 'null') {
                        $sql .= " AND jc.{$col} IS NULL";
                    } else {
                        $sql .= " AND jc.{$col} = :{$col}";
                        $params[":{$col}"] = $filters[$col];
                    }
                }
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error in count(): " . $e->getMessage());
            throw new RuntimeException('Failed to count categories', 0, $e);
        }
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT jc.*,
                       COALESCE(jct.name, '') AS name,
                       COALESCE(jct.description, '') AS description,
                       l.name AS language_name,
                       l.direction AS language_direction,
                       (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id AND tenant_id = jc.tenant_id) AS children_count,
                       img.url AS image_url,
                       img.thumb_url AS image_thumb_url
                FROM job_categories jc
                LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang
                LEFT JOIN languages l ON jct.language_code = l.code
                LEFT JOIN images img ON jc.id = img.owner_id AND img.image_type_id = 11 AND img.tenant_id = jc.tenant_id
                WHERE jc.tenant_id = :tenant_id AND jc.id = :id LIMIT 1
            ");
            $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => $lang]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error in find(): " . $e->getMessage());
            throw new RuntimeException('Failed to find category', 0, $e);
        }
    }

    public function findBySlug(int $tenantId, string $slug, string $lang = 'ar'): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT jc.*,
                       COALESCE(jct.name, '') AS name,
                       COALESCE(jct.description, '') AS description,
                       l.name AS language_name,
                       l.direction AS language_direction,
                       (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id AND tenant_id = jc.tenant_id) AS children_count,
                       img.url AS image_url,
                       img.thumb_url AS image_thumb_url
                FROM job_categories jc
                LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang
                LEFT JOIN languages l ON jct.language_code = l.code
                LEFT JOIN images img ON jc.id = img.owner_id AND img.image_type_id = 11 AND img.tenant_id = jc.tenant_id
                WHERE jc.tenant_id = :tenant_id AND jc.slug = :slug LIMIT 1
            ");
            $stmt->execute([':tenant_id' => $tenantId, ':slug' => $slug, ':lang' => $lang]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error in findBySlug(): " . $e->getMessage());
            throw new RuntimeException('Failed to find category by slug', 0, $e);
        }
    }

    public function getTree(int $tenantId, ?int $parentId = null, string $lang = 'ar'): array
    {
        try {
            $sql = "
                SELECT jc.*,
                       COALESCE(jct.name, '') AS name,
                       COALESCE(jct.description, '') AS description,
                       img.url AS image_url,
                       img.thumb_url AS image_thumb_url
                FROM job_categories jc
                LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang
                LEFT JOIN images img ON jc.id = img.owner_id AND img.image_type_id = 11 AND img.tenant_id = jc.tenant_id
                WHERE jc.tenant_id = :tenant_id
            ";
            
            if ($parentId === null) {
                $sql .= " AND jc.parent_id IS NULL";
                $params = [':tenant_id' => $tenantId, ':lang' => $lang];
            } else {
                $sql .= " AND jc.parent_id = :parent_id";
                $params = [':tenant_id' => $tenantId, ':parent_id' => $parentId, ':lang' => $lang];
            }
            
            $sql .= " ORDER BY jc.sort_order ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($categories as &$category) {
                $category['children'] = $this->getTree($tenantId, (int)$category['id'], $lang);
            }

            return $categories;
        } catch (PDOException $e) {
            error_log("Error in getTree(): " . $e->getMessage());
            throw new RuntimeException('Failed to get category tree', 0, $e);
        }
    }

    public function getChildren(int $tenantId, int $parentId, string $lang = 'ar'): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT jc.*,
                       COALESCE(jct.name, '') AS name,
                       COALESCE(jct.description, '') AS description,
                       (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id AND tenant_id = jc.tenant_id) AS children_count,
                       img.url AS image_url,
                       img.thumb_url AS image_thumb_url
                FROM job_categories jc
                LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang
                LEFT JOIN images img ON jc.id = img.owner_id AND img.image_type_id = 11 AND img.tenant_id = jc.tenant_id
                WHERE jc.tenant_id = :tenant_id AND jc.parent_id = :parent_id ORDER BY jc.sort_order ASC
            ");
            $stmt->execute([':tenant_id' => $tenantId, ':parent_id' => $parentId, ':lang' => $lang]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getChildren(): " . $e->getMessage());
            throw new RuntimeException('Failed to get children categories', 0, $e);
        }
    }

    public function getRootCategories(int $tenantId, string $lang = 'ar'): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT jc.*,
                       COALESCE(jct.name, '') AS name,
                       COALESCE(jct.description, '') AS description,
                       (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id AND tenant_id = jc.tenant_id) AS children_count,
                       img.url AS image_url,
                       img.thumb_url AS image_thumb_url
                FROM job_categories jc
                LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang
                LEFT JOIN images img ON jc.id = img.owner_id AND img.image_type_id = 11 AND img.tenant_id = jc.tenant_id
                WHERE jc.tenant_id = :tenant_id AND jc.parent_id IS NULL ORDER BY jc.sort_order ASC
            ");
            $stmt->execute([':tenant_id' => $tenantId, ':lang' => $lang]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getRootCategories(): " . $e->getMessage());
            throw new RuntimeException('Failed to get root categories', 0, $e);
        }
    }

    public function getTranslations(int $categoryId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT jct.*,
                       l.name AS language_name,
                       l.direction AS language_direction
                FROM job_category_translations jct
                JOIN languages l ON jct.language_code = l.code
                WHERE jct.category_id = :category_id ORDER BY jct.language_code
            ");
            $stmt->execute([':category_id' => $categoryId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getTranslations(): " . $e->getMessage());
            throw new RuntimeException('Failed to get translations', 0, $e);
        }
    }

    public function save(int $tenantId, array $data): int
    {
        try {
            $this->pdo->beginTransaction();

            $isUpdate = !empty($data['id']);
            $params = [];

            foreach (self::CATEGORY_COLUMNS as $col) {
                if (array_key_exists($col, $data)) {
                    $val = $data[$col];
                    $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
                } else {
                    $params[':' . $col] = null;
                }
            }

            if (empty($params[':slug']) || $params[':slug'] === null) {
                $name = $this->extractNameFromData($data);
                $params[':slug'] = $this->generateSlug($name);
            }

            $params[':sort_order'] = $params[':sort_order'] ?? 0;
            $params[':is_active'] = $params[':is_active'] ?? 1;

            if ($isUpdate) {
                $params[':tenant_id'] = $tenantId;
                $params[':id'] = (int)$data['id'];

                $stmt = $this->pdo->prepare("
                    UPDATE job_categories SET parent_id = :parent_id, slug = :slug, sort_order = :sort_order, is_active = :is_active
                    WHERE tenant_id = :tenant_id AND id = :id
                ");
                $stmt->execute($params);
                $categoryId = (int)$data['id'];
            } else {
                $params[':tenant_id'] = $tenantId;

                $stmt = $this->pdo->prepare("
                    INSERT INTO job_categories (tenant_id, parent_id, slug, sort_order, is_active)
                    VALUES (:tenant_id, :parent_id, :slug, :sort_order, :is_active)
                ");
                $stmt->execute($params);
                $categoryId = (int)$this->pdo->lastInsertId();
            }

            $this->pdo->commit();
            return $categoryId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error in save(): " . $e->getMessage());
            throw new RuntimeException('Failed to save category', 0, $e);
        }
    }

    public function delete(int $tenantId, int $id): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM job_categories WHERE parent_id = :id AND tenant_id = :tenant_id");
            $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('Cannot delete category with children');
            }

            $stmt = $this->pdo->prepare("DELETE FROM job_categories WHERE tenant_id = :tenant_id AND id = :id");
            $result = $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);

            $this->pdo->commit();
            return $result;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error in delete(): " . $e->getMessage());
            throw new RuntimeException('Failed to delete category', 0, $e);
        }
    }

    public function updateSortOrder(int $tenantId, int $id, int $sortOrder): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE job_categories SET sort_order = :sort_order WHERE tenant_id = :tenant_id AND id = :id");
            return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':sort_order' => $sortOrder]);
        } catch (PDOException $e) {
            error_log("Error in updateSortOrder(): " . $e->getMessage());
            throw new RuntimeException('Failed to update sort order', 0, $e);
        }
    }

    public function moveToParent(int $tenantId, int $id, ?int $newParentId): bool
    {
        try {
            if ($newParentId !== null && $this->isDescendantOf($id, $newParentId)) {
                throw new RuntimeException('Cannot move to descendant');
            }

            $stmt = $this->pdo->prepare("UPDATE job_categories SET parent_id = :parent_id WHERE tenant_id = :tenant_id AND id = :id");
            return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':parent_id' => $newParentId]);
        } catch (PDOException $e) {
            error_log("Error in moveToParent(): " . $e->getMessage());
            throw new RuntimeException('Failed to move category', 0, $e);
        }
    }

    public function slugExists(string $slug, int $tenantId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM job_categories WHERE slug = :slug AND tenant_id = :tenant_id");
            $stmt->execute([':slug' => $slug, ':tenant_id' => $tenantId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in slugExists: " . $e->getMessage());
            return false;
        }
    }

    public function slugExistsExcludingId(string $slug, int $tenantId, int $excludeId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM job_categories WHERE slug = :slug AND tenant_id = :tenant_id AND id != :exclude_id");
            $stmt->execute([':slug' => $slug, ':tenant_id' => $tenantId, ':exclude_id' => $excludeId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in slugExistsExcludingId: " . $e->getMessage());
            return false;
        }
    }

    public function languageExists(string $code): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM languages WHERE code = :code");
            $stmt->execute([':code' => $code]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in languageExists: " . $e->getMessage());
            return false;
        }
    }

    private function isDescendantOf(int $categoryId, int $potentialAncestorId): bool
    {
        if ($categoryId === $potentialAncestorId) return true;
        $stmt = $this->pdo->prepare("SELECT parent_id FROM job_categories WHERE id = :id");
        $stmt->execute([':id' => $categoryId]);
        $parentId = $stmt->fetchColumn();
        return $parentId && $this->isDescendantOf((int)$parentId, $potentialAncestorId);
    }

    private function extractNameFromData(array $data): string
    {
        if (!empty($data['name'])) return $data['name'];
        if (!empty($data['translations']) && is_array($data['translations'])) {
            $first = reset($data['translations']);
            return $first['name'] ?? '';
        }
        return '';
    }

    private function generateSlug(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9\p{Arabic}\-]+/u', '-', mb_strtolower(trim($name)));
        $slug = trim($slug, '-');
        return empty($slug) ? 'category-' . time() . '-' . mt_rand(100, 999) : $slug;
    }
}