<?php
declare(strict_types=1);

final class PdoProductBundleItemsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all items of a specific bundle (with product names translated)
     */
    public function getByBundle(int $tenantId, int $bundleId, string $lang = 'ar'): array
    {
        $sql = "
            SELECT 
                pbi.*,
                COALESCE(pt.name, '') AS product_name,
                p.sku AS product_sku
            FROM product_bundle_items pbi
            INNER JOIN product_bundles pb ON pbi.bundle_id = pb.id
            INNER JOIN entities e ON pb.entity_id = e.id
            LEFT JOIN products p ON p.id = pbi.product_id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id AND pb.id = :bundle_id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':bundle_id' => $bundleId,
            ':lang'      => $lang
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a single item by its ID (with product name)
     */
    public function find(int $tenantId, int $itemId, string $lang = 'ar'): ?array
    {
        $sql = "
            SELECT 
                pbi.*,
                COALESCE(pt.name, '') AS product_name,
                p.sku AS product_sku
            FROM product_bundle_items pbi
            INNER JOIN product_bundles pb ON pbi.bundle_id = pb.id
            INNER JOIN entities e ON pb.entity_id = e.id
            LEFT JOIN products p ON p.id = pbi.product_id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id AND pbi.id = :id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id'        => $itemId,
            ':lang'      => $lang
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    /**
     * Create a new bundle item
     */
    public function create(int $tenantId, array $data): int
    {
        // First verify that the bundle belongs to the tenant
        $this->validateBundleAccess($tenantId, (int)$data['bundle_id']);

        $stmt = $this->pdo->prepare("
            INSERT INTO product_bundle_items (bundle_id, product_id, quantity, product_price)
            VALUES (:bundle_id, :product_id, :quantity, :product_price)
        ");
        $stmt->execute([
            ':bundle_id'     => $data['bundle_id'],
            ':product_id'    => $data['product_id'],
            ':quantity'      => $data['quantity'] ?? 1,
            ':product_price' => $data['product_price'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing bundle item
     */
    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Item ID required for update.');
        }

        // Check ownership
        $this->validateItemAccess($tenantId, (int)$data['id']);

        $fields = [];
        $params = [':id' => $data['id']];

        if (isset($data['product_id'])) {
            $fields[] = "product_id = :product_id";
            $params[':product_id'] = $data['product_id'];
        }
        if (array_key_exists('quantity', $data)) {
            $fields[] = "quantity = :quantity";
            $params[':quantity'] = $data['quantity'];
        }
        if (array_key_exists('product_price', $data)) {
            $fields[] = "product_price = :product_price";
            $params[':product_price'] = $data['product_price'];
        }

        if (empty($fields)) {
            return true; // nothing to update
        }

        $sql = "UPDATE product_bundle_items SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a single bundle item
     */
    public function delete(int $tenantId, int $itemId): bool
    {
        $sql = "
            DELETE pbi FROM product_bundle_items pbi
            INNER JOIN product_bundles pb ON pbi.bundle_id = pb.id
            INNER JOIN entities e ON pb.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND pbi.id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $itemId]);
    }

    /**
     * Delete all items of a specific bundle
     */
    public function deleteByBundle(int $tenantId, int $bundleId): bool
    {
        $this->validateBundleAccess($tenantId, $bundleId);
        $stmt = $this->pdo->prepare("DELETE FROM product_bundle_items WHERE bundle_id = :bundle_id");
        return $stmt->execute([':bundle_id' => $bundleId]);
    }

    // ================================
    // Helper methods for access control
    // ================================

    private function validateBundleAccess(int $tenantId, int $bundleId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT pb.id FROM product_bundles pb
            INNER JOIN entities e ON pb.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND pb.id = :bundle_id
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':bundle_id' => $bundleId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Bundle not found or access denied.');
        }
    }

    private function validateItemAccess(int $tenantId, int $itemId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT pbi.id FROM product_bundle_items pbi
            INNER JOIN product_bundles pb ON pbi.bundle_id = pb.id
            INNER JOIN entities e ON pb.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND pbi.id = :id
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $itemId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Item not found or access denied.');
        }
    }
}