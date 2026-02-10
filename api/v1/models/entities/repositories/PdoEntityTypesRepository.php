<?php
declare(strict_types=1);

final class PdoEntityTypesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id','code','name','created_at','updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'code','name'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================
    // List
    // =========================
    public function all(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $sql = "SELECT * FROM entity_types WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (!empty($filters[$col])) {
                $sql .= " AND {$col} LIKE :{$col}";
                $params[":{$col}"] = '%' . $filters[$col] . '%';
            }
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        if ($limit !== null)  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        $sql = "SELECT COUNT(*) FROM entity_types WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (!empty($filters[$col])) {
                $sql .= " AND {$col} LIKE :{$col}";
                $params[":{$col}"] = '%' . $filters[$col] . '%';
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // =========================
    // Find
    // =========================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM entity_types WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================
    // Save
    // =========================
    public function save(array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE entity_types SET
                    code = :code,
                    name = :name,
                    description = :description,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $data['id'],
                ':code' => $data['code'],
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null
            ]);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO entity_types (code, name, description)
            VALUES (:code, :name, :description)
        ");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // =========================
    // Delete
    // =========================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entity_types WHERE id = :id"
        );
        return $stmt->execute([':id'=>$id]);
    }
}
