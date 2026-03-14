<?php
declare(strict_types=1);

final class EntitiesWorkingHoursService
{
    private PdoEntitiesWorkingHoursRepository $repo;

    public function __construct(PdoEntitiesWorkingHoursRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $query): array
{
    $page = max(1, (int)($query['page'] ?? 1));
    $perPage = min(1000, max(1, (int)($query['per_page'] ?? 25)));

    $result = $this->repo->all(
        $query,
        $perPage,
        ($page - 1) * $perPage,
        $query['order_by'] ?? 'id',
        $query['order_dir'] ?? 'DESC'
    );

    $items = $result['items'];
    $total = $result['total'];

    return [
        'items' => $items,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
            'from' => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
            'to' => $total > 0 ? min($page * $perPage, $total) : 0
        ]
    ];
}

    public function get(int $id): array
    {
        $item = $this->repo->find($id);
        if (!$item) {
            throw new RuntimeException("Entity working hour not found");
        }
        return $item;
    }

    public function create(array $data): int
    {
        EntitiesWorkingHoursValidator::validateCreate($data);
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): void
    {
        EntitiesWorkingHoursValidator::validateUpdate($data);
        $this->repo->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
    }

    public function getByEntity(int $entityId): array
    {
        $result = $this->repo->all(['entity_id' => $entityId], 100);
        return $result['items'] ?? [];
    }

    public function deleteByEntity(int $entityId): void
    {
        $this->repo->deleteByEntity($entityId);
    }
}