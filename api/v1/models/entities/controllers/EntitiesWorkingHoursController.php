<?php
declare(strict_types=1);

final class EntitiesWorkingHoursController
{
    private EntitiesWorkingHoursService $service;

    public function __construct(EntitiesWorkingHoursService $service)
    {
        $this->service = $service;
    }

    public function list(array $query): array
    {
        return $this->service->list($query);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(int $id, array $data): void
    {
        $this->service->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->service->delete($id);
    }

    public function getByEntity(int $entityId): array
    {
        return $this->service->getByEntity($entityId);
    }

    public function deleteByEntity(int $entityId): void
    {
        $this->service->deleteByEntity($entityId);
    }
}
