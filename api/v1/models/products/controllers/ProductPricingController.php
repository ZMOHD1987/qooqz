<?php
declare(strict_types=1);

final class ProductPricingController
{
    private ProductPricingService $service;

    public function __construct(ProductPricingService $service)
    {
        $this->service = $service;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return [
            'items' => $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir),
            'total' => $this->service->count($tenantId, $filters)
        ];
    }

    public function get(int $tenantId, int $id): ?array
    {
        return $this->service->get($tenantId, $id);
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
