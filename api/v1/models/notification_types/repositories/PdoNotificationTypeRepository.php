<?php
declare(strict_types=1);

/**
 * PdoNotificationTypeRepository
 *
 * PDO implementation of NotificationTypeRepositoryInterface.
 *
 * Location: api/v1/models/notifications/notification_types/repositories/PdoNotificationTypeRepository.php
 */
final class PdoNotificationTypeRepository implements NotificationTypeRepositoryInterface
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'nt.id', 'nt.code', 'nt.name', 'nt.is_active', 'nt.created_at', 'nt.updated_at'
    ];

    private const FILTERABLE_COLUMNS = ['is_active'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'nt.id',
        string $orderDir = 'ASC'
    ): array {
        $sql    = "SELECT nt.* FROM notification_types nt WHERE 1=1";
        $params = [];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'nt.id';
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
        $sql    = "SELECT COUNT(*) FROM notification_types nt WHERE 1=1";
        $params = [];

        [$sql, $params] = $this->applyFilters($sql, $params, $filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notification_types WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notification_types WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_types (code, name, description, is_active, default_template)
            VALUES (:code, :name, :description, :is_active, :default_template)
        ");

        $stmt->execute([
            ':code'             => $data['code'],
            ':name'             => $data['name'],
            ':description'      => $data['description'] ?? null,
            ':is_active'        => $data['is_active'] ?? 1,
            ':default_template' => $data['default_template'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Type ID required for update.');
        }

        $allowedFields = ['code', 'name', 'description', 'is_active', 'default_template'];
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

        $sql  = 'UPDATE notification_types SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notification_types WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function applyFilters(string $sql, array $params, array $filters): array
    {
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND nt.{$col} = :{$col}";
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
