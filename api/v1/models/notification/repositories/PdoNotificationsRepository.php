<?php
declare(strict_types=1);

final class PdoNotificationsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'tenant_id', 'sender_entity_id', 'entity_id',
        'sent_at', 'notification_type_id', 'priority', 'expires_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'tenant_id', 'sender_entity_id', 'entity_id', 'notification_type_id', 'priority'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sent_at',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT n.*, nt.name AS type_name
                FROM notifications n
                LEFT JOIN notification_types nt ON nt.id = n.notification_type_id
                WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND n.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        // Title / message search
        if (!empty($filters['search'])) {
            $sql .= " AND (n.title LIKE :search OR n.message LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $sql .= " AND n.sent_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND n.sent_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sent_at';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY n.$orderBy $orderDir";

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
        $sql = "SELECT COUNT(*) FROM notifications n WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND n.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (n.title LIKE :search OR n.message LIKE :search2)";
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND n.sent_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND n.sent_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT n.*, nt.name AS type_name
             FROM notifications n
             LEFT JOIN notification_types nt ON nt.id = n.notification_type_id
             WHERE n.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $allowed = [
            'tenant_id', 'sender_entity_id', 'entity_id',
            'title', 'message', 'data',
            'notification_type_id', 'priority', 'expires_at'
        ];

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets   = [];
            $params = [':id' => $id];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            $sql  = "UPDATE notifications SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        // Insert
        $cols         = [];
        $placeholders = [];
        $params       = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $cols[]         = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            }
        }

        if (!in_array('tenant_id', $cols)) {
            throw new InvalidArgumentException('Field "tenant_id" is required.');
        }
        if (!in_array('title', $cols)) {
            throw new InvalidArgumentException('Field "title" is required.');
        }
        if (!in_array('message', $cols)) {
            throw new InvalidArgumentException('Field "message" is required.');
        }

        $sql  = "INSERT INTO notifications (" . implode(', ', $cols) . ", sent_at)
                 VALUES (" . implode(', ', $placeholders) . ", NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}