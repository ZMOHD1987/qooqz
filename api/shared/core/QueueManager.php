<?php
declare(strict_types=1);

final class QueueManager
{
    private const STATUS_PENDING  = 0;
    private const STATUS_WORKING  = 1;
    private const STATUS_DONE     = 2;
    private const STATUS_FAILED   = 3;

    /** Max retry attempts before moving to dead letter */
    private const MAX_ATTEMPTS = 5;

    /** Base backoff seconds (exponential: base * 2^attempts) */
    private const BACKOFF_BASE = 5;

    private function __construct() {}
    private function __clone() {}

    /* =========================
     * Push job
     * ========================= */
    public static function push(string $queue, array $payload): void
    {
        $pdo = DatabaseConnection::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO queues (queue, payload, status, attempts, created_at)
            VALUES (:queue, :payload, :status, 0, NOW())
        ");

        $stmt->execute([
            ':queue'   => $queue,
            ':payload'=> json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':status' => self::STATUS_PENDING,
        ]);

        if (class_exists('EventDispatcher')) {
            EventDispatcher::dispatch('queue.pushed', [
                'queue' => $queue,
                'payload' => $payload,
            ]);
        }
    }

    /* =========================
     * Fetch next job (LOCKED)
     * Uses SKIP LOCKED for parallel worker support
     * Filters by max_attempts to avoid re-processing dead jobs
     * Sets processed_at for performance tracking
     * ========================= */
    public static function pop(string $queue, int $maxAttempts = self::MAX_ATTEMPTS): ?array
    {
        $pdo = DatabaseConnection::getConnection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, payload, attempts
                FROM queues
                WHERE queue = :queue
                  AND status = :status
                  AND attempts < :max_attempts
                  AND (available_at IS NULL OR available_at <= NOW())
                ORDER BY created_at ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
            ");

            $stmt->execute([
                ':queue'        => $queue,
                ':status'       => self::STATUS_PENDING,
                ':max_attempts' => $maxAttempts,
            ]);

            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $pdo->commit();
                return null;
            }

