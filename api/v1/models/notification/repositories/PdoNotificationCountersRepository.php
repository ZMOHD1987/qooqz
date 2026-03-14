<?php
declare(strict_types=1);

final class PdoNotificationCountersRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY   = ['id', 'tenant_id', 'recipient_type', 'recipient_id', 'unread_count', 'updated_at'];
    private const FILTERABLE_COLUMNS = ['tenant_id', 'recipient_type', 'recipient_id'];

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
        $sql    = "SELECT c.*,
                          t.name AS tenant_name,
                          CASE 
                              WHEN c.recipient_type = 'user' THEN u.username
                              WHEN c.recipient_type = 'entity' THEN e.store_name
                              WHEN c.recipient_type = 'tenant' THEN t_rec.name
                              ELSE NULL
                          END AS recipient_name
                   FROM notification_counters c
                   LEFT JOIN tenants t  ON t.id = c.tenant_id
                   LEFT JOIN users u    ON u.id = c.recipient_id AND c.recipient_type = 'user'
                   LEFT JOIN entities e ON e.id = c.recipient_id AND c.recipient_type = 'entity'
                   LEFT JOIN tenants t_rec ON t_rec.id = c.recipient_id AND c.recipient_type = 'tenant'
                   WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND c.$col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY c.$orderBy $orderDir";

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
        $sql    = "SELECT COUNT(*) FROM notification_counters WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND $col = :$col";
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
            "SELECT c.*,
                    t.name AS tenant_name,
                    CASE 
                        WHEN c.recipient_type = 'user' THEN u.username
                        WHEN c.recipient_type = 'entity' THEN e.store_name
                        WHEN c.recipient_type = 'tenant' THEN t_rec.name
                        ELSE NULL
                    END AS recipient_name
             FROM notification_counters c
             LEFT JOIN tenants t  ON t.id = c.tenant_id
             LEFT JOIN users u    ON u.id = c.recipient_id AND c.recipient_type = 'user'
             LEFT JOIN entities e ON e.id = c.recipient_id AND c.recipient_type = 'entity'
             LEFT JOIN tenants t_rec ON t_rec.id = c.recipient_id AND c.recipient_type = 'tenant'
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByRecipient(string $recipientType, int $recipientId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    t.name AS tenant_name,
                    CASE 
                        WHEN c.recipient_type = 'user' THEN u.username
                        WHEN c.recipient_type = 'entity' THEN e.store_name
                        WHEN c.recipient_type = 'tenant' THEN t_rec.name
                        ELSE NULL
                    END AS recipient_name
             FROM notification_counters c
             LEFT JOIN tenants t  ON t.id = c.tenant_id
             LEFT JOIN users u    ON u.id = c.recipient_id AND c.recipient_type = 'user'
             LEFT JOIN entities e ON e.id = c.recipient_id AND c.recipient_type = 'entity'
             LEFT JOIN tenants t_rec ON t_rec.id = c.recipient_id AND c.recipient_type = 'tenant'
             WHERE c.tenant_id = :tenant_id AND c.recipient_type = :recipient_type AND c.recipient_id = :recipient_id"
        );
        $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':recipient_type' => $recipientType,
            ':recipient_id'   => $recipientId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);
        $allowed  = ['tenant_id', 'recipient_type', 'recipient_id', 'unread_count'];

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
            $sql  = "UPDATE notification_counters SET " . implode(', ', $sets) . " WHERE id = :id";
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

        if (!in_array('tenant_id', $cols)) {
            throw new InvalidArgumentException('Field "tenant_id" is required.');
        }
        if (!in_array('recipient_type', $cols)) {
            throw new InvalidArgumentException('Field "recipient_type" is required.');
        }
        if (!in_array('recipient_id', $cols)) {
            throw new InvalidArgumentException('Field "recipient_id" is required.');
        }

        $sql  = "INSERT INTO notification_counters (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function increment(int $id, int $amount = 1): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_counters SET unread_count = unread_count + :amount WHERE id = :id"
        );
        $stmt->execute([':amount' => $amount, ':id' => $id]);
    }

    public function reset(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_counters SET unread_count = 0 WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notification_counters WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
