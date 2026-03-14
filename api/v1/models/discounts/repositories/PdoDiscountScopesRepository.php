<?php
declare(strict_types=1);

/**
 * PDO repository for the discount_scopes table.
 */
final class PdoDiscountScopesRepository
{
    private PDO $pdo;

    private const ALLOWED_SCOPE_TYPES = [
        'product', 'category', 'brand', 'collection', 'supplier', 'customer_group', 'all',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List scopes by discount
    // ================================
    public function listByDiscount(int $discountId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM discount_scopes
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
        if (!isset($data['scope_type']) || !in_array($data['scope_type'], self::ALLOWED_SCOPE_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid scope_type. Allowed: " . implode(', ', self::ALLOWED_SCOPE_TYPES)
            );
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO discount_scopes (discount_id, scope_type, scope_id)
            VALUES (:discount_id, :scope_type, :scope_id)
        ");
        $stmt->execute([
            ':discount_id' => $data['discount_id'],
            ':scope_type'  => $data['scope_type'],
            ':scope_id'    => $data['scope_id'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM discount_scopes WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
