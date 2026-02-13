<?php
declare(strict_types=1);

/**
 * PDO repository for the discount_conditions table.
 */
final class PdoDiscountConditionsRepository
{
    private PDO $pdo;

    private const ALLOWED_CONDITION_TYPES = [
        'min_cart_total', 'min_items_count', 'first_order_only', 'weekend_only',
        'specific_payment_method', 'customer_segment', 'geo_location',
        'time_window', 'custom_rule',
    ];

    private const ALLOWED_OPERATORS = [
        '=', '>', '<', '>=', '<=', '<>', 'in', 'not_in', 'between', 'contains',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List conditions by discount
    // ================================
    public function listByDiscount(int $discountId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM discount_conditions
            WHERE discount_id = :discount_id
        ");
        $stmt->execute([':discount_id' => $discountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create
    // ================================
    public function create(array $data): int
    {
        $this->validateTypes($data);

        $stmt = $this->pdo->prepare("
            INSERT INTO discount_conditions (discount_id, condition_type, operator, condition_value)
            VALUES (:discount_id, :condition_type, :operator, :condition_value)
        ");
        $stmt->execute([
            ':discount_id'    => $data['discount_id'],
            ':condition_type' => $data['condition_type'],
            ':operator'       => $data['operator'],
            ':condition_value' => $data['condition_value'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Update
    // ================================
    public function update(int $id, array $data): bool
    {
        $setClauses = [];
        $params = [':id' => $id];

        $allowed = ['condition_type', 'operator', 'condition_value'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        // Validate types if they are being changed
        if (isset($data['condition_type']) && !in_array($data['condition_type'], self::ALLOWED_CONDITION_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid condition_type. Allowed: " . implode(', ', self::ALLOWED_CONDITION_TYPES)
            );
        }
        if (isset($data['operator']) && !in_array($data['operator'], self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException(
                "Invalid operator. Allowed: " . implode(', ', self::ALLOWED_OPERATORS)
            );
        }

        $sql = "UPDATE discount_conditions SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM discount_conditions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Validate condition_type and operator
    // ================================
    private function validateTypes(array $data): void
    {
        if (!isset($data['condition_type']) || !in_array($data['condition_type'], self::ALLOWED_CONDITION_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid condition_type. Allowed: " . implode(', ', self::ALLOWED_CONDITION_TYPES)
            );
        }
        if (!isset($data['operator']) || !in_array($data['operator'], self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException(
                "Invalid operator. Allowed: " . implode(', ', self::ALLOWED_OPERATORS)
            );
        }
    }
}
