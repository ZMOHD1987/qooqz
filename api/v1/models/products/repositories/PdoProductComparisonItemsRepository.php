<?php
declare(strict_types=1);

final class PdoProductComparisonItemsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * جلب جميع عناصر مقارنة معينة
     */
    public function getByComparison(int $tenantId, int $comparisonId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pci.*,
                   COALESCE(pt.name, p.name) AS product_name,
                   p.sku AS product_sku
            FROM product_comparison_items pci
            INNER JOIN products p ON pci.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id AND pci.comparison_id = :comparison_id
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':comparison_id' => $comparisonId,
            ':lang' => $lang
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * جلب عنصر محدد
     */
    public function find(int $tenantId, int $itemId, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pci.*,
                   COALESCE(pt.name, p.name) AS product_name,
                   p.sku AS product_sku
            FROM product_comparison_items pci
            INNER JOIN products p ON pci.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = :lang
            WHERE e.tenant_id = :tenant_id AND pci.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id' => $itemId,
            ':lang' => $lang
        ]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    /**
     * إنشاء عنصر جديد
     */
    public function create(int $tenantId, array $data): int
    {
        // التحقق من أن المقارنة تنتمي للمستأجر (عبر المنتج الرئيسي للمقارنة)
        $this->validateComparisonAccess($tenantId, (int)$data['comparison_id']);
        // التحقق من أن المنتج المضاف ينتمي للمستأجر
        $this->validateProductBelongsToTenant((int)$data['product_id'], $tenantId);

        $stmt = $this->pdo->prepare("
            INSERT INTO product_comparison_items (comparison_id, product_id, added_at)
            VALUES (:comparison_id, :product_id, NOW())
        ");
        $stmt->execute([
            ':comparison_id' => $data['comparison_id'],
            ':product_id' => $data['product_id']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * حذف عنصر
     */
    public function delete(int $tenantId, int $itemId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE pci FROM product_comparison_items pci
            INNER JOIN products p ON pci.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id AND pci.id = :id
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $itemId]);
    }

    /**
     * حذف جميع عناصر مقارنة معينة
     */
    public function deleteByComparison(int $tenantId, int $comparisonId): bool
    {
        $this->validateComparisonAccess($tenantId, $comparisonId);
        $stmt = $this->pdo->prepare("DELETE FROM product_comparison_items WHERE comparison_id = :comparison_id");
        return $stmt->execute([':comparison_id' => $comparisonId]);
    }

    /**
     * التحقق من أن المقارنة تنتمي للمستأجر (عبر المنتج الرئيسي)
     */
    private function validateComparisonAccess(int $tenantId, int $comparisonId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT pc.id
            FROM product_comparisons pc
            INNER JOIN products p ON pc.product_id = p.id
            INNER JOIN entities e ON p.tenant_id = e.id
            WHERE e.tenant_id = :tenant_id AND pc.id = :comparison_id
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':comparison_id' => $comparisonId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Comparison not found or access denied.');
        }
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
}