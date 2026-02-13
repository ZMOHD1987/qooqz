<?php
declare(strict_types=1);

/**
 * PDO repository for the discount_actions table.
 */
final class PdoDiscountActionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ACTION_TYPES = [
        'percentage', 'fixed', 'free_shipping', 'buy_x_get_y', 'free_item',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List actions by discount
    // ================================
    public function listByDiscount(int $discountId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM discount_actions
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
        if (!isset($data['action_type']) || !in_array($data['action_type'], self::ALLOWED_ACTION_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid action_type. Allowed: " . implode(', ', self::ALLOWED_ACTION_TYPES)
            );
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO discount_actions (discount_id, action_type, action_value)
            VALUES (:discount_id, :action_type, :action_value)
        ");
        $stmt->execute([
            ':discount_id'  => $data['discount_id'],
            ':action_type'  => $data['action_type'],
            ':action_value' => $data['action_value'] ?? null,
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

        $allowed = ['action_type', 'action_value'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[] = "{$col} = :{$col}";
                $params[':' . $col] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException("No valid fields provided for update");
        }

        if (isset($data['action_type']) && !in_array($data['action_type'], self::ALLOWED_ACTION_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid action_type. Allowed: " . implode(', ', self::ALLOWED_ACTION_TYPES)
            );
        }

        $sql = "UPDATE discount_actions SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM discount_actions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
