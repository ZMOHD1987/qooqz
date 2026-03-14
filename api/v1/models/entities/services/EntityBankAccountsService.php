<?php
declare(strict_types=1);

final class EntityBankAccountsService
{
    private PdoEntityBankAccountsRepository $repo;

    public function __construct(PdoEntityBankAccountsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $tenantId,
        int $entityId,
        ?int $limit,
        ?int $offset,
        string $orderBy,
        string $orderDir,
        array $filters = []
    ): array {
        return $this->repo->all($tenantId, $entityId, $limit, $offset, $orderBy, $orderDir, $filters);
    }

    public function get(int $tenantId, int $entityId, int $id): ?array
    {
        return $this->repo->find($tenantId, $entityId, $id);
    }

    public function save(int $tenantId, int $entityId, array $data): int
    {
        return $this->repo->save($tenantId, $entityId, $data);
    }

    public function delete(int $tenantId, int $entityId, int $id): bool
    {
        return $this->repo->delete($tenantId, $entityId, $id);
    }
}