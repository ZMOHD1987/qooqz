<?php
declare(strict_types=1);

final class PdoNotificationTypesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'code', 'name', 'is_active', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'code', 'name', 'is_active'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT * FROM notification_types WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'code' || $col === 'name') {
                    $sql .= " AND $col LIKE :$col";
                    $params[":$col"] = '%' . $filters[$col] . '%';
                } elseif ($col === 'is_active') {
                    $sql .= " AND is_active = :is_active";
                    $params[':is_active'] = (int)$filters['is_active'];
                } else {
                    $sql .= " AND $col = :$col";
                    $params[":$col"] = $filters[$col];
                }
            }
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderBy $orderDir";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        if ($offset !== null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM notification_types WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'code' || $col === 'name') {
                    $sql .= " AND $col LIKE :$col";
                    $params[":$col"] = '%' . $filters[$col] . '%';
                } elseif ($col === 'is_active') {
                    $sql .= " AND is_active = :is_active";
                    $params[':is_active'] = (int)$filters['is_active'];
                } else {
                    $sql .= " AND $col = :$col";
                    $params[":$col"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notification_types WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notification_types WHERE code = :code");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets = [];
            $params = [':id' => $id];
            $allowed = ['code', 'name', 'description', 'is_active', 'default_template'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }
            $sets[] = "updated_at = CURRENT_TIMESTAMP";

            $sql = "UPDATE notification_types SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        // Insert
        $cols = [];
        $placeholders = [];
        $params = [];
        $allowed = ['code', 'name', 'description', 'is_active', 'default_template'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $cols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            }
        }
        // Ensure required fields: code, name are NOT NULL
        if (!in_array('code', $cols)) {
            throw new InvalidArgumentException('Field "code" is required.');
        }
        if (!in_array('name', $cols)) {
            throw new InvalidArgumentException('Field "name" is required.');
        }

        $sql = "INSERT INTO notification_types (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notification_types WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}