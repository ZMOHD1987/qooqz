<?php
declare(strict_types=1);

final class ProductPhysicalAttributesController
{
    private ProductPhysicalAttributesService $service;

    public function __construct(ProductPhysicalAttributesService $service)
    {
        $this->service = $service;
    }

    // ===============================
    // List + Filters + Pagination
    // ===============================
    public function list(?int $limit = null, ?int $offset = null, array $filters = [], string $orderBy = 'created_at', string $orderDir = 'DESC'): array
    {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    // ===============================
    // Count
    // ===============================
    public function count(array $filters = []): int
    {
        return $this->service->count($filters);
    }

    // ===============================
    // Get single record (Product OR Variant)
    // ===============================
    public function getByProduct(int $productId): ?array
    {
        return $this->service->getByProduct($productId);
    }

    public function getByVariant(int $variantId): ?array
    {
        return $this->service->getByVariant($variantId);
    }

    // ===============================
    // Create / Save
    // ===============================
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    // ===============================
    // Delete (Product OR Variant)
    // ===============================
    public function deleteByProduct(int $productId): bool
    {
        return $this->service->deleteByProduct($productId);
    }

    public function deleteByVariant(int $variantId): bool
    {
        return $this->service->deleteByVariant($variantId);
    }
}