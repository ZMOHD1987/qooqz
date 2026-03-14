<?php
declare(strict_types=1);

final class ProductComparisonItemsService
{
    private PdoProductComparisonItemsRepository $repo;
    private ProductComparisonItemsValidator $validator;

    public function __construct(PdoProductComparisonItemsRepository $repo, ProductComparisonItemsValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function getByComparison(int $tenantId, int $comparisonId, string $lang): array
    {
        return $this->repo->getByComparison($tenantId, $comparisonId, $lang);
    }

    public function find(int $tenantId, int $itemId, string $lang): ?array
    {
        return $this->repo->find($tenantId, $itemId, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->create($tenantId, $data);
    }

    public function delete(int $tenantId, int $itemId): bool
    {
        return $this->repo->delete($tenantId, $itemId);
    }

    public function deleteByComparison(int $tenantId, int $comparisonId): bool
    {
        return $this->repo->deleteByComparison($tenantId, $comparisonId);
    }
}