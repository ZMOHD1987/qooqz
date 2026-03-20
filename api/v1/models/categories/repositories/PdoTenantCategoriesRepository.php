<?php
declare(strict_types=1);

// api/v1/models/categories/repositories/PdoTenantCategoriesRepository.php

final class PdoTenantCategoriesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(?int $tenantId = null, ?int $categoryId = null, ?int $isActive = null, int $offset = 0, int $limit = null, string $lang = 'ar'): array
    {
        $sql = "SELECT tc.*, t.name AS tenant_name,
                       COALESCE(
                           (SELECT name FROM category_translations WHERE category_id = c.id AND language_code = :lang LIMIT 1),
                           (SELECT name FROM category_translations WHERE category_id = c.id ORDER BY language_code LIMIT 1),
                           c.name
                       ) AS category_name
                FROM tenant_categories tc
                LEFT JOIN tenants t ON tc.tenant_id = t.id
                LEFT JOIN categories c ON tc.category_id = c.id
                WHERE 1=1";

        $params = [':lang' => $lang];

        if ($tenantId !== null) {
            $sql .= " AND tc.tenant_id = :tenantId";
            $params[':tenantId'] = $tenantId;
        }

        if ($categoryId !== null) {
            $sql .= " AND tc.category_id = :categoryId";
            $params[':categoryId'] = $categoryId;
        }

        if ($isActive !== null) {
            $sql .= " AND tc.is_active = :isActive";
            $params[':isActive'] = $isActive;
        }

        $sql .= " ORDER BY tc.sort_order ASC, tc.created_at DESC";

        // Apply limit if provided
        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT tc.*, t.name AS tenant_name,
                   COALESCE(
                       (SELECT name FROM category_translations WHERE category_id = c.id AND language_code = :lang LIMIT 1),
                       (SELECT name FROM category_translations WHERE category_id = c.id ORDER BY language_code LIMIT 1),
                       c.name
                   ) AS category_name
            FROM tenant_categories tc
            LEFT JOIN tenants t ON tc.tenant_id = t.id
            LEFT JOIN categories c ON tc.category_id = c.id
            WHERE tc.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            // For partial updates, only update provided fields
            $updateFields = [];
            $params = [':id' => $data['id']];

            if (isset($data['tenant_id'])) {
                $updateFields[] = "tenant_id = :tenant_id";
                $params[':tenant_id'] = $data['tenant_id'];
            }
            if (isset($data['category_id'])) {
                $updateFields[] = "category_id = :category_id";
                $params[':category_id'] = $data['category_id'];
            }
            if (isset($data['is_active'])) {
                $updateFields[] = "is_active = :is_active";
                $params[':is_active'] = $data['is_active'];
            }
            if (isset($data['sort_order'])) {
                $updateFields[] = "sort_order = :sort_order";
                $params[':sort_order'] = $data['sort_order'];
            }

            if (empty($updateFields)) {
                return (int)$data['id']; // No changes
            }

            $sql = "UPDATE tenant_categories SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$data['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO tenant_categories (tenant_id, category_id, is_active, sort_order, created_at)
                VALUES (:tenant_id, :category_id, :is_active, :sort_order, NOW())
            ");
            $stmt->execute([
                ':tenant_id'   => $data['tenant_id'],
                ':category_id' => $data['category_id'],
                ':is_active'   => $data['is_active'] ?? 1,
                ':sort_order'  => $data['sort_order'] ?? 0,
            ]);
            return (int)$this->pdo->lastInsertId();
        }
    }

    /**
     * Return all descendant category IDs for a given set of category IDs.
     * Uses a recursive CTE (supported by MySQL 8+ and MariaDB 10.2+).
     * Falls back to an iterative in-application recursion for older engines.
     *
     * @param  int[] $rootIds
     * @return int[]  flat, deduplicated list (includes the original $rootIds)
     */
    public function getDescendantIds(array $rootIds): array
    {
        if (empty($rootIds)) {
            return [];
        }

        // Collect all descendant IDs iteratively to support any MySQL/MariaDB version
        $all = array_flip($rootIds);   // use as a set
        $queue = $rootIds;

        while (!empty($queue)) {
            $placeholders = implode(',', array_fill(0, count($queue), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id FROM categories WHERE parent_id IN ($placeholders) AND (is_active IS NULL OR is_active = 1)"
            );
            $stmt->execute(array_values($queue));
            $children = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            $queue = [];
            foreach ($children as $childId) {
                $childId = (int)$childId;
                if (!isset($all[$childId])) {
                    $all[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_keys($all);
    }

    /**
     * Validate that all given category IDs actually exist in the DB.
     *
     * @param  int[] $ids
     * @return int[] IDs that do NOT exist
     */
    public function findMissingCategoryIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
        return array_values(array_diff($ids, $found));
    }

    /**
     * Full sync: replace all tenant-category assignments for a tenant with the new set.
     *
     * Steps (in a single transaction):
     *   1. Delete rows for the tenant that are NOT in $categoryIds.
     *   2. Insert rows for IDs that do not yet exist (INSERT IGNORE).
     *
     * @param  int   $tenantId
     * @param  int[] $categoryIds  resolved (children already expanded) list
     * @param  int   $isActive
     * @return array{added:int, removed:int}
     */
    public function syncForTenant(int $tenantId, array $categoryIds, int $isActive = 1): array
    {
        $this->pdo->beginTransaction();
        try {
            $removed = 0;
            $added   = 0;

            if (empty($categoryIds)) {
                // Remove everything for this tenant
                $stmt = $this->pdo->prepare("DELETE FROM tenant_categories WHERE tenant_id = :tid");
                $stmt->execute([':tid' => $tenantId]);
                $removed = $stmt->rowCount();
            } else {
                // 1. Delete assignments not in the new set
                $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                $params = array_merge([$tenantId], array_values($categoryIds));
                $stmt = $this->pdo->prepare(
                    "DELETE FROM tenant_categories WHERE tenant_id = ? AND category_id NOT IN ($placeholders)"
                );
                $stmt->execute($params);
                $removed = $stmt->rowCount();

                // 2. Fetch which IDs already exist
                $stmt = $this->pdo->prepare(
                    "SELECT category_id FROM tenant_categories WHERE tenant_id = ? AND category_id IN ($placeholders)"
                );
                $stmt->execute($params);
                $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
                $toInsert = array_values(array_diff($categoryIds, $existing));

                // 3. Batch-insert missing rows
                if (!empty($toInsert)) {
                    $rowPlaceholders = implode(',', array_fill(0, count($toInsert), '(?,?,?,?,NOW())'));
                    $insertParams = [];
                    foreach ($toInsert as $catId) {
                        $insertParams[] = $tenantId;
                        $insertParams[] = $catId;
                        $insertParams[] = $isActive;
                        $insertParams[] = 0; // default sort_order
                    }
                    $stmt = $this->pdo->prepare(
                        "INSERT IGNORE INTO tenant_categories (tenant_id, category_id, is_active, sort_order, created_at)
                         VALUES $rowPlaceholders"
                    );
                    $stmt->execute($insertParams);
                    $added = $stmt->rowCount();
                }
            }

            $this->pdo->commit();
            return ['added' => $added, 'removed' => $removed];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tenant_categories WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
