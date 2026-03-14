<?php
declare(strict_types=1);

final class NotificationCountersService
{
    private PdoNotificationCountersRepository $repo;
    private NotificationCountersValidator     $validator;

    public function __construct(
        PdoNotificationCountersRepository $repo,
        NotificationCountersValidator     $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC',
        ?int   $limit    = null,
        ?int   $offset   = null
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Notification counter not found.');
        }
        return $row;
    }

    public function create(array $data): int
    {
        $this->validator->validate($data, false);
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        $this->validator->validate($data, true);

        $existing = $this->repo->find((int)$data['id']);
        if (!$existing) {
            throw new RuntimeException('Notification counter not found.');
        }

        return $this->repo->save($data);
    }

    public function delete(int $id): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Notification counter not found.');
        }
        $this->repo->delete($id);
    }

    public function increment(int $id, int $amount = 1): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Notification counter not found.');
        }
        $this->repo->increment($id, $amount);
    }

    public function reset(int $id): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Notification counter not found.');
        }
        $this->repo->reset($id);
    }
}
