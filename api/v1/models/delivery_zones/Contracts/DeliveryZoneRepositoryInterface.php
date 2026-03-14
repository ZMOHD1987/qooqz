<?php
declare(strict_types=1);

/**
 * Interface DeliveryZoneRepositoryInterface
 *
 * Contract for DeliveryZones data access layer.
 *
 * Location: api/v1/models/delivery_zones/Contracts/DeliveryZoneRepositoryInterface.php
 */
interface DeliveryZoneRepositoryInterface
{
    /**
     * Retrieve a paginated, filtered list of delivery zones for a tenant.
     *
     * @param int         $tenantId  Tenant scope
     * @param int|null    $limit     Max rows to return
     * @param int|null    $offset    Rows to skip
     * @param array       $filters   Keyed filters: provider_id, zone_type, city_id, is_active
     * @param string      $orderBy   Column to sort by
     * @param string      $orderDir  'ASC' or 'DESC'
     * @param string      $lang      Language code
     * @return array<int, array<string, mixed>>
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'dz.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    /**
     * Count total delivery zones for a tenant, optionally filtered.
     *
     * @param int   $tenantId
     * @param array $filters
     * @return int
     */
    public function count(int $tenantId, array $filters = []): int;

    /**
     * Find a single delivery zone by ID within a tenant scope.
     *
     * @param int    $tenantId
     * @param int    $id
     * @param string $lang
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array;

    /**
     * Create a new delivery zone.
     *
     * @param int   $tenantId
     * @param array $data
     * @return int  The new zone's ID
     * @throws InvalidArgumentException
     */
    public function create(int $tenantId, array $data): int;

    /**
     * Update an existing delivery zone.
     *
     * @param int   $tenantId
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(int $tenantId, array $data): bool;

    /**
     * Delete a single delivery zone by ID.
     *
     * @param int $tenantId
     * @param int $id
     * @return bool
     */
    public function delete(int $tenantId, int $id): bool;
}