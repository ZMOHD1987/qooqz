<?php
declare(strict_types=1);

/**
 * NotificationCounterService
 *
 * Business logic layer for Notification Counters.
 *
 * Location: api/v1/models/notifications/notification_counters/services/NotificationCounterService.php
 */
final class NotificationCounterService
{
    private NotificationCounterRepositoryInterface $repo;
    private NotificationCounterValidator $validator;

    public function __construct(
        NotificationCounterRepositoryInterface $repo,
        NotificationCounterValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        $items = $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $id): ?array
    {
        return $this->repo->find($tenantId, $id);
    }

    public function getUnreadCount(int $tenantId, array $data): int
    {
        $this->validator->validateRecipientPayload($data);
        return $this->repo->getUnreadCount($tenantId, $data['recipient_type'], (int) $data['recipient_id']);
    }

    public function increment(int $tenantId, array $data): bool
    {
        $this->validator->validateRecipientPayload($data);
        $amount = isset($data['amount']) ? (int) $data['amount'] : 1;
        return $this->repo->increment($tenantId, $data['recipient_type'], (int) $data['recipient_id'], $amount);
    }

    public function reset(int $tenantId, array $data): bool
    {
        $this->validator->validateRecipientPayload($data);
        return $this->repo->reset($tenantId, $data['recipient_type'], (int) $data['recipient_id']);
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID required for update.');
        }
        $this->validator->validate($data, true);
        return $this->repo->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }
}
