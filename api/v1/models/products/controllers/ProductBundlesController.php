<?php
declare(strict_types=1);

final class ProductBundlesController
{
    private ProductBundlesService $service;

    public function __construct(ProductBundlesService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function count(int $tenantId, array $filters): int
    {
        return $this->service->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id, string $lang): ?array
    {
        return $this->service->get($tenantId, $id, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }
}