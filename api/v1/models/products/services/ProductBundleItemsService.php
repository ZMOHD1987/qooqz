<?php
declare(strict_types=1);

final class ProductBundleItemsService
{
    private PdoProductBundleItemsRepository $repo;
    private ProductBundleItemsValidator $validator;

    public function __construct(PdoProductBundleItemsRepository $repo, ProductBundleItemsValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function getByBundle(int $tenantId, int $bundleId, string $lang = 'ar'): array
    {
        return $this->repo->getByBundle($tenantId, $bundleId, $lang);
    }

    public function find(int $tenantId, int $itemId, string $lang = 'ar'): ?array
    {
        return $this->repo->find($tenantId, $itemId, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('Item ID required for update.');
        }
        $this->validator->validate($data, true);
        return $this->repo->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $itemId): bool
    {
        return $this->repo->delete($tenantId, $itemId);
    }

    public function deleteByBundle(int $tenantId, int $bundleId): bool
    {
        return $this->repo->deleteByBundle($tenantId, $bundleId);
    }
}