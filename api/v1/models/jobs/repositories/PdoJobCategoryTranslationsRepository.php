<?php
declare(strict_types=1);

final class PdoJobCategoryTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // Get translation by category and language
    // ================================
    public function find(int $categoryId, string $languageCode): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT jct.*,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM job_category_translations jct
            JOIN languages l ON jct.language_code = l.code
            WHERE jct.category_id = :category_id 
            AND jct.language_code = :language_code
            LIMIT 1
        ");
        $stmt->execute([
            ':category_id' => $categoryId,
            ':language_code' => $languageCode
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get all translations for a category
    // ================================
    public function getAllForCategory(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT jct.*,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM job_category_translations jct
            JOIN languages l ON jct.language_code = l.code
            WHERE jct.category_id = :category_id
            ORDER BY jct.language_code
        ");
        $stmt->execute([':category_id' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Save or update translation
    // ================================
    public function save(int $categoryId, string $languageCode, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO job_category_translations (
                category_id, language_code, name, description
            ) VALUES (
                :category_id, :language_code, :name, :description
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description)
        ");

        return $stmt->execute([
            ':category_id' => $categoryId,
            ':language_code' => $languageCode,
            ':name' => $data['name'] ?? '',
            ':description' => $data['description'] ?? null
        ]);
    }

    // ================================
    // Delete translation
    // ================================
    public function delete(int $categoryId, string $languageCode): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM job_category_translations 
            WHERE category_id = :category_id 
            AND language_code = :language_code
        ");
        return $stmt->execute([
            ':category_id' => $categoryId,
            ':language_code' => $languageCode
        ]);
    }

    // ================================
    // Delete all translations for a category
    // ================================
    public function deleteAllForCategory(int $categoryId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM job_category_translations 
            WHERE category_id = :category_id
        ");
        return $stmt->execute([':category_id' => $categoryId]);
    }

    // ================================
    // Check if translation exists
    // ================================
    public function exists(int $categoryId, string $languageCode): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM job_category_translations 
            WHERE category_id = :category_id 
            AND language_code = :language_code
        ");
        $stmt->execute([
            ':category_id' => $categoryId,
            ':language_code' => $languageCode
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ================================
    // Get available languages for a category
    // ================================
    public function getAvailableLanguages(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT jct.language_code,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM job_category_translations jct
            JOIN languages l ON jct.language_code = l.code
            WHERE jct.category_id = :category_id
            ORDER BY jct.language_code
        ");
        $stmt->execute([':category_id' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get missing languages for a category
    // ================================
    public function getMissingLanguages(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT l.code, l.name, l.direction
            FROM languages l
            WHERE l.code NOT IN (
                SELECT language_code 
                FROM job_category_translations 
                WHERE category_id = :category_id
            )
            ORDER BY l.code
        ");
        $stmt->execute([':category_id' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Bulk save translations
    // ================================
    public function bulkSave(int $categoryId, array $translations): bool
    {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($translations as $languageCode => $data) {
                $this->save($categoryId, $languageCode, $data);
            }
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}