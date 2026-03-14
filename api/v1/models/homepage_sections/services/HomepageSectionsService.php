<?php
declare(strict_types=1);

/**
 * HomepageSectionsService
 *
 * Business logic layer.
 * Depends on HomepageSectionsRepositoryInterface — not the PDO class directly.
 * Resolves user language automatically from users.preferred_language.
 *
 * Location: api/v1/models/homepage_sections/services/HomepageSectionsService.php
 */
final class HomepageSectionsService
{
    private HomepageSectionsRepositoryInterface $repo;
    private HomepageSectionsValidator $validator;

    public function __construct(
        HomepageSectionsRepositoryInterface $repo,   // ← Interface
        HomepageSectionsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    // =========================================================================
    // Language Resolution
    // =========================================================================

    /**
     * Resolve the best language to use:
     * 1. Explicit $lang param if provided
     * 2. User's preferred_language from DB if $userId given
     * 3. Fallback to 'en'
     */
    public function resolveLang(?string $lang, ?int $userId = null, string $fallback = 'en'): string
    {
        if ($lang !== null && $lang !== '') {
            return $lang;
        }
        if ($userId !== null) {
            return $this->repo->resolveUserLang($userId, $fallback);
        }
        return $fallback;
    }

    /**
     * Get all supported languages from the languages table.
     */
    public function getSupportedLanguages(): array
    {
        return $this->repo->getSupportedLanguages();
    }

    // =========================================================================
    // CRUD
    // =========================================================================

    public function list(
        int $tenantId,
        ?string $sectionType = null,
        ?int $themeId = null,
        string $lang = 'en'
    ): array {
        return $this->repo->all($tenantId, $sectionType, $themeId, $lang);
    }

    public function get(
        int $tenantId,
        int $id,
        string $lang = 'en',
        bool $allTranslations = false
    ): array {
        $row = $this->repo->find($tenantId, $id, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Homepage section not found.', 404);
        }
        return $row;
    }

    public function create(int $tenantId, array $data, ?int $userId = null): array
    {
        $errors = $this->validator->validate($data, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id  = $this->repo->save($tenantId, $data, $userId);
        $row = $this->repo->find($tenantId, $id, 'en', true);
        if (!$row) {
            throw new RuntimeException('Failed to load created section.');
        }
        return $row;
    }

    public function update(int $tenantId, array $data, ?int $userId = null): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update.');
        }

        $errors = $this->validator->validate($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id  = $this->repo->save($tenantId, $data, $userId);
        $row = $this->repo->find($tenantId, $id, 'en', true);
        if (!$row) {
            throw new RuntimeException('Failed to load updated section.');
        }
        return $row;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Homepage section not found or already deleted.', 404);
        }
    }

    public function getSectionTypes(int $tenantId): array
    {
        return $this->repo->getSectionTypes($tenantId);
    }

    public function getActiveSections(
        int $tenantId,
        string $lang = 'en',
        ?int $themeId = null
    ): array {
        return $this->repo->getActiveSections($tenantId, $lang, $themeId);
    }

    public function getTranslations(int $tenantId, int $id): array
    {
        // Verify ownership first
        $row = $this->repo->findById($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Homepage section not found.', 404);
        }
        return $this->repo->getTranslations($id);
    }

    public function saveTranslations(int $tenantId, int $id, array $translations, ?int $userId = null): array
    {
        $row = $this->repo->findById($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Homepage section not found.', 404);
        }

        // Validate each translation
        $errors = $this->validator->validate(['translations' => $translations], true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $this->repo->saveTranslations($id, $translations);

        if ($userId !== null) {
            // Log as partial update
            $this->repo->save($tenantId, array_merge($row, ['id' => $id, 'translations' => $translations]), $userId);
        }

        return $this->repo->getTranslations($id);
    }
}