<?php
declare(strict_types=1);

final class EntityTranslationsController
{
    private EntityTranslationsService $service;

    public function __construct(EntityTranslationsService $service)
    {
        $this->service = $service;
    }

    public function getByEntity(int $entityId): array
    {
        return $this->service->getByEntity($entityId);
    }

    public function save(array $data): int
    {
        return $this->service->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
