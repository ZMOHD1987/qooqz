<?php
declare(strict_types=1);

final class QueueManager
{
    private const STATUS_PENDING  = 0;
    private const STATUS_WORKING  = 1;
    private const STATUS_DONE     = 2;
    private const STATUS_FAILED   = 3;

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
     * ========================= */
    public static function pop(string $queue): ?array
    {
        $pdo = DatabaseConnection::getConnection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, payload
                FROM queues
                WHERE queue = :queue
                  AND status = :status
                ORDER BY created_at ASC
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':queue'  => $queue,
                ':status' => self::STATUS_PENDING,
            ]);

            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $pdo->commit();
                return null;
            }

            $pdo->prepare("
                UPDATE queues
                SET status = :working, attempts = attempts + 1, updated_at = NOW()
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
     * Mark job failed
     * ========================= */
    public static function markFailed(int $id, string $reason): void
    {
        $pdo = DatabaseConnection::getConnection();

        $pdo->prepare("
            UPDATE queues
            SET status = :failed, error = :error, updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':failed' => self::STATUS_FAILED,
            ':error'  => $reason,
            ':id'     => $id,
        ]);

        Logger::error("Queue job {$id} failed: {$reason}");
    }

    /* =========================
     * Worker loop
     * ========================= */
    public static function work(string $queue, callable $handler, int $sleep = 1): void
    {
        while (true) {
            $job = self::pop($queue);

            if (!$job) {
                sleep($sleep);
                continue;
            }

            try {
                $handler($job['payload']);
                self::markDone($job['id']);
            } catch (Throwable $e) {
                self::markFailed($job['id'], $e->getMessage());
            }
        }
    }
}
