<?php
declare(strict_types=1);

final class JobAlertsController
{
    private JobAlertsService $service;

    public function __construct(JobAlertsService $service)
    {
        $this->service = $service;
    }

    /**
     * List alerts with filters, ordering, and pagination
     */
    public function list(
        int $userId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->service->list(
            $userId,
            $limit,
            $offset,
            $filters,
            $orderBy,
            $orderDir
        );
        $total = $this->service->count($userId, $filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get a single alert by ID
     */
    public function get(int $userId, int $id): ?array
    {
        return $this->service->get($userId, $id);
    }

    /**
     * Create a new alert
     */
    public function create(int $userId, array $data): int
    {
        return $this->service->create($userId, $data);
    }

    /**
     * Update an existing alert
     */
    public function update(int $userId, array $data): int
    {
        return $this->service->update($userId, $data);
    }

    /**
     * Delete an alert
     */
    public function delete(int $userId, int $id): bool
    {
        return $this->service->delete($userId, $id);
    }

    /**
     * Toggle alert active status
     */
    public function toggleActive(int $userId, int $id): bool
    {
        return $this->service->toggleActive($userId, $id);
    }

    /**
     * Get alerts due for sending
     */
    public function getDueAlerts(string $frequency = 'daily'): array
    {
        return $this->service->getDueAlerts($frequency);
    }

    /**
     * Update last sent timestamp
     */
    public function updateLastSent(int $alertId): bool
    {
        return $this->service->updateLastSent($alertId);
    }

    /**
     * Get user statistics
     */
    public function getStatistics(int $userId): array
    {
        return $this->service->getStatistics($userId);
    }

    /**
     * Batch update alert status
     */
    public function batchUpdateStatus(int $userId, array $alertIds, bool $isActive): array
    {
        $updated = $this->service->batchUpdateStatus($userId, $alertIds, $isActive);
        return [
            'updated' => $updated,
            'total' => count($alertIds)
        ];
    }

    /**
     * Check if user can create more alerts
     */
    public function canCreateAlert(int $userId, int $maxAlerts = 10): bool
    {
        return $this->service->canCreateAlert($userId, $maxAlerts);
    }

    /**
     * Get active alerts count
     */
    public function getActiveAlertsCount(int $userId): int
    {
        return $this->service->getActiveAlertsCount($userId);
    }
}
