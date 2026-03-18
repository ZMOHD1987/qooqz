<?php
declare(strict_types=1);

/**
 * TenantCategoriesRepositoryInterface
 *
 * Contract for the tenant_categories persistence layer.
 * Manages the many-to-many relationship between tenants and categories.
 */
interface TenantCategoriesRepositoryInterface
{
    /**
     * Return a paginated, filtered list of tenant-category assignments.
     *
     * @param int|null $tenantId    Filter by tenant (null = all tenants)
     * @param int|null $categoryId  Filter by category (null = all categories)
     * @param int|null $isActive    Filter by active flag (null = both)
     * @param int      $offset      Row offset for pagination
     * @param int|null $limit       Page size (null = no limit)
     */
    public function all(
        ?int $tenantId = null,
        ?int $categoryId = null,
        ?int $isActive = null,
        int $offset = 0,
        ?int $limit = null
    ): array;

    /**
     * Find a single tenant-category assignment by ID.
     * Returns null when not found.
     */
    public function find(int $id): ?array;

    /**
     * Persist a tenant-category assignment.
     * INSERT when $data['id'] is absent; UPDATE otherwise.
     * Returns the record ID.
     */
    public function save(array $data): int;

    /**
     * Hard-delete a tenant-category assignment by ID.
     * Returns true on success.
     */
    public function delete(int $id): bool;
}
