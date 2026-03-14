<?php
declare(strict_types=1);

final class PdoNotificationDeliveriesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY   = ['id', 'notification_id', 'channel_id', 'delivery_status', 'attempts', 'sent_at', 'created_at'];
    private const FILTERABLE_COLUMNS = ['notification_id', 'channel_id', 'delivery_status'];

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
        $sql    = "SELECT d.*,
                          nc.name  AS channel_name,
                          n.title  AS notification_title,
                          t.name   AS tenant_name
                   FROM notification_deliveries d
                   LEFT JOIN notification_channels nc ON nc.id = d.channel_id
                   LEFT JOIN notifications n          ON n.id  = d.notification_id
                   LEFT JOIN tenants t                ON t.id  = n.tenant_id
                   WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND d.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY d.$orderBy $orderDir";

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
        $sql    = "SELECT COUNT(*) FROM notification_deliveries d WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND d.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.*,
                    nc.name  AS channel_name,
                    n.title  AS notification_title,
                    t.name   AS tenant_name
             FROM notification_deliveries d
             LEFT JOIN notification_channels nc ON nc.id = d.channel_id
             LEFT JOIN notifications n          ON n.id  = d.notification_id
             LEFT JOIN tenants t                ON t.id  = n.tenant_id
             WHERE d.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);
        $allowed  = ['notification_id', 'channel_id', 'delivery_status', 'attempts', 'sent_at', 'error_message'];

        if ($isUpdate) {
            $id     = (int)$data['id'];
            $sets   = [];
            $params = [':id' => $id];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[]          = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }
            $sql  = "UPDATE notification_deliveries SET " . implode(', ', $sets) . " WHERE id = :id";
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
                $cols[]          = $col;
                $placeholders[]  = ":$col";
                $params[":$col"] = $data[$col];
            }
        }

        if (!in_array('notification_id', $cols)) {
            throw new InvalidArgumentException('Field "notification_id" is required.');
        }
        if (!in_array('channel_id', $cols)) {
            throw new InvalidArgumentException('Field "channel_id" is required.');
        }

        $sql  = "INSERT INTO notification_deliveries (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notification_deliveries WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
