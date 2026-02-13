<?php
declare(strict_types=1);

/**
 * PDO repository for the discount_exclusions table.
 */
final class PdoDiscountExclusionsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List exclusions by discount
    // ================================
    public function listByDiscount(int $discountId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM discount_exclusions
            WHERE discount_id = :discount_id
        ");
        $stmt->execute([':discount_id' => $discountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create (with duplicate check)
    // ================================
    public function create(int $discountId, int $excludedDiscountId): int
    {
        // Check for duplicate
        $stmt = $this->pdo->prepare("
            SELECT id FROM discount_exclusions
            WHERE discount_id = :discount_id AND excluded_discount_id = :excluded_discount_id
        ");
        $stmt->execute([
            ':discount_id'          => $discountId,
            ':excluded_discount_id' => $excludedDiscountId,
        ]);

        if ($stmt->fetchColumn()) {
            throw new InvalidArgumentException("This exclusion already exists");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO discount_exclusions (discount_id, excluded_discount_id)
            VALUES (:discount_id, :excluded_discount_id)
        ");
        $stmt->execute([
            ':discount_id'          => $discountId,
            ':excluded_discount_id' => $excludedDiscountId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM discount_exclusions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
