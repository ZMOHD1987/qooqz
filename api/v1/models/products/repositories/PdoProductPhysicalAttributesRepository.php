<?php
declare(strict_types=1);

final class PdoProductPhysicalAttributesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
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

        if (!empty($filters['min_weight'])) {
            $sql .= " AND weight >= :min_weight";
            $params[':min_weight'] = (float)$filters['min_weight'];
        }

        if (!empty($filters['max_weight'])) {
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
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue(
                $key,
                $val,
                is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $stmt->execute();
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
        return $this->findOne('product_id', $productId);
    }

    public function findByVariant(int $variantId): ?array
    {
        return $this->findOne('variant_id', $variantId);
    }

    private function findOne(string $column, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM product_physical_attributes
            WHERE {$column} = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
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

        if ($isProduct === $isVariant) {
            throw new InvalidArgumentException(
                'Exactly one of product_id or variant_id must be provided.'
            );
        }

        $column = $isProduct ? 'product_id' : 'variant_id';
        $id     = (int)($isProduct ? $data['product_id'] : $data['variant_id']);

        $existing = $this->findOne($column, $id);

        if ($existing) {
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
                WHERE {$column} = :id
            ");

            $stmt->execute([
                ':weight' => $data['weight'] ?? null,
                ':length' => $data['length'] ?? null,
                ':width'  => $data['width'] ?? null,
                ':height' => $data['height'] ?? null,
                ':weight_unit' => $data['weight_unit'] ?? 'kg',
                ':dimension_unit' => $data['dimension_unit'] ?? 'cm',
                ':id' => $id,
            ]);

            return $id;
        }

        // Insert
        $stmt = $this->pdo->prepare("
            INSERT INTO product_physical_attributes
            (
                {$column},
                weight, length, width, height,
                weight_unit, dimension_unit,
                created_at, updated_at
            )
            VALUES
            (
                :id,
                :weight, :length, :width, :height,
                :weight_unit, :dimension_unit,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':id' => $id,
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
        return $this->delete('product_id', $productId);
    }

    public function deleteByVariant(int $variantId): bool
    {
        return $this->delete('variant_id', $variantId);
    }

    private function delete(string $column, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM product_physical_attributes
            WHERE {$column} = :id
        ");
        return $stmt->execute([':id' => $id]);
    }
}
