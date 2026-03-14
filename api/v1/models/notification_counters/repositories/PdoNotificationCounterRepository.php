<?php
declare(strict_types=1);

/**
 * PdoNotificationCounterRepository
 *
 * PDO implementation of NotificationCounterRepositoryInterface.
 *
 * Location: api/v1/models/notifications/notification_counters/repositories/PdoNotificationCounterRepository.php
 */
final class PdoNotificationCounterRepository implements NotificationCounterRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'nc.id', 'nc.recipient_type', 'nc.recipient_id', 'nc.unread_count', 'nc.updated_at'
    ];

    private const FILTERABLE_COLUMNS = ['recipient_type', 'recipient_id'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'nc.id',
        string $orderDir = 'DESC'
    ): array {
        $sql    = "SELECT nc.* FROM notification_counters nc WHERE nc.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'nc.id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params[':offset'] = $offset;
        }

        return $this->fetchAll($sql, $params);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM notification_counters nc WHERE nc.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $sql  = "SELECT * FROM notification_counters WHERE tenant_id = :tenant_id AND id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getUnreadCount(int $tenantId, string $recipientType, int $recipientId): int
    {
        $sql  = "
            SELECT unread_count FROM notification_counters
            WHERE tenant_id = :tenant_id
              AND recipient_type = :recipient_type
              AND recipient_id   = :recipient_id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':recipient_type' => $recipientType,
            ':recipient_id'   => $recipientId,
        ]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function increment(int $tenantId, string $recipientType, int $recipientId, int $amount = 1): bool
    {
        $sql = "
            INSERT INTO notification_counters (tenant_id, recipient_type, recipient_id, unread_count)
            VALUES (:tenant_id, :recipient_type, :recipient_id, :amount)
            ON DUPLICATE KEY UPDATE unread_count = unread_count + :amount2
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':recipient_type' => $recipientType,
            ':recipient_id'   => $recipientId,
            ':amount'         => $amount,
            ':amount2'        => $amount,
        ]);
    }

    public function reset(int $tenantId, string $recipientType, int $recipientId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_counters
            SET unread_count = 0
            WHERE tenant_id = :tenant_id
              AND recipient_type = :recipient_type
              AND recipient_id   = :recipient_id
        ");
        return $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':recipient_type' => $recipientType,
            ':recipient_id'   => $recipientId,
        ]);
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_counters (tenant_id, recipient_type, recipient_id, unread_count)
            VALUES (:tenant_id, :recipient_type, :recipient_id, :unread_count)
        ");

        $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':recipient_type' => $data['recipient_type'],
            ':recipient_id'   => $data['recipient_id'],
            ':unread_count'   => $data['unread_count'] ?? 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Counter ID required for update.');
        }

        $allowedFields = ['unread_count'];
        $fields        = [];
        $params        = [':id' => $data['id'], ':tenant_id' => $tenantId];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]          = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $sql  = 'UPDATE notification_counters SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notification_counters WHERE id = :id AND tenant_id = :tenant_id");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND nc.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }
        return [$sql, $params];
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
