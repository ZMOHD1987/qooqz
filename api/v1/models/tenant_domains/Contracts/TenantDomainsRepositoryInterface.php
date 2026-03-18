<?php
declare(strict_types=1);

/**
 * TenantDomainsRepositoryInterface
 *
 * Contract for the tenant_domains persistence layer.
 * A tenant may own multiple domains:
 *   - primary   : canonical domain (mirrors tenants.domain)
 *   - custom    : customer-managed CNAME / A record
 *   - subdomain : platform-generated *.platform.tld
 *   - alias     : vanity domain → redirect to primary
 */
interface TenantDomainsRepositoryInterface
{
    // ─────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────

    /**
     * Return all domains for a tenant.
     *
     * @param  int    $tenantId
     * @param  array  $filters  Supported: type, ssl_status, is_verified, search
     * @param  int    $limit
     * @param  int    $offset
     * @return array<int, array<string, mixed>>
     */
    public function allByTenant(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Count domains for a tenant (for pagination).
     */
    public function countByTenant(int $tenantId, array $filters = []): int;

    /**
     * Find a domain record by its primary key.
     *
     * @param  int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array;

    /**
     * Find a domain record by domain string (global lookup).
     *
     * @param  string $domain
     * @return array<string, mixed>|null
     */
    public function findByDomain(string $domain): ?array;

    /**
     * Check whether a domain string is already registered
     * (globally unique – like DNS).
     *
     * @param  string   $domain
     * @param  int|null $excludeId  Skip this record (for edit uniqueness check)
     */
    public function domainExists(string $domain, ?int $excludeId = null): bool;

    /**
     * Return the primary domain record for a tenant, or null.
     */
    public function findPrimary(int $tenantId): ?array;

    // ─────────────────────────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────────────────────────

    /**
     * Create a new domain record.
     *
     * Required keys: tenant_id, domain
     * Optional keys: type, is_verified, ssl_status, redirect_to_primary,
     *                verification_token, meta
     *
     * @param  array $data
     * @return int   Inserted row ID
     */
    public function create(array $data): int;

    /**
     * Update an existing domain record.
     *
     * @param  int   $id
     * @param  array $data  Fields to update (partial update supported)
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a domain record.
     *
     * @param  int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Mark a domain as verified.
     *
     * @param  int    $id
     * @param  string $verifiedAt  ISO-8601 datetime
     * @return bool
     */
    public function markVerified(int $id, string $verifiedAt): bool;

    /**
     * Update SSL status for a domain.
     *
     * @param  int         $id
     * @param  string      $status      'none'|'pending'|'active'|'failed'
     * @param  string|null $expiresAt   ISO-8601 datetime or null
     * @return bool
     */
    public function updateSslStatus(int $id, string $status, ?string $expiresAt = null): bool;
}
