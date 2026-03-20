<?php
declare(strict_types=1);

/**
 * TenantsRepositoryInterface
 *
 * Contract for the tenants persistence layer.
 * Any concrete repository (e.g. PdoTenantsRepository) must implement
 * every method declared here, guaranteeing a stable API for the service
 * and controller layers and enabling easy swapping or mocking in tests.
 */
interface TenantsRepositoryInterface
{
    // ─────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────

    /**
     * Return a paginated, filtered list of tenants.
     *
     * @param int   $perPage  Page size (max 100)
     * @param int   $offset   Row offset for pagination
     * @param array $filters  Supported keys: search, status, owner_user_id
     * @return array<int, array<string, mixed>>
     */
    public function all(int $perPage = 10, int $offset = 0, array $filters = []): array;

    /**
     * Count tenants matching the given filters (for pagination).
     *
     * @param array $filters Same keys as all()
     */
    public function count(array $filters = []): int;

    /**
     * Find a single tenant row by ID, or null if not found.
     * Joins the owner user for username/email context.
     *
     * @param int $id Tenant primary key
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array;

    /**
     * Find a tenant by domain slug.
     *
     * @param string $domain Unique domain slug
     * @return array<string, mixed>|null
     */
    public function findByDomain(string $domain): ?array;

    /**
     * Return only active tenants (status = 'active'), no pagination.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActive(): array;

    /**
     * Check whether a domain slug is already used.
     *
     * @param string   $domain    Domain to check
     * @param int|null $excludeId Exclude this tenant ID (for edit uniqueness check)
     */
    public function domainExists(string $domain, ?int $excludeId = null): bool;

    /**
     * Check whether a user ID exists in the users table.
     *
     * @param int $userId
     */
    public function userExists(int $userId): bool;

    /**
     * Return aggregate statistics: total, active, suspended counts.
     *
     * @return array{total_tenants: int, active_tenants: int, suspended_tenants: int}
     */
    public function getStats(): array;

    // ─────────────────────────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────────────────────────

    /**
     * Create or update a tenant record.
     *
     * Required keys for create: name, owner_user_id, status
     * Optional keys:            domain
     * Required for update:      id (in addition to above)
     *
     * @param  array    $data   Tenant field values
     * @param  int|null $userId Acting user (for audit log)
     * @return int              ID of the created/updated tenant
     */
    public function save(array $data, ?int $userId = null): int;

    /**
     * Hard-delete a tenant by ID.
     *
     * @param  int      $id     Tenant primary key
     * @param  int|null $userId Acting user (for audit log)
     * @return bool             True on success, false when record not found
     */
    public function delete(int $id, ?int $userId = null): bool;

    /**
     * Bulk-update the status of multiple tenants.
     *
     * @param  int[]    $ids    Tenant IDs to update
     * @param  string   $status New status value ('active' | 'suspended')
     * @param  int|null $userId Acting user (for audit log)
     * @return int              Number of affected rows
     */
    public function bulkUpdateStatus(array $ids, string $status, ?int $userId = null): int;
}
