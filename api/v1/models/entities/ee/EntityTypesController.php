<?php
declare(strict_types=1);

final class EntityTypesController
{
    private EntityTypesService $service;

    public function __construct(EntityTypesService $service)
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
        return $this->service->list($limit,$offset,$filters,$orderBy,$orderDir);
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
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
