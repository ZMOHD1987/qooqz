<?php
declare(strict_types=1);

/**
 * PdoNotificationRecipientRepository
 *
 * PDO implementation of NotificationRecipientRepositoryInterface.
 *
 * Location: api/v1/models/notifications/notification_recipients/repositories/PdoNotificationRecipientRepository.php
 */
final class PdoNotificationRecipientRepository implements NotificationRecipientRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'nr.id', 'nr.notification_id', 'nr.recipient_type',
        'nr.recipient_id', 'nr.is_read', 'nr.created_at', 'nr.read_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'notification_id', 'recipient_type', 'recipient_id', 'is_read'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'nr.id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "
            SELECT nr.*, n.title, n.message, n.priority, n.sent_at
            FROM notification_recipients nr
            LEFT JOIN notifications n ON nr.notification_id = n.id
            WHERE 1=1
        ";

        $params = [];
        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'nr.id';
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

    public function count(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM notification_recipients nr WHERE 1=1";
        $params = [];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $sql = "
            SELECT nr.*, n.title, n.message, n.priority, n.sent_at
            FROM notification_recipients nr
            LEFT JOIN notifications n ON nr.notification_id = n.id
            WHERE nr.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_recipients
                (notification_id, recipient_type, recipient_id, is_read, read_at)
            VALUES
                (:notification_id, :recipient_type, :recipient_id, :is_read, :read_at)
        ");

        $stmt->execute([
            ':notification_id' => $data['notification_id'],
            ':recipient_type'  => $data['recipient_type'],
            ':recipient_id'    => $data['recipient_id'],
            ':is_read'         => $data['is_read'] ?? 0,
            ':read_at'         => $data['read_at'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Recipient ID required for update.');
        }

        $allowedFields = ['is_read', 'read_at'];
        $fields        = [];
        $params        = [':id' => $data['id']];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]          = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $sql  = 'UPDATE notification_recipients SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function markRead(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_recipients
            SET is_read = 1, read_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }

    public function markAllRead(string $recipientType, int $recipientId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_recipients
            SET is_read = 1, read_at = NOW()
            WHERE recipient_type = :recipient_type
              AND recipient_id   = :recipient_id
              AND is_read        = 0
        ");
        $stmt->execute([
            ':recipient_type' => $recipientType,
            ':recipient_id'   => $recipientId,
        ]);
        return $stmt->rowCount();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notification_recipients WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND nr.{$col} = :{$col}";
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
