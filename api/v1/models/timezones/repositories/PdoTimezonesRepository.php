<?php
declare(strict_types=1);

final class PdoTimezonesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all timezones
     *
     * @return array<int,array>
     */
    public function all(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, timezone, label
            FROM timezones
            ORDER BY timezone ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get timezone by id
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, timezone, label
            FROM timezones
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get timezone by timezone string (unique)
     */
    public function getByTimezone(string $tz): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, timezone, label
            FROM timezones
            WHERE timezone = :tz
            LIMIT 1
        ");
        $stmt->execute([':tz' => $tz]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Insert new timezone
     *
     * Returns inserted id
     */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO timezones (timezone, label)
            VALUES (:timezone, :label)
        ");
        $stmt->execute([
            ':timezone' => $data['timezone'],
            ':label' => $data['label'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update timezone by id
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE timezones
            SET timezone = :timezone,
                label = :label
            WHERE id = :id
        ");
        return $stmt->execute([
            ':timezone' => $data['timezone'],
            ':label' => $data['label'] ?? null,
            ':id' => $id
        ]);
    }

    /**
     * Delete timezone by id
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM timezones WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}