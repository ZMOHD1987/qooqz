<?php
declare(strict_types=1);

final class EntityTranslationsService
{
    private PdoEntityTranslationsRepository $repo;

    public function __construct(PdoEntityTranslationsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getByEntity(int $entityId): array
    {
        return $this->repo->getByEntity($entityId);
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
