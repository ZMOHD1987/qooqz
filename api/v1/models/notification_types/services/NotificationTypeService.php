<?php
declare(strict_types=1);

/**
 * NotificationTypeService
 *
 * Business logic layer for Notification Types.
 *
 * Location: api/v1/models/notifications/notification_types/services/NotificationTypeService.php
 */
final class NotificationTypeService
{
    private NotificationTypeRepositoryInterface $repo;
    private NotificationTypeValidator $validator;

    public function __construct(
        NotificationTypeRepositoryInterface $repo,
        NotificationTypeValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function getByCode(string $code): ?array
    {
        return $this->repo->findByCode($code);
    }

    public function create(array $data): int
    {
        $this->validator->validate($data);

        if ($this->repo->findByCode($data['code']) !== null) {
            throw new InvalidArgumentException("A notification type with code '{$data['code']}' already exists.");
        }

        return $this->repo->create($data);
    }

    public function update(array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID required for update.');
        }
        $this->validator->validate($data, true);

        if (!empty($data['code'])) {
            $existing = $this->repo->findByCode($data['code']);
            if ($existing !== null && (int) $existing['id'] !== (int) $data['id']) {
                throw new InvalidArgumentException("A notification type with code '{$data['code']}' already exists.");
            }
        }

        return $this->repo->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
