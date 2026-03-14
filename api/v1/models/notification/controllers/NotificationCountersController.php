<?php
declare(strict_types=1);

final class NotificationCountersController
{
    private NotificationCountersService $service;

    public function __construct(NotificationCountersService $service)
    {
        $this->service = $service;
    }

    public function list(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        return $this->service->list($filters, $orderBy, $orderDir, $limit, $offset);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    public function delete(int $id): void
    {
        $this->service->delete($id);
    }

    public function increment(int $id, int $amount = 1): void
    {
        $this->service->increment($id, $amount);
    }

    public function reset(int $id): void
    {
        $this->service->reset($id);
    }
}
