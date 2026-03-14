<?php
declare(strict_types=1);

/**
 * Interface DeliveryOrderRepositoryInterface
 *
 * Contract for DeliveryOrders data access layer.
 *
 * Location: api/v1/models/delivery_orders/Contracts/DeliveryOrderRepositoryInterface.php
 */
interface DeliveryOrderRepositoryInterface
{
    /**
     * Retrieve a paginated, filtered list of delivery orders for a tenant.
     *
     * @param int         $tenantId
     * @param int|null    $limit
     * @param int|null    $offset
     * @param array       $filters   Keyed filters: order_id, provider_id, delivery_status
     * @param string      $orderBy
     * @param string      $orderDir
     * @param string      $lang
     * @return array<int, array<string, mixed>>
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'dord.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    /**
     * Count total delivery orders for a tenant.
     *
     * @param int   $tenantId
     * @param array $filters
     * @return int
     */
    public function count(int $tenantId, array $filters = []): int;

    /**
     * Find a single delivery order by ID.
     *
     * @param int    $tenantId
     * @param int    $id
     * @param string $lang
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array;

    /**
     * Create a new delivery order.
     *
     * @param int   $tenantId
     * @param array $data
     * @return int
     * @throws InvalidArgumentException
     */
    public function create(int $tenantId, array $data): int;

    /**
     * Update an existing delivery order (e.g., assign provider, update status).
     *
     * @param int   $tenantId
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $tenantId, array $data): bool;

    /**
     * Delete a delivery order.
     *
     * @param int $tenantId
     * @param int $id
     * @return bool
     */
    public function delete(int $tenantId, int $id): bool;
}