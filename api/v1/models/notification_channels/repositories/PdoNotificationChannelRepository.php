<?php
declare(strict_types=1);

/**
 * PdoNotificationChannelRepository
 *
 * PDO implementation of NotificationChannelRepositoryInterface.
 *
 * Location: api/v1/models/notifications/notification_channels/repositories/PdoNotificationChannelRepository.php
 */
final class PdoNotificationChannelRepository implements NotificationChannelRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = ['nc.id', 'nc.code', 'nc.name', 'nc.is_active', 'nc.created_at'];
    private const FILTERABLE_COLUMNS = ['is_active'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'nc.id',
        string $orderDir = 'ASC'
    ): array {
        $sql    = "SELECT nc.* FROM notification_channels nc WHERE 1=1";
        $params = [];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'nc.id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
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
        $sql    = "SELECT COUNT(*) FROM notification_channels nc WHERE 1=1";
        $params = [];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notification_channels WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notification_channels WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_channels (code, name, is_active)
            VALUES (:code, :name, :is_active)
        ");

        $stmt->execute([
            ':code'      => $data['code'],
            ':name'      => $data['name'],
            ':is_active' => $data['is_active'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Channel ID required for update.');
        }

        $allowedFields = ['code', 'name', 'is_active'];
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

        $sql  = 'UPDATE notification_channels SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notification_channels WHERE id = :id");
        return $stmt->execute([':id' => $id]);
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
