<?php
declare(strict_types=1);

/**
 * CategoriesRepositoryInterface
 *
 * Contract for the categories persistence layer.
 * Any concrete repository (e.g. PdoCategoriesRepository) must implement
 * every method declared here, guaranteeing a stable API for the service
 * and controller layers and enabling easy swapping or mocking in tests.
 *
 * Super-admin bypass convention
 * ──────────────────────────────
 * All methods that accept a $tenantId parameter now accept ?int.
 * When $tenantId is NULL the implementation MUST skip ALL tenant-scoped
 * WHERE clauses and JOIN restrictions so that a super admin can operate
 * across every tenant without any filter.  Write methods (save/delete)
 * with a null tenantId must derive the tenant from the data array
 * (e.g. $data['tenant_id']) or reject the call with an exception.
 */
interface CategoriesRepositoryInterface
{
    /**
     * Return the underlying PDO connection (used by SEO helpers, etc.)
     */
    public function getPdo(): PDO;

    // ─────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────

    /**
     * Return a paginated, filtered list of categories with translated fields.
     *
     * @param int|null    $tenantId     NULL = super-admin: all tenants, no filter
     * @param int|null    $parentId     Filter by parent (0 = root only, null = all)
     * @param bool        $featuredOnly Return only featured categories
     * @param string      $lang         BCP-47 language code for translations
     * @param string|null $search       Full-text search in name/description
     * @param bool|null   $isActive     Filter by active flag (null = both)
     * @param int         $limit        Page size
     * @param int         $offset       Row offset
     * @param bool        $skipTcFilter Skip tenant_categories assignment join
     */
    public function all(
        ?int $tenantId,
        ?int $parentId = null,
        bool $featuredOnly = false,
        string $lang = 'en',
        ?string $search = null,
        ?bool $isActive = null,
        int $limit = 50,
        int $offset = 0,
        bool $skipTcFilter = false
    ): array;

    /**
     * Count categories matching the given filters (for pagination).
     *
     * @param int|null $tenantId     NULL = super-admin: all tenants, no filter
     * @param bool     $skipTcFilter Skip tenant_categories assignment join
     */
    public function countAll(
        ?int $tenantId,
        array $filters = [],
        bool $skipTcFilter = false
    ): int;

    /**
     * Find a single category row by ID.
     * Returns null when the category is not found.
     * When $tenantId is null the tenant check is skipped (super-admin).
     */
    public function findById(?int $tenantId, int $id): ?array;

    /**
     * Find a category with all its translations embedded under 'translations'.
     * Returns null when the category is not found.
     * When $tenantId is null the tenant check is skipped (super-admin).
     */
    public function findByIdWithTranslations(?int $tenantId, int $id): ?array;

    /**
     * Resolve a category ID from its slug.
     * Returns null when the slug is not found.
     * When $tenantId is null searches across all tenants.
     */
    public function findIdBySlug(?int $tenantId, string $slug): ?int;

    /**
     * Return all translations for a category keyed by language_code.
     */
    public function getTranslations(int $categoryId): array;

    /**
     * Return the main image record for a category, or null.
     * When $tenantId is null the tenant check is skipped.
     */
    public function getMainImage(?int $tenantId, int $categoryId): ?array;

    /**
     * Return all active categories.
     * When $tenantId is null returns active categories across all tenants.
     */
    public function getActiveCategories(?int $tenantId, string $lang = 'en'): array;

    /**
     * Return all featured categories.
     * When $tenantId is null returns featured categories across all tenants.
     */
    public function getFeaturedCategories(?int $tenantId, string $lang = 'en'): array;

    /**
     * Return true when a slug is already in use by another category.
     * When $tenantId is null checks across all tenants.
     *
     * @param int|null $excludeId Exclude this category's own slug when editing
     */
    public function slugExists(
        ?int $tenantId,
        string $slug,
        ?int $excludeId = null
    ): bool;

    /**
     * Return true when the category has sub-categories.
     */
    public function hasChildren(int $categoryId): bool;

    // ─────────────────────────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────────────────────────

    /**
     * Persist a category (INSERT on create, UPDATE when $data['id'] is set).
     * Also saves translations, image assignment, and writes an audit log entry.
     *
     * When $tenantId is null the implementation must read tenant_id from
     * $data['tenant_id']; if absent it MUST throw InvalidArgumentException.
     *
     * @return int  The category ID (new or updated)
     */
    public function save(?int $tenantId, array $data, ?int $userId = null): int;

    /**
     * Upsert translations for a category.
     * Existing rows for a language_code are replaced; new rows are inserted.
     */
    public function saveTranslations(int $categoryId, array $translations): void;

    /**
     * Hard-delete a single category (with audit log).
     * When $tenantId is null the tenant check is skipped (super-admin).
     * Returns true on success, throws on failure.
     */
    public function delete(?int $tenantId, int $categoryId, ?int $userId = null): bool;

    /**
     * Delete one specific translation for a category.
     * Returns true when a row was deleted.
     */
    public function deleteTranslation(int $categoryId, string $lang): bool;
}

