<?php
declare(strict_types=1);

final class PdoImageTypesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all image types
     */
    public function all(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                id,
                code,
                name,
                description,
                width,
                height,
                crop,
                quality,
                format,
                is_thumbnail
            FROM image_types
            ORDER BY id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find image type by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                code,
                name,
                description,
                width,
                height,
                crop,
                quality,
                format,
                is_thumbnail
            FROM image_types
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find image type by code (الأهم عمليًا)
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                code,
                name,
                description,
                width,
                height,
                crop,
                quality,
                format,
                is_thumbnail
            FROM image_types
            WHERE code = :code
            LIMIT 1
        ");
        $stmt->execute([':code' => $code]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create new image type
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO image_types (
                code,
                name,
                description,
                width,
                height,
                crop,
                quality,
                format,
                is_thumbnail
            ) VALUES (
                :code,
                :name,
                :description,
                :width,
                :height,
                :crop,
                :quality,
                :format,
                :is_thumbnail
            )
        ");

        $stmt->execute([
            ':code'         => $data['code'],
            ':name'         => $data['name'],
            ':description'  => $data['description'] ?? null,
            ':width'        => $data['width'],
            ':height'       => $data['height'],
            ':crop'         => $data['crop'] ?? 'cover',
            ':quality'      => $data['quality'] ?? 85,
            ':format'       => $data['format'] ?? 'webp',
            ':is_thumbnail' => (int)($data['is_thumbnail'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update image type
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE image_types
            SET
                code = :code,
                name = :name,
                description = :description,
                width = :width,
                height = :height,
                crop = :crop,
                quality = :quality,
                format = :format,
                is_thumbnail = :is_thumbnail
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'           => $id,
            ':code'         => $data['code'],
            ':name'         => $data['name'],
            ':description'  => $data['description'] ?? null,
            ':width'        => $data['width'],
            ':height'       => $data['height'],
            ':crop'         => $data['crop'] ?? 'cover',
            ':quality'      => $data['quality'] ?? 85,
            ':format'       => $data['format'] ?? 'webp',
            ':is_thumbnail' => (int)($data['is_thumbnail'] ?? 0),
        ]);
    }

    /**
     * Delete image type
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM image_types
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }
}
