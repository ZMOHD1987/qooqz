<?php
declare(strict_types=1);

final class PdoQueuesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'queue', 'status', 'attempts', 'created_at', 'updated_at', 
        'processed_at', 'available_at', 'priority', 'job_type'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function push(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO queues (
                queue, job_type, priority, entity_type, entity_id, 
                payload, status, attempts, available_at, created_at
            ) VALUES (
                :queue, :job_type, :priority, :entity_type, :entity_id, 
                :payload, :status, 0, :available_at, NOW()
            )
        ");

        $stmt->execute([
            ':queue'        => $data['queue'],
            ':job_type'     => $data['job_type'] ?? null,
            ':priority'     => $data['priority'] ?? 'normal',
            ':entity_type'  => $data['entity_type'] ?? null,
            ':entity_id'    => $data['entity_id'] ?? null,
            ':payload'      => json_encode($data['payload'], JSON_UNESCAPED_UNICODE),
            ':status'       => $data['status'] ?? 0,
            ':available_at' => $data['available_at'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM queues WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function all(
        int    $limit   = 25,
        int    $offset  = 0,
        array  $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $where  = [];
        $params = [];

        if (!empty($filters['queue'])) {
            $where[] = 'queue = :queue';
            $params[':queue'] = $filters['queue'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'status = :status';
            $params[':status'] = (int)$filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'priority = :priority';
            $params[':priority'] = $filters['priority'];
        }
        if (!empty($filters['job_type'])) {
            $where[] = 'job_type = :job_type';
            $params[':job_type'] = $filters['job_type'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = (int)$filters['entity_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(queue LIKE :search OR error LIKE :search2 OR payload LIKE :search3)';
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        // Count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM queues {$whereSQL}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Rows
        $sql = "SELECT * FROM queues {$whereSQL} ORDER BY {$orderBy} {$orderDir} LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total
        ];
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $val;
        }

        if (empty($fields)) return false;

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE queues SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM queues WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*)                                        AS total,
                SUM(status = 0)                                 AS pending,
                SUM(status = 1)                                 AS working,
                SUM(status = 2)                                 AS done,
                SUM(status = 3)                                 AS failed,
                COUNT(DISTINCT queue)                            AS queues
            FROM queues
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getQueueNames(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT queue FROM queues ORDER BY queue ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function pop(string $queue, int $maxAttempts): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, queue, job_type, entity_type, entity_id, payload, attempts, created_at
            FROM queues
            WHERE queue = :queue
              AND status = 0
              AND attempts < :max_attempts
              AND (available_at IS NULL OR available_at <= NOW())
            ORDER BY created_at ASC
            LIMIT 1
            FOR UPDATE SKIP LOCKED
        ");

        $stmt->execute([
            ':queue'        => $queue,
            ':max_attempts' => $maxAttempts,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function archiveOld(int $olderThanSeconds): int
    {
        // Use COALESCE(processed_at, updated_at) to avoid nulls
        // Calculate execution_time_ms if possible
        $sql = "
            INSERT INTO queues_archive (
                queue, job_type, priority, entity_type, entity_id, 
                payload, status, attempts, error, 
                created_at, available_at, updated_at, processed_at, execution_time_ms
            )
            SELECT 
                queue, job_type, priority, entity_type, entity_id, 
                payload, status, attempts, error, 
                created_at, available_at, updated_at, processed_at,
                TIMESTAMPDIFF(MICROSECOND, processed_at, updated_at) / 1000
            FROM queues
            WHERE (status = 2 OR (status = 3 AND attempts >= 5))
              AND updated_at < DATE_SUB(NOW(), INTERVAL :sec SECOND)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sec' => $olderThanSeconds]);
        $count = $stmt->rowCount();

        if ($count > 0) {
            $this->pdo->prepare("
                DELETE FROM queues 
                WHERE (status = 2 OR (status = 3 AND attempts >= 5))
                  AND updated_at < DATE_SUB(NOW(), INTERVAL :sec SECOND)
            ")->execute([':sec' => $olderThanSeconds]);
        }

        return $count;
    }

    public function purgeArchive(int $days): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM queues_archive WHERE updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $stmt->execute([':days' => $days]);
        return $stmt->rowCount();
    }
}
