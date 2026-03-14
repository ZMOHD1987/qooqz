<?php
declare(strict_types=1);

final class ProductBundleItemsController
{
    private ProductBundleItemsService $service;

    public function __construct(ProductBundleItemsService $service)
    {
        $this->service = $service;
    }

    public function getByBundle(int $tenantId, int $bundleId, string $lang = 'ar'): array
    {
        return $this->service->getByBundle($tenantId, $bundleId, $lang);
    }

    public function find(int $tenantId, int $itemId, string $lang = 'ar'): ?array
    {
        return $this->service->find($tenantId, $itemId, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $itemId): bool
    {
        return $this->service->delete($tenantId, $itemId);
    }

    public function deleteByBundle(int $tenantId, int $bundleId): bool
    {
        return $this->service->deleteByBundle($tenantId, $bundleId);
    }
}