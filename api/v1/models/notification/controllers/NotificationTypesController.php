<?php
declare(strict_types=1);

final class NotificationTypesController
{
    private NotificationTypesService $service;

    public function __construct(NotificationTypesService $service)
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
}