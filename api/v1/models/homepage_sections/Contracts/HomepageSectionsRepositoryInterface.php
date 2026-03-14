<?php
declare(strict_types=1);

/**
 * HomepageSectionsRepositoryInterface
 *
 * Contract for homepage sections data access layer.
 * Location: api/v1/models/homepage_sections/Contracts/HomepageSectionsRepositoryInterface.php
 */
interface HomepageSectionsRepositoryInterface
{
    /**
     * Get all sections for a tenant with optional filters.
     * Returns titles/subtitles in the requested language with fallback to default.
     */
    public function all(
        int $tenantId,
        ?string $sectionType = null,
        ?int $themeId = null,
        string $lang = 'en'
    ): array;

    /**
     * Find a single section by ID.
     * If $allTranslations = true, returns all language translations as nested array.
     */
    public function find(
        int $tenantId,
        int $id,
        string $lang = 'en',
        bool $allTranslations = false
    ): ?array;

    /**
     * Find a section by ID without translation join (raw row).
     */
    public function findById(int $tenantId, int $id): ?array;

    /**
     * Insert or update a section (upsert via data['id']).
     * Handles translations and audit log.
     *
     * @return int The section ID
     */
    public function save(int $tenantId, array $data, ?int $userId = null): int;

    /**
     * Delete a section and its translations.
     */
    public function delete(int $tenantId, int $id, ?int $userId = null): bool;

    /**
     * Get distinct section_type values for a tenant.
     */
    public function getSectionTypes(int $tenantId): array;

    /**
     * Get active sections only, ordered by sort_order.
     */
    public function getActiveSections(
        int $tenantId,
        string $lang = 'en',
        ?int $themeId = null
    ): array;

    /**
     * Save or update translations for a section.
     * Accepts: ['ar' => ['title' => '...', 'subtitle' => '...'], 'en' => [...]]
     */
    public function saveTranslations(int $sectionId, array $translations): void;

    /**
     * Get all translations for a section keyed by language_code.
     */
    public function getTranslations(int $sectionId): array;

    /**
     * Resolve the preferred language for a user from the users table.
     * Falls back to $default if not found.
     */
    public function resolveUserLang(int $userId, string $default = 'en'): string;

    /**
     * Get all supported languages from the languages table.
     */
    public function getSupportedLanguages(): array;
}