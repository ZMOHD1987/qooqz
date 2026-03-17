<?php
declare(strict_types=1);

/**
 * CategoriesRepositoryInterface
 *
 * Contract for the categories persistence layer.
 * Any concrete repository (e.g. PdoCategoriesRepository) must implement
 * every method declared here, guaranteeing a stable API for the service
 * and controller layers and enabling easy swapping or mocking in tests.
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
     * @param int         $tenantId
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
        int $tenantId,
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
     * @param bool $skipTcFilter Skip tenant_categories assignment join
     */
    public function countAll(
        int $tenantId,
        array $filters = [],
        bool $skipTcFilter = false
    ): int;

    /**
     * Find a single category row by ID.
     * Returns null when the category is not found or does not belong to the tenant.
     */
    public function findById(int $tenantId, int $id): ?array;

    /**
     * Find a category with all its translations embedded under 'translations'.
     * Returns null when the category is not found.
     */
    public function findByIdWithTranslations(int $tenantId, int $id): ?array;

    /**
     * Resolve a category ID from its slug.
     * Returns null when the slug is not found.
     */
    public function findIdBySlug(int $tenantId, string $slug): ?int;

    /**
     * Return all translations for a category keyed by language_code.
     */
    public function getTranslations(int $categoryId): array;

    /**
     * Return the main image record for a category, or null.
     */
    public function getMainImage(int $tenantId, int $categoryId): ?array;

    /**
     * Return all active categories for the tenant.
     */
    public function getActiveCategories(int $tenantId, string $lang = 'en'): array;

    /**
     * Return all featured categories for the tenant.
     */
    public function getFeaturedCategories(int $tenantId, string $lang = 'en'): array;

    /**
     * Return true when a slug is already in use by another category.
     *
     * @param int|null $excludeId Exclude this category's own slug when editing
     */
    public function slugExists(
        int $tenantId,
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
     * @return int  The category ID (new or updated)
     */
    public function save(int $tenantId, array $data, ?int $userId = null): int;

    /**
     * Upsert translations for a category.
     * Existing rows for a language_code are replaced; new rows are inserted.
     */
    public function saveTranslations(int $categoryId, array $translations): void;

    /**
     * Hard-delete a single category (with sub-category check and audit log).
     * Returns true on success, throws on failure.
     */
    public function delete(int $tenantId, int $categoryId, ?int $userId = null): bool;

    /**
     * Delete one specific translation for a category.
     * Returns true when a row was deleted.
     */
    public function deleteTranslation(int $categoryId, string $lang): bool;
}