            $pdo->prepare("
                UPDATE queues
                SET status = :working,
                    attempts = attempts + 1,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                ':working' => self::STATUS_WORKING,
                ':id'      => $job['id'],
            ]);

            $pdo->commit();

            return [
                'id'      => (int) $job['id'],
                'payload' => json_decode($job['payload'], true),
            ];

        } catch (Throwable $e) {
            $pdo->rollBack();
            Logger::error('Queue pop failed: ' . $e->getMessage());
            return null;
        }
    }

    /* =========================
     * Mark job done
     * ========================= */
    public static function markDone(int $id): void
    {
        $pdo = DatabaseConnection::getConnection();

        $pdo->prepare("
            UPDATE queues
            SET status = :done, updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':done' => self::STATUS_DONE,
            ':id'   => $id,
        ]);
    }

    /* =========================
     * Mark job failed with retry/backoff
     * If attempts < MAX_ATTEMPTS: reschedule with exponential backoff
     * If attempts >= MAX_ATTEMPTS: mark as FAILED (dead letter)
     * ========================= */
    public static function markFailed(int $id, string $reason, int $maxAttempts = self::MAX_ATTEMPTS): void
    {
        $pdo = DatabaseConnection::getConnection();

        // Check current attempts
        $stmt = $pdo->prepare("SELECT attempts FROM queues WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $attempts = $row ? (int) $row['attempts'] : 0;

        if ($attempts < $maxAttempts) {
            // Retry with exponential backoff: 5s, 10s, 20s, 40s, 80s...
            $backoffSeconds = self::BACKOFF_BASE * (int) pow(2, max($attempts, 1) - 1);
            $pdo->prepare("
                UPDATE queues
                SET status = :pending,
                    error = :error,
                    available_at = DATE_ADD(NOW(), INTERVAL :backoff SECOND),
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                ':pending' => self::STATUS_PENDING,
                ':error'   => $reason,
                ':backoff' => $backoffSeconds,
                ':id'      => $id,
            ]);
            Logger::warning("Queue job {$id} attempt {$attempts}/{$maxAttempts} failed, retrying in {$backoffSeconds}s: {$reason}");
        } else {
            // Max attempts exceeded → dead letter (final FAILED)
            $pdo->prepare("
                UPDATE queues
                SET status = :failed, error = :error, updated_at = NOW()
                WHERE id = :id
            ")->execute([
                ':failed' => self::STATUS_FAILED,
                ':error'  => "[DEAD LETTER] Max attempts ({$maxAttempts}) exceeded. Last error: {$reason}",
                ':id'     => $id,
            ]);
            Logger::error("Queue job {$id} moved to dead letter after {$maxAttempts} attempts: {$reason}");
        }
    }

    /* =========================
     * Worker loop with graceful shutdown
     * Handles SIGTERM/SIGINT to finish current job before stopping
     * ========================= */
    public static function work(string $queue, callable $handler, int $sleep = 1, int $maxAttempts = self::MAX_ATTEMPTS): void
    {
        $shouldStop = false;

        // Install signal handlers for graceful shutdown
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $stopHandler = function () use (&$shouldStop) {
                $shouldStop = true;
                Logger::info('Queue worker received shutdown signal, finishing current job...');
            };
            pcntl_signal(SIGTERM, $stopHandler);
            pcntl_signal(SIGINT, $stopHandler);
        }

        Logger::info("Queue worker started for '{$queue}' (max_attempts={$maxAttempts})");

        while (!$shouldStop) {
            $job = self::pop($queue, $maxAttempts);

            if (!$job) {
                sleep($sleep);
                continue;
            }

            try {
                $handler($job['payload']);
                self::markDone($job['id']);
            } catch (Throwable $e) {
                self::markFailed($job['id'], $e->getMessage(), $maxAttempts);
            }
        }

        Logger::info("Queue worker for '{$queue}' shut down gracefully.");
    }

    /* =========================
     * Get job by ID
     * ========================= */
    public static function getById(int $id): ?array
    {
        $pdo  = DatabaseConnection::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM queues WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* =========================
     * List jobs with filters
     * ========================= */
    public static function list(
        int    $limit   = 25,
        int    $offset  = 0,
        array  $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $pdo    = DatabaseConnection::getConnection();
        $where  = [];
        $params = [];

        if (isset($filters['queue']) && $filters['queue'] !== '') {
            $where[]           = 'queue = :queue';
            $params[':queue']  = $filters['queue'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[]            = 'status = :status';
            $params[':status']  = (int) $filters['status'];
        }
        if (isset($filters['search']) && $filters['search'] !== '') {
            $where[]            = '(queue LIKE :search OR error LIKE :search2)';
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowed  = ['id', 'queue', 'status', 'attempts', 'created_at', 'updated_at', 'processed_at', 'available_at'];
        $orderBy  = in_array($orderBy, $allowed, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM queues {$whereSQL}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Rows
        $sql  = "SELECT * FROM queues {$whereSQL} ORDER BY {$orderBy} {$orderDir} LIMIT :lim OFFSET :off";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
            ],
        ];
    }

    /* =========================
     * Queue statistics
     * ========================= */
    public static function stats(): array
    {
        $pdo  = DatabaseConnection::getConnection();
        $stmt = $pdo->query("
            SELECT
                COUNT(*)                                        AS total,
                SUM(status = 0)                                 AS pending,
                SUM(status = 1)                                 AS working,
                SUM(status = 2)                                 AS done,
                SUM(status = 3)                                 AS failed,
                COUNT(DISTINCT queue)                            AS queues
            FROM queues
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total'   => (int) ($row['total']   ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'working' => (int) ($row['working'] ?? 0),
            'done'    => (int) ($row['done']    ?? 0),
            'failed'  => (int) ($row['failed']  ?? 0),
            'queues'  => (int) ($row['queues']  ?? 0),
        ];
    }

    /* =========================
     * Retry a failed job
     * ========================= */
    public static function retry(int $id): bool
    {
        $pdo  = DatabaseConnection::getConnection();
        $stmt = $pdo->prepare("
            UPDATE queues
            SET status = :pending, error = NULL, updated_at = NOW()
            WHERE id = :id AND status = :failed
        ");
        $stmt->execute([
            ':pending' => self::STATUS_PENDING,
            ':failed'  => self::STATUS_FAILED,
            ':id'      => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /* =========================
     * Delete a job
     * ========================= */
    public static function delete(int $id): bool
    {
        $pdo  = DatabaseConnection::getConnection();
        $stmt = $pdo->prepare("DELETE FROM queues WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /* =========================
     * Archive done jobs (with safety window)
     * Only archives jobs updated more than 10 seconds ago
     * to prevent race conditions with active workers
     * ========================= */
    public static function archiveDone(): int
    {
        $pdo = DatabaseConnection::getConnection();
        $pdo->beginTransaction();
        try {
            $archiveStmt = $pdo->prepare("
                INSERT INTO queues_archive (queue, payload, status, attempts, error, created_at, available_at, updated_at, processed_at)
                SELECT queue, payload, status, attempts, error, created_at, available_at, updated_at, COALESCE(processed_at, updated_at)
                FROM queues
                WHERE status = :done_status
                  AND updated_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)
            ");
            $archiveStmt->execute([':done_status' => self::STATUS_DONE]);
            $stmt = $pdo->prepare("
                DELETE FROM queues
                WHERE status = :done
                  AND updated_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)
            ");
            $stmt->execute([':done' => self::STATUS_DONE]);
            $count = $stmt->rowCount();
            $pdo->commit();
            return $count;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* =========================
     * Purge old jobs
     * ========================= */
    public static function purge(string $status = 'done', int $olderThanDays = 30): int
    {
        $statusMap = [
            'pending' => self::STATUS_PENDING,
            'working' => self::STATUS_WORKING,
            'done'    => self::STATUS_DONE,
            'failed'  => self::STATUS_FAILED,
        ];
        $statusVal = $statusMap[$status] ?? self::STATUS_DONE;

        $pdo  = DatabaseConnection::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM queues
            WHERE status = :status AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->bindValue(':status', $statusVal, PDO::PARAM_INT);
        $stmt->bindValue(':days',   $olderThanDays, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /* =========================
     * Get distinct queue names
     * ========================= */
    public static function getQueueNames(): array
    {
        $pdo  = DatabaseConnection::getConnection();
        $stmt = $pdo->query("SELECT DISTINCT queue FROM queues ORDER BY queue ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /* =========================
     * Detect and recover stuck jobs
     * Jobs stuck in WORKING status for more than $minutes
     * are reset to PENDING for retry
     * ========================= */
    public static function detectStuck(int $minutes = 30): int
    {
        $pdo  = DatabaseConnection::getConnection();
        $stmt = $pdo->prepare("
            UPDATE queues
            SET status = :pending,
                error = :stuck_msg,
                updated_at = NOW()
            WHERE status = :working
              AND processed_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stuckMsg = "[STUCK] Reset after {$minutes} minutes of no response";
        $stmt->bindValue(':pending',   self::STATUS_PENDING, PDO::PARAM_INT);
        $stmt->bindValue(':stuck_msg', $stuckMsg);
        $stmt->bindValue(':working',   self::STATUS_WORKING, PDO::PARAM_INT);
        $stmt->bindValue(':minutes',   $minutes, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) {
            Logger::warning("Queue: reset {$count} stuck jobs (working > {$minutes} min)");
        }
        return $count;
    }

    /* =========================
     * Status label helpers
     * ========================= */
    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'pending',
            self::STATUS_WORKING => 'working',
            self::STATUS_DONE    => 'done',
            self::STATUS_FAILED  => 'failed',
            default              => 'unknown',
        };
    }

    public static function statusCode(string $label): int
    {
        return match (strtolower($label)) {
            'pending' => self::STATUS_PENDING,
            'working' => self::STATUS_WORKING,
            'done'    => self::STATUS_DONE,
            'failed'  => self::STATUS_FAILED,
            default   => -1,
        };
    }
}
