<?php
declare(strict_types=1);

/**
 * PDO repository for the discount_translations table.
 */
final class PdoDiscountTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List translations by discount
    // ================================
    public function listByDiscount(int $discountId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM discount_translations
            WHERE discount_id = :discount_id
        ");
        $stmt->execute([':discount_id' => $discountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM discount_translations WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // ================================
    // Upsert (insert or update)
    // ================================
    /**
     * @return int The translation row ID
     */
    public function upsert(int $discountId, string $langCode, array $data): int
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM discount_translations
            WHERE discount_id = :discount_id AND language_code = :language_code
        ");
        $stmt->execute([
            ':discount_id'   => $discountId,
            ':language_code' => $langCode,
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $stmt = $this->pdo->prepare("
                UPDATE discount_translations
                SET name = :name,
                    description = :description,
                    terms_conditions = :terms_conditions,
                    marketing_badge = :marketing_badge
                WHERE id = :id
            ");
            $stmt->execute([
                ':id'               => (int)$existingId,
                ':name'             => $data['name'] ?? '',
                ':description'      => $data['description'] ?? null,
                ':terms_conditions' => $data['terms_conditions'] ?? null,
                ':marketing_badge'  => $data['marketing_badge'] ?? null,
            ]);
            return (int)$existingId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO discount_translations
                (discount_id, language_code, name, description, terms_conditions, marketing_badge)
            VALUES
                (:discount_id, :language_code, :name, :description, :terms_conditions, :marketing_badge)
        ");
        $stmt->execute([
            ':discount_id'      => $discountId,
            ':language_code'    => $langCode,
            ':name'             => $data['name'] ?? '',
            ':description'      => $data['description'] ?? null,
            ':terms_conditions' => $data['terms_conditions'] ?? null,
            ':marketing_badge'  => $data['marketing_badge'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete by ID
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM discount_translations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Delete all translations for a discount
    // ================================
    public function deleteByDiscount(int $discountId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM discount_translations WHERE discount_id = :discount_id");
        return $stmt->execute([':discount_id' => $discountId]);
    }
}
