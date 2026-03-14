<?php
declare(strict_types=1);

final class OrdersService
{
    private PdoOrdersRepository $repo;

    public function __construct(PdoOrdersRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang = 'ar'
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($tenantId, $id, $lang);
    }

    public function getByOrderNumber(int $tenantId, string $orderNumber): ?array
    {
        return $this->repo->findByOrderNumber($tenantId, $orderNumber);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->repo->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }
        return $this->repo->save($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }
}
