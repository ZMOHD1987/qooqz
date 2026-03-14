<?php
declare(strict_types=1);

final class CartsController
{
    private CartsService $service;

    public function __construct(CartsService $service)
    {
        $this->service = $service;
    }

    /**
     * List carts with filters, ordering, and pagination
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
     * Get a single cart by ID
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
     * Get cart by session ID
     *
     * @param int $tenantId
     * @param string $sessionId
     * @param int $entityId
     * @return array|null
     */
    public function getBySession(int $tenantId, string $sessionId, int $entityId): ?array
    {
        return $this->service->getBySession($tenantId, $sessionId, $entityId);
    }

    /**
     * Get cart by user ID
     *
     * @param int $tenantId
     * @param int $userId
     * @param int $entityId
     * @return array|null
     */
    public function getByUser(int $tenantId, int $userId, int $entityId): ?array
    {
        return $this->service->getByUser($tenantId, $userId, $entityId);
    }

    /**
     * Create a new cart
     *
     * @param int $tenantId
     * @param array $data
     * @return int New cart ID
     */
    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    /**
     * Update an existing cart
     *
     * @param int $tenantId
     * @param array $data Must include 'id'
     * @return int Updated cart ID
     */
    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    /**
     * Delete a cart by ID (soft delete)
     *
     * @param int $tenantId
     * @param int $id
     * @return bool
     */
    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }

    /**
     * Convert cart to order
     *
     * @param int $tenantId
     * @param int $cartId
     * @param int $orderId
     * @return bool
     */
    public function convertToOrder(int $tenantId, int $cartId, int $orderId): bool
    {
        return $this->service->convertToOrder($tenantId, $cartId, $orderId);
    }
}
