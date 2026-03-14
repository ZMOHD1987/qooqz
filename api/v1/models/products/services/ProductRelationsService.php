<?php
declare(strict_types=1);

/**
 * ProductRelationsService
 *
 * Business logic layer. Depends on the Interface — not the concrete PDO class.
 * This makes it fully testable and swappable without changing service code.
 *
 * Location: api/v1/modules/products/services/ProductRelationsService.php
 */
final class ProductRelationsService
{
    private ProductRelationsRepositoryInterface $repo;
    private ProductRelationsValidator $validator;

    /**
     * Constructor now type-hints the Interface, not the concrete class.
     * Any implementation (PDO, mock, cache-layer, etc.) can be injected.
     */
    public function __construct(
        ProductRelationsRepositoryInterface $repo,   // ← Interface, not PdoProductRelationsRepository
        ProductRelationsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    public function count(int $tenantId, array $filters): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id, string $lang): ?array
    {
        return $this->repo->find($tenantId, $id, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID required for update.');
        }
        $this->validator->validate($data, true);
        return $this->repo->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    public function deleteByProduct(int $tenantId, int $productId, ?string $relationType = null): bool
    {
        return $this->repo->deleteByProduct($tenantId, $productId, $relationType);
    }

    public function getRelatedProducts(
        int $tenantId,
        int $productId,
        ?string $relationType,
        string $lang
    ): array {
        return $this->repo->getRelatedProducts($tenantId, $productId, $relationType, $lang);
    }
}