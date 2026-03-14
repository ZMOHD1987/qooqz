<?php
declare(strict_types=1);

final class ProductStockAlertsService
{
    private PdoProductStockAlertsRepository $repo;

    public function __construct(PdoProductStockAlertsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters = [], ?int $limit = null, ?int $offset = null, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        return $this->repo->all($filters['tenant_id'] ?? 0, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function create(array $data): int
    {
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) throw new InvalidArgumentException("ID is required");
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}