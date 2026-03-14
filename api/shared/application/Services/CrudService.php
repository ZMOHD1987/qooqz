<?php
declare(strict_types=1);

namespace Shared\Application\Services;

use PDO;
use RuntimeException;

final class CrudService
{
    private PDO $pdo;
    private array $entities;

    public function __construct(PDO $pdo, array $entities)
    {
        $this->pdo = $pdo;
        $this->entities = $entities;
    }

    private function entity(string $name): array
    {
        if (!isset($this->entities[$name])) {
            throw new RuntimeException("Unknown entity: {$name}");
        }
        return $this->entities[$name];
    }

    /* ================= CREATE ================= */
    public function create(string $entity, array $data): int
    {
        $cfg = $this->entity($entity);

        $fields = array_intersect(array_keys($data), $cfg['fillable']);
        if (!$fields) {
            throw new RuntimeException('No valid fields for insert');
        }

        $columns = implode(',', $fields);
        $placeholders = ':' . implode(',:', $fields);

        $sql = "INSERT INTO {$cfg['table']} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);

        foreach ($fields as $f) {
            $stmt->bindValue(":{$f}", $data[$f]);
        }

        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    /* ================= READ ================= */
    public function read(string $entity, int $id): array
    {
        $cfg = $this->entity($entity);

        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$cfg['table']} WHERE {$cfg['primary']} = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Record not found');
        }

        return $row;
    }

    /* ================= UPDATE ================= */
    public function update(string $entity, int $id, array $data): void
    {
        $cfg = $this->entity($entity);

        $fields = array_intersect(array_keys($data), $cfg['fillable']);
        if (!$fields) {
            throw new RuntimeException('No valid fields for update');
        }

        $set = implode(', ', array_map(fn($f) => "{$f} = :{$f}", $fields));

        $sql = "UPDATE {$cfg['table']} SET {$set} WHERE {$cfg['primary']} = :id";
        $stmt = $this->pdo->prepare($sql);

        foreach ($fields as $f) {
            $stmt->bindValue(":{$f}", $data[$f]);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $stmt->execute();
    }

    /* ================= DELETE ================= */
    public function delete(string $entity, int $id): void
    {
        $cfg = $this->entity($entity);

        $stmt = $this->pdo->prepare(
            "DELETE FROM {$cfg['table']} WHERE {$cfg['primary']} = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /* ================= LIST ================= */
    public function list(string $entity, int $limit = 50, int $offset = 0): array
    {
        $cfg = $this->entity($entity);

        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$cfg['table']} LIMIT :o, :l"
        );
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
