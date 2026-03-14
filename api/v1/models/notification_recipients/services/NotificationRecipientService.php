<?php
declare(strict_types=1);

/**
 * NotificationRecipientService
 *
 * Business logic layer for Notification Recipients.
 *
 * Location: api/v1/models/notifications/notification_recipients/services/NotificationRecipientService.php
 */
final class NotificationRecipientService
{
    private NotificationRecipientRepositoryInterface $repo;
    private NotificationRecipientValidator $validator;

    public function __construct(
        NotificationRecipientRepositoryInterface $repo,
        NotificationRecipientValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function create(array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->create($data);
    }

    public function update(array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID required for update.');
        }
        $this->validator->validate($data, true);
        return $this->repo->update($data);
    }

    public function markRead(int $id): bool
    {
        return $this->repo->markRead($id);
    }

    public function markAllRead(array $data): int
    {
        $this->validator->validateMarkAllRead($data);
        return $this->repo->markAllRead($data['recipient_type'], (int) $data['recipient_id']);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
