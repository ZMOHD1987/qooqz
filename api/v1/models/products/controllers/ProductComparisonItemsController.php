<?php
declare(strict_types=1);

final class ProductComparisonItemsController
{
    private ProductComparisonItemsService $service;

    public function __construct(ProductComparisonItemsService $service)
    {
        $this->service = $service;
    }

    public function getByComparison(int $tenantId, int $comparisonId, string $lang): array
    {
        return $this->service->getByComparison($tenantId, $comparisonId, $lang);
    }

    public function find(int $tenantId, int $itemId, string $lang): ?array
    {
        return $this->service->find($tenantId, $itemId, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    public function delete(int $tenantId, int $itemId): bool
    {
        return $this->service->delete($tenantId, $itemId);
    }

    public function deleteByComparison(int $tenantId, int $comparisonId): bool
    {
        return $this->service->deleteByComparison($tenantId, $comparisonId);
    }
}