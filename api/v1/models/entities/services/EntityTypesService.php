<?php
declare(strict_types=1);

final class EntityTypesService
{
    private PdoEntityTypesRepository $repo;

    public function __construct(PdoEntityTypesRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return [
            'items' => $this->repo->all($limit,$offset,$filters,$orderBy,$orderDir),
            'total' => $this->repo->count($filters)
        ];
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function save(array $data): int
    {
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
