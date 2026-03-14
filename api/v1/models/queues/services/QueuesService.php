<?php
declare(strict_types=1);

final class QueuesService
{
    private PdoQueuesRepository $repository;
    private const MAX_ATTEMPTS = 5;
    private const BACKOFF_BASE = 5;

    public function __construct(PdoQueuesRepository $repository)
    {
        $this->repository = $repository;
    }

    public function pushJob(
        string $queue,
        array  $payload,
        ?string $jobType = null,
        ?string $priority = 'normal',
        ?string $entityType = null,
        ?int    $entityId = null,
        ?string $availableAt = null
    ): int {
        $data = [
            'queue'        => $queue,
            'payload'      => $payload,
            'job_type'     => $jobType,
            'priority'     => $priority,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'available_at' => $availableAt,
            'status'       => 0, // Pending
        ];

        $errors = QueuesValidator::validatePush($data);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors));
        }

        return $this->repository->push($data);
    }

    public function listJobs(int $limit = 25, int $offset = 0, array $filters = []): array
    {
        $errors = QueuesValidator::validateFilters($filters);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors));
        }

        return $this->repository->all($limit, $offset, $filters);
    }

    public function getJob(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function deleteJob(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function retryJob(int $id): bool
    {
        $job = $this->repository->find($id);
        if (!$job || (int)$job['status'] !== 3) {
            return false;
        }

        return $this->repository->update($id, [
            'status'     => 0, // Pending
            'error'      => null,
            'attempts'   => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    public function getQueueNames(): array
    {
        return $this->repository->getQueueNames();
    }

    public function popJob(string $queue): ?array
    {
        return $this->repository->pop($queue, self::MAX_ATTEMPTS);
    }

    public function markJobDone(int $id): void
    {
        $this->repository->update($id, ['status' => 2]); // Done
    }

    public function markJobFailed(int $id, string $reason): void
    {
        $job = $this->repository->find($id);
        if (!$job) return;

        $attempts = (int)$job['attempts'] + 1;

        if ($attempts < self::MAX_ATTEMPTS) {
            $backoff = self::BACKOFF_BASE * (int)pow(2, max($attempts, 1) - 1);
            $availableAt = date('Y-m-d H:i:s', time() + $backoff);

            $this->repository->update($id, [
                'status'       => 0, // Pending
                'error'        => $reason,
                'attempts'     => $attempts,
                'available_at' => $availableAt
            ]);
        } else {
            $this->repository->update($id, [
                'status' => 3, // Failed
                'error'  => "[DEAD LETTER] Max attempts exceeded. Last error: " . $reason,
                'attempts' => $attempts
            ]);
        }
    }

    public function archiveJobs(int $olderThanSeconds = 10): int
    {
        return $this->repository->archiveOld($olderThanSeconds);
    }

    public function purgeArchives(int $days = 30): int
    {
        return $this->repository->purgeArchive($days);
    }
}
