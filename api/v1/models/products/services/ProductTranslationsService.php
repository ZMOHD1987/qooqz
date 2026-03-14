<?php
declare(strict_types=1);

final class ProductTranslationsService
{
    private PdoProductTranslationsRepository $repo;

    public function __construct(PdoProductTranslationsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(int $tenantId, ?string $languageCode, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        return $this->repo->all($tenantId, $languageCode, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $tenantId, int $id, ?string $languageCode): ?array
    {
        return $this->repo->find($tenantId, $id, $languageCode);
    }

    public function create(array $data): int
    {
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
