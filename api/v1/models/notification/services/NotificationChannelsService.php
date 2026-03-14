<?php
declare(strict_types=1);

final class NotificationChannelsService
{
    private PdoNotificationChannelsRepository $repo;
    private NotificationChannelsValidator     $validator;

    public function __construct(
        PdoNotificationChannelsRepository $repo,
        NotificationChannelsValidator     $validator
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
            throw new RuntimeException('Notification channel not found.');
        }
        return $row;
    }

    public function create(array $data): int
    {
        $this->validator->validate($data, false);

        $existing = $this->repo->findByCode($data['code']);
        if ($existing !== null) {
            throw new InvalidArgumentException('Channel code already exists.');
        }

        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        $this->validator->validate($data, true);

        $existing = $this->repo->find((int)$data['id']);
        if (!$existing) {
            throw new RuntimeException('Notification channel not found.');
        }

        if (isset($data['code']) && $data['code'] !== $existing['code']) {
            $byCode = $this->repo->findByCode($data['code']);
            if ($byCode !== null && (int)$byCode['id'] !== (int)$data['id']) {
                throw new InvalidArgumentException('Channel code already exists.');
            }
        }

        return $this->repo->save($data);
    }

    public function delete(int $id): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Notification channel not found.');
        }
        $this->repo->delete($id);
    }
}
