<?php
declare(strict_types=1);

final class PdoEntityTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByEntity(int $entityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM entity_translations
            WHERE entity_id = :entity_id
        ");
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $data): int
    {
        // Check if exists
        if (isset($data['id'])) {
            return $this->update($data);
        }

        // Check unique constraint (entity_id + language_code)
        $stmt = $this->pdo->prepare("
            SELECT id FROM entity_translations
            WHERE entity_id = :entity_id AND language_code = :language_code
        ");
        $stmt->execute([
            ':entity_id' => $data['entity_id'],
            ':language_code' => $data['language_code']
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $data['id'] = (int)$existingId;
            return $this->update($data);
        }

        return $this->create($data);
    }

    private function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO entity_translations (
                entity_id, language_code, store_name, description, 
                meta_title, meta_description
            ) VALUES (
                :entity_id, :language_code, :store_name, :description,
                :meta_title, :meta_description
            )
        ");

        $stmt->execute([
            ':entity_id' => $data['entity_id'],
            ':language_code' => $data['language_code'],
            ':store_name' => $data['store_name'] ?? '',
            ':description' => $data['description'] ?? null,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function update(array $data): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE entity_translations SET
                store_name = :store_name,
                description = :description,
                meta_title = :meta_title,
                meta_description = :meta_description
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $data['id'],
            ':store_name' => $data['store_name'] ?? '',
            ':description' => $data['description'] ?? null,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null
        ]);

        return (int)$data['id'];
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_translations WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
