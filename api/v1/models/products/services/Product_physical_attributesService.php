<?php
declare(strict_types=1);

final class ProductPhysicalAttributesService
{
    private PdoProductPhysicalAttributesRepository $repo;
    private ProductPhysicalAttributesValidator $validator;

    public function __construct(
        PdoProductPhysicalAttributesRepository $repo,
        ProductPhysicalAttributesValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    // ===============================
    // List + Filters + Pagination
    // ===============================
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'created_at',
        string $orderDir = 'DESC'
    ): array {
        return $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
    }

    // ===============================
    // Count
    // ===============================
    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    // ===============================
    // Get single record (Product OR Variant)
    // ===============================
    public function getByProduct(int $productId): ?array
    {
        return $this->repo->findByProduct($productId);
    }

    public function getByVariant(int $variantId): ?array
    {
        return $this->repo->findByVariant($variantId);
    }

    // ===============================
    // Create / Save
    // ===============================
    public function create(array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        $isProduct = !empty($data['product_id']);
        $isVariant = !empty($data['variant_id']);

        if ($isProduct === $isVariant) {
            throw new InvalidArgumentException(
                'Exactly one of product_id or variant_id must be provided for update.'
            );
        }

        $this->validator->validate($data, true);
        return $this->repo->save($data);
    }

    // ===============================
    // Delete
    // ===============================
    public function deleteByProduct(int $productId): bool
    {
        return $this->repo->deleteByProduct($productId);
    }

    public function deleteByVariant(int $variantId): bool
    {
        return $this->repo->deleteByVariant($variantId);
    }
}