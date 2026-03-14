<?php
declare(strict_types=1);

/**
 * NotificationCounterController
 *
 * Location: api/v1/models/notifications/notification_counters/controllers/NotificationCounterController.php
 */
final class NotificationCounterController
{
    private NotificationCounterService $service;

    public function __construct(NotificationCounterService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        return $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $tenantId, int $id): ?array
    {
        return $this->service->get($tenantId, $id);
    }

    public function getUnreadCount(int $tenantId, array $data): int
    {
        return $this->service->getUnreadCount($tenantId, $data);
    }

    public function increment(int $tenantId, array $data): bool
    {
        return $this->service->increment($tenantId, $data);
    }

    public function reset(int $tenantId, array $data): bool
    {
        return $this->service->reset($tenantId, $data);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }
}
