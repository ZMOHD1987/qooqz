<?php
declare(strict_types=1);

final class CartItemsController
{
    private CartItemsService $service;

    public function __construct(CartItemsService $service)
    {
        $this->service = $service;
    }

    /**
     * List cart items with filters, ordering, and pagination
     *
     * @param int $tenantId
     * @param int|null $limit
     * @param int|null $offset
     * @param array $filters
     * @param string $orderBy
     * @param string $orderDir
     * @param string $lang
     * @return array
     */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($tenantId, $filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get a single cart item by ID
     *
     * @param int $tenantId
     * @param int $id
     * @param string $lang
     * @return array|null
     */
    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($tenantId, $id, $lang);
    }

    /**
     * Get all items for a specific cart
     *
     * @param int $tenantId
     * @param int $cartId
     * @return array
     */
    public function getByCart(int $tenantId, int $cartId): array
    {
        return $this->service->getByCart($tenantId, $cartId);
    }

    /**
     * Create a new cart item
     *
     * @param int $tenantId
     * @param array $data
     * @return int New cart item ID
     */
    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    /**
     * Update an existing cart item
     *
     * @param int $tenantId
     * @param array $data Must include 'id'
     * @return int Updated cart item ID
     */
    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    /**
     * Delete a cart item by ID
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
