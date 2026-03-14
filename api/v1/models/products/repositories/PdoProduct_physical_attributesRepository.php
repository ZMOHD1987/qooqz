<?php
declare(strict_types=1);

final class PdoProductPhysicalAttributesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id',
        'product_id',
        'variant_id',
        'weight',
        'length',
        'width',
        'height',
        'created_at',
        'updated_at'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // تفعيل وضع الأخطاء
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // =========================================================
    // List + Filters + Pagination
    // =========================================================
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT * FROM product_physical_attributes WHERE 1=1";
        $params = [];

        if (!empty($filters['product_id'])) {
            $sql .= " AND product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['variant_id'])) {
            $sql .= " AND variant_id = :variant_id";
            $params[':variant_id'] = (int)$filters['variant_id'];
        }

        if (isset($filters['min_weight']) && $filters['min_weight'] !== '') {
            $sql .= " AND weight >= :min_weight";
            $params[':min_weight'] = (float)$filters['min_weight'];
        }

        if (isset($filters['max_weight']) && $filters['max_weight'] !== '') {
            $sql .= " AND weight <= :max_weight";
            $params[':max_weight'] = (float)$filters['max_weight'];
        }

        if (!empty($filters['weight_unit'])) {
            $sql .= " AND weight_unit = :weight_unit";
            $params[':weight_unit'] = $filters['weight_unit'];
        }

        if (!empty($filters['dimension_unit'])) {
            $sql .= " AND dimension_unit = :dimension_unit";
            $params[':dimension_unit'] = $filters['dimension_unit'];
        }

        // Ordering
        if (!in_array($orderBy, self::ALLOWED_ORDER_BY, true)) {
            $orderBy = 'created_at';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================
    // Count
    // =========================================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM product_physical_attributes WHERE 1=1";
        $params = [];

        if (!empty($filters['product_id'])) {
            $sql .= " AND product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['variant_id'])) {
            $sql .= " AND variant_id = :variant_id";
            $params[':variant_id'] = (int)$filters['variant_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // =========================================================
    // Find (Product OR Variant)
    // =========================================================
    public function findByProduct(int $productId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM product_physical_attributes
            WHERE product_id = :product_id
            LIMIT 1
        ");
        $stmt->execute([':product_id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByVariant(int $variantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM product_physical_attributes
            WHERE variant_id = :variant_id
            LIMIT 1
        ");
        $stmt->execute([':variant_id' => $variantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================
    // Save (Insert / Update)
    // =========================================================
    public function save(array $data): int
    {
        $isProduct = !empty($data['product_id']);
        $isVariant = !empty($data['variant_id']);

        // التحقق من أن واحد فقط موجود
        if ($isProduct && $isVariant) {
            throw new InvalidArgumentException(
                'Cannot provide both product_id and variant_id.'
            );
        }

        if (!$isProduct && !$isVariant) {
            throw new InvalidArgumentException(
                'Either product_id or variant_id must be provided.'
            );
        }

        if ($isProduct) {
            return $this->saveForProduct((int)$data['product_id'], $data);
        } else {
            return $this->saveForVariant((int)$data['variant_id'], $data);
        }
    }

    private function saveForProduct(int $productId, array $data): int
    {
        $existing = $this->findByProduct($productId);

        if ($existing) {
            // Update
            $stmt = $this->pdo->prepare("
                UPDATE product_physical_attributes
                SET
                    weight = :weight,
                    length = :length,
                    width  = :width,
                    height = :height,
                    weight_unit = :weight_unit,
                    dimension_unit = :dimension_unit,
                    updated_at = CURRENT_TIMESTAMP
                WHERE product_id = :product_id
            ");

            $stmt->execute([
                ':weight' => $data['weight'] ?? null,
                ':length' => $data['length'] ?? null,
                ':width'  => $data['width'] ?? null,
                ':height' => $data['height'] ?? null,
                ':weight_unit' => $data['weight_unit'] ?? 'kg',
                ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
                ':product_id' => $productId,
            ]);

            return (int)$existing['id'];
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO product_physical_attributes
            (
                product_id,
                variant_id,
                weight, length, width, height,
                weight_unit, dimension_unit,
                created_at, updated_at
            )
            VALUES
            (
                :product_id,
                NULL,
                :weight, :length, :width, :height,
                :weight_unit, :dimension_unit,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':product_id' => $productId,
            ':weight' => $data['weight'] ?? null,
            ':length' => $data['length'] ?? null,
            ':width'  => $data['width'] ?? null,
            ':height' => $data['height'] ?? null,
            ':weight_unit' => $data['weight_unit'] ?? 'kg',
            ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function saveForVariant(int $variantId, array $data): int
    {
        $existing = $this->findByVariant($variantId);

        if ($existing) {
            // Update
            $stmt = $this->pdo->prepare("
                UPDATE product_physical_attributes
                SET
                    weight = :weight,
                    length = :length,
                    width  = :width,
                    height = :height,
                    weight_unit = :weight_unit,
                    dimension_unit = :dimension_unit,
                    updated_at = CURRENT_TIMESTAMP
                WHERE variant_id = :variant_id
            ");

            $stmt->execute([
                ':weight' => $data['weight'] ?? null,
                ':length' => $data['length'] ?? null,
                ':width'  => $data['width'] ?? null,
                ':height' => $data['height'] ?? null,
                ':weight_unit' => $data['weight_unit'] ?? 'kg',
                ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
                ':variant_id' => $variantId,
            ]);

            return (int)$existing['id'];
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO product_physical_attributes
            (
                product_id,
                variant_id,
                weight, length, width, height,
                weight_unit, dimension_unit,
                created_at, updated_at
            )
            VALUES
            (
                NULL,
                :variant_id,
                :weight, :length, :width, :height,
                :weight_unit, :dimension_unit,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':variant_id' => $variantId,
            ':weight' => $data['weight'] ?? null,
            ':length' => $data['length'] ?? null,
            ':width'  => $data['width'] ?? null,
            ':height' => $data['height'] ?? null,
            ':weight_unit' => $data['weight_unit'] ?? 'kg',
            ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // =========================================================
    // Delete
    // =========================================================
    public function deleteByProduct(int $productId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM product_physical_attributes
            WHERE product_id = :product_id
        ");
        return $stmt->execute([':product_id' => $productId]);
    }

    public function deleteByVariant(int $variantId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM product_physical_attributes
            WHERE variant_id = :variant_id
        ");
        return $stmt->execute([':variant_id' => $variantId]);
    }
}