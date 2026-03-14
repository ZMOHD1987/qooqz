<?php
declare(strict_types=1);

/**
 * Interface DeliveryProviderRepositoryInterface
 *
 * Contract for DeliveryProviders data access layer.
 *
 * Location: api/v1/models/delivery_zones/Contracts/DeliveryProviderRepositoryInterface.php
 */
interface DeliveryProviderRepositoryInterface
{
    /**
     * Retrieve a paginated, filtered list of delivery providers for a tenant.
     *
     * @param int         $tenantId
     * @param int|null    $limit
     * @param int|null    $offset
     * @param array       $filters   Keyed filters: provider_type, vehicle_type, is_online, is_active
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
        string $orderBy = 'dp.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    /**
     * Count total delivery providers for a tenant.
     *
     * @param int   $tenantId
     * @param array $filters
     * @return int
     */
    public function count(int $tenantId, array $filters = []): int;

    /**
     * Find a single delivery provider by ID.
     *
     * @param int    $tenantId
     * @param int    $id
     * @param string $lang
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array;

    /**
     * Create a new delivery provider.
     *
     * @param int   $tenantId
     * @param array $data
     * @return int
     * @throws InvalidArgumentException
     */
    public function create(int $tenantId, array $data): int;

    /**
     * Update an existing delivery provider.
     *
     * @param int   $tenantId
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $tenantId, array $data): bool;

    /**
     * Delete a delivery provider.
     *
     * @param int $tenantId
     * @param int $id
     * @return bool
     */
    public function delete(int $tenantId, int $id): bool;
}