<?php
declare(strict_types=1);

/**
 * NotificationRecipientController
 *
 * Location: api/v1/models/notifications/notification_recipients/controllers/NotificationRecipientController.php
 */
final class NotificationRecipientController
{
    private NotificationRecipientService $service;

    public function __construct(NotificationRecipientService $service)
    {
        $this->service = $service;
    }

    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(array $data): bool
    {
        return $this->service->update($data);
    }

    public function markRead(int $id): bool
    {
        return $this->service->markRead($id);
    }

    public function markAllRead(array $data): int
    {
        return $this->service->markAllRead($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
