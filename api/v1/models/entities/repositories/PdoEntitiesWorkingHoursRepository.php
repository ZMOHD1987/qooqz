<?php
declare(strict_types=1);

final class PdoEntitiesWorkingHoursRepository
{
    private PDO $pdo;

    /**
     * الأعمدة المسموح استخدامها في ORDER BY فقط
     */
    private const ALLOWED_ORDER_BY = [
        'id',
        'entity_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
        'created_at',
        'updated_at',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================
    // List + Filters + Pagination (Production Ready)
    // =========================================================
    public function all(
        array $filters = [],
        int $limit = 25,
        int $offset = 0,
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $where   = [];
        $params  = [];

        // -------------------------------
        // Filters
        // -------------------------------
        if (isset($filters['id'])) {
            $where[] = 'id = :id';
            $params[':id'] = (int)$filters['id'];
        }

        if (isset($filters['entity_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        }

        if (isset($filters['day_of_week'])) {
            $where[] = 'day_of_week = :day_of_week';
            $params[':day_of_week'] = (int)$filters['day_of_week'];
        }

        if (isset($filters['is_open'])) {
            $where[] = 'is_open = :is_open';
            $params[':is_open'] = (int)$filters['is_open'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // -------------------------------
        // ORDER BY protection
        // -------------------------------
        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        // -------------------------------
        // Main query
        // -------------------------------
        $sql = "
            SELECT
                id,
                entity_id,
                day_of_week,
                is_open,
                open_time,
                close_time,
                created_at,
                updated_at
            FROM entities_working_hours
            {$whereSql}
            ORDER BY {$orderBy} {$orderDir}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // -------------------------------
        // Count query (accurate & fast)
        // -------------------------------
        $countSql = "
            SELECT COUNT(*)
            FROM entities_working_hours
            {$whereSql}
        ";

        $countStmt = $this->pdo->prepare($countSql);

        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_INT);
        }

        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    // =========================================================
    // Find by ID
    // =========================================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM entities_working_hours WHERE id = :id LIMIT 1"
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================
    // Create
    // =========================================================
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO entities_working_hours
                (entity_id, day_of_week, is_open, open_time, close_time)
            VALUES
                (:entity_id, :day_of_week, :is_open, :open_time, :close_time)
        ");

        $stmt->bindValue(':entity_id',  (int)$data['entity_id'],  PDO::PARAM_INT);
        $stmt->bindValue(':day_of_week',(int)$data['day_of_week'],PDO::PARAM_INT);
        $stmt->bindValue(':is_open',    (int)($data['is_open'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':open_time',  $data['open_time']  ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':close_time', $data['close_time'] ?? null, PDO::PARAM_STR);

        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }

    // =========================================================
    // Update (partial update safe)
    // =========================================================
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $sets   = [];
        $params = [':id' => $id];

        foreach ($data as $field => $value) {
            $sets[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $sql = "
            UPDATE entities_working_hours
            SET " . implode(', ', $sets) . ",
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(
                $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        return $stmt->execute();
    }

    // =========================================================
    // Delete
    // =========================================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entities_working_hours WHERE id = :id"
        );

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // =========================================================
    // Delete by Entity
    // =========================================================
    public function deleteByEntity(int $entityId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entities_working_hours WHERE entity_id = :entity_id"
        );

        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
