<?php
declare(strict_types=1);

/**
 * NotificationChannelController
 *
 * Location: api/v1/models/notifications/notification_channels/controllers/NotificationChannelController.php
 */
final class NotificationChannelController
{
    private NotificationChannelService $service;

    public function __construct(NotificationChannelService $service)
    {
        $this->service = $service;
    }

    public function list(?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    public function getByCode(string $code): ?array
    {
        return $this->service->getByCode($code);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(array $data): bool
    {
        return $this->service->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
