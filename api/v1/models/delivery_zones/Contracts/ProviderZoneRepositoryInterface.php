<?php
declare(strict_types=1);

/**
 * Interface ProviderZoneRepositoryInterface
 *
 * Contract for ProviderZones data access layer.
 * Handles the assignment of zones to delivery providers.
 *
 * Location: api/v1/models/provider_zones/Contracts/ProviderZoneRepositoryInterface.php
 */
interface ProviderZoneRepositoryInterface
{
    /**
     * Retrieve a paginated list of zone assignments for a tenant.
     *
     * @param int         $tenantId
     * @param int|null    $limit
     * @param int|null    $offset
     * @param array       $filters   Keys: provider_id, zone_id, is_active
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
        string $orderBy = 'pz.provider_id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    /**
     * Count total zone assignments.
     *
     * @param int   $tenantId
     * @param array $filters
     * @return int
     */
    public function count(int $tenantId, array $filters = []): int;

    /**
     * Find a specific assignment by composite key.
     *
     * @param int    $tenantId
     * @param int    $providerId
     * @param int    $zoneId
     * @param string $lang
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $providerId, int $zoneId, string $lang = 'ar'): ?array;

    /**
     * Create a new zone assignment.
     *
     * @param int   $tenantId
     * @param array $data  Keys: provider_id, zone_id, is_active
     * @return bool
     * @throws InvalidArgumentException if duplicate or invalid IDs
     */
    public function create(int $tenantId, array $data): bool;

    /**
     * Update an existing assignment (usually just is_active status).
     *
     * @param int   $tenantId
     * @param array $data  Keys: provider_id, zone_id, plus fields to update
     * @return bool
     */
    public function update(int $tenantId, array $data): bool;

    /**
     * Delete a specific assignment.
     *
     * @param int $tenantId
     * @param int $providerId
     * @param int $zoneId
     * @return bool
     */
    public function delete(int $tenantId, int $providerId, int $zoneId): bool;
}