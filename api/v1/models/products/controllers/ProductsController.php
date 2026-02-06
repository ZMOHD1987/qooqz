<?php
declare(strict_types=1);

final class ProductsController
{
    private ProductsService $service;

    public function __construct(ProductsService $service)
    {
        $this->service = $service;
    }

    /**
     * List products with filters, ordering, and pagination
     *
     * @param int $tenantId
     * @param int|null $limit
     * @param int|null $offset
     * @param array $filters
     * @param string $orderBy
     * @param string $orderDir
     * @return array
     */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->service->count($tenantId, $filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get a single product by ID
     *
     * @param int $tenantId
     * @param int $id
     * @return array|null
     */
    public function get(int $tenantId, int $id): ?array
    {
        return $this->service->get($tenantId, $id);
    }

    /**
     * Create a new product
     *
     * @param int $tenantId
     * @param array $data
     * @return int New product ID
     */
    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    /**
     * Update an existing product
     *
     * @param int $tenantId
     * @param array $data Must include 'id'
     * @return int Updated product ID
     */
    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    /**
     * Delete a product by ID
     *
     * @param int $tenantId
     * @param int $id
     * @return bool
     */
    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }
}
