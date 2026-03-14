<?php
declare(strict_types=1);

/**
 * QueueManager
 * Static wrapper for QueuesService to maintain backward compatibility.
 * All logic is delegated to the MVC service layer.
 */
final class QueueManager
{
    private static $service = null;

    private function __construct() {}
    private function __clone() {}

    private static function getService()
    {
        if (self::$service === null) {
            // Load dependencies
            $basePath = API_VERSION_PATH . '/models/queues';
            require_once $basePath . '/repositories/PdoQueuesRepository.php';
            require_once $basePath . '/validators/QueuesValidator.php';
            require_once $basePath . '/services/QueuesService.php';

            $pdo = DatabaseConnection::getConnection();
            $repository = new PdoQueuesRepository($pdo);
            self::$service = new QueuesService($repository);
        }
        return self::$service;
    }

    public static function push(
        string $queue,
        array  $payload,
        ?string $jobType = null,
        ?string $priority = 'normal',
        ?string $entityType = null,
        ?int    $entityId = null
    ): void {
        self::getService()->pushJob($queue, $payload, $jobType, $priority, $entityType, $entityId);
    }

    public static function pop(string $queue, int $maxAttempts = 5): ?array
    {
        $job = self::getService()->popJob($queue);
        if (!$job) return null;

        return [
            'id'          => (int) $job['id'],
            'queue'       => $job['queue'],
            'job_type'    => $job['job_type'],
            'entity_type' => $job['entity_type'],
            'entity_id'   => $job['entity_id'] ? (int)$job['entity_id'] : null,
            'payload'     => json_decode($job['payload'], true),
            'attempts'    => (int)$job['attempts']
        ];
    }

    public static function markDone(int $id): void
    {
        self::getService()->markJobDone($id);
    }

    public static function markFailed(int $id, string $reason, int $maxAttempts = 5): void
    {
        self::getService()->markJobFailed($id, $reason);
    }

    public static function getById(int $id): ?array
    {
        return self::getService()->getJob($id);
    }

    public static function list(
        int    $limit   = 25,
        int    $offset  = 0,
        array  $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $result = self::getService()->listJobs($limit, $offset, $filters);
        return [
            'items' => $result['items'],
            'meta'  => [
                'total'       => $result['total'],
                'limit'       => $limit,
                'offset'      => $offset,
                'total_pages' => $limit > 0 ? (int) ceil($result['total'] / $limit) : 1,
            ],
        ];
    }

    public static function stats(): array
    {
        return self::getService()->getStats();
    }

    public static function retry(int $id): bool
    {
        return self::getService()->retryJob($id);
    }

    public static function delete(int $id): bool
    {
        return self::getService()->deleteJob($id);
    }

    public static function archiveDone(): int
    {
        return self::getService()->archiveJobs();
    }

    public static function purge(string $status = 'done', int $olderThanDays = 30): int
    {
        return self::getService()->purgeArchives($olderThanDays);
    }

    public static function getQueueNames(): array
    {
        return self::getService()->getQueueNames();
    }

    public static function work(string $queue, callable $handler, int $sleep = 1, int $maxAttempts = 5): void
    {
        $shouldStop = false;

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $stopHandler = function () use (&$shouldStop) {
                $shouldStop = true;
            };
            pcntl_signal(SIGTERM, $stopHandler);
            pcntl_signal(SIGINT, $stopHandler);
        }

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
                self::markFailed($job['id'], $e->getMessage());
            }
        }
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            0 => 'pending',
            1 => 'working',
            2 => 'done',
            3 => 'failed',
            default => 'unknown',
        };
    }
}
