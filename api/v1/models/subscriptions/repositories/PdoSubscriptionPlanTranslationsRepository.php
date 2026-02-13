<?php
declare(strict_types=1);

/**
 * PDO repository for the subscription_plan_translations table.
 */
final class PdoSubscriptionPlanTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List by plan_id
    // ================================
    public function listByPlan(int $planId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM subscription_plan_translations WHERE plan_id = :plan_id ORDER BY language_code");
        $stmt->execute([':plan_id' => $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM subscription_plan_translations WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by plan_id + language_code
    // ================================
    public function findByPlanAndLang(int $planId, string $langCode): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM subscription_plan_translations WHERE plan_id = :plan_id AND language_code = :language_code LIMIT 1");
        $stmt->execute([':plan_id' => $planId, ':language_code' => $langCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Upsert by plan_id + language_code
    // ================================
    public function upsert(int $planId, string $langCode, array $data): int
    {
        $existing = $this->findByPlanAndLang($planId, $langCode);

        if ($existing) {
            $this->pdo->prepare("
                UPDATE subscription_plan_translations
                SET plan_name = :plan_name, description = :description, features = :features, updated_at = NOW()
                WHERE plan_id = :plan_id AND language_code = :language_code
            ")->execute([
                ':plan_name'     => $data['plan_name'] ?? $existing['plan_name'],
                ':description'   => $data['description'] ?? $existing['description'],
                ':features'      => $data['features'] ?? $existing['features'],
                ':plan_id'       => $planId,
                ':language_code' => $langCode,
            ]);
            return (int)$existing['id'];
        }

        $this->pdo->prepare("
            INSERT INTO subscription_plan_translations
                (plan_id, language_code, plan_name, description, features, created_at, updated_at)
            VALUES
                (:plan_id, :language_code, :plan_name, :description, :features, NOW(), NOW())
        ")->execute([
            ':plan_id'       => $planId,
            ':language_code' => $langCode,
            ':plan_name'     => $data['plan_name'] ?? '',
            ':description'   => $data['description'] ?? null,
            ':features'      => $data['features'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM subscription_plan_translations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ================================
    // Delete all by plan_id
    // ================================
    public function deleteByPlan(int $planId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM subscription_plan_translations WHERE plan_id = :plan_id");
        return $stmt->execute([':plan_id' => $planId]);
    }
}
