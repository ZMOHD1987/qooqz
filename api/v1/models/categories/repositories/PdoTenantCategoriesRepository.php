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

    public function all(?int $tenantId = null, ?int $categoryId = null, ?int $isActive = null, int $offset = 0, int $limit = null): array
    {
        $sql = "SELECT tc.*, t.name AS tenant_name, c.name AS category_name
                FROM tenant_categories tc
                LEFT JOIN tenants t ON tc.tenant_id = t.id
                LEFT JOIN categories c ON tc.category_id = c.id
                WHERE 1=1";

        $params = [];

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

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT tc.*, t.name AS tenant_name, c.name AS category_name
            FROM tenant_categories tc
            LEFT JOIN tenants t ON tc.tenant_id = t.id
            LEFT JOIN categories c ON tc.category_id = c.id
            WHERE tc.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
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

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM tenant_categories WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}