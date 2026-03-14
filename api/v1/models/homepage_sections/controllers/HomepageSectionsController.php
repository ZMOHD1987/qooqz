<?php
declare(strict_types=1);

/**
 * HomepageSectionsController
 *
 * Thin layer — parses request params and delegates to Service.
 * No business logic here.
 *
 * Location: api/v1/models/homepage_sections/controllers/HomepageSectionsController.php
 */
final class HomepageSectionsController
{
    private HomepageSectionsService $service;

    public function __construct(HomepageSectionsService $service)
    {
        $this->service = $service;
    }

    // =========================================================================
    // GET endpoints
    // =========================================================================

    public function list(int $tenantId, int $userId): array
    {
        $lang        = $this->resolveLang($userId);
        $sectionType = $_GET['section_type'] ?? null;
        $themeId     = isset($_GET['theme_id']) && ctype_digit((string) $_GET['theme_id'])
                           ? (int) $_GET['theme_id'] : null;

        return $this->service->list($tenantId, $sectionType, $themeId, $lang);
    }

    public function get(int $tenantId, int $id, int $userId): array
    {
        $lang            = $this->resolveLang($userId);
        $allTranslations = ($this->param('all_translations') === '1');
        return $this->service->get($tenantId, $id, $lang, $allTranslations);
    }

    public function getActive(int $tenantId, int $userId): array
    {
        $lang    = $this->resolveLang($userId);
        $themeId = isset($_GET['theme_id']) && ctype_digit((string) $_GET['theme_id'])
                       ? (int) $_GET['theme_id'] : null;
        return $this->service->getActiveSections($tenantId, $lang, $themeId);
    }

    public function sectionTypes(int $tenantId): array
    {
        return $this->service->getSectionTypes($tenantId);
    }

    public function translations(int $tenantId, int $id): array
    {
        return $this->service->getTranslations($tenantId, $id);
    }

    public function languages(): array
    {
        return $this->service->getSupportedLanguages();
    }

    // =========================================================================
    // Write endpoints
    // =========================================================================

    public function create(int $tenantId, array $data, int $userId): array
    {
        return $this->service->create($tenantId, $data, $userId);
    }

    public function update(int $tenantId, array $data, int $userId): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update.');
        }
        return $this->service->update($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, int $id, int $userId): void
    {
        $this->service->delete($tenantId, $id, $userId);
    }

    public function saveTranslations(int $tenantId, int $id, array $translations, int $userId): array
    {
        return $this->service->saveTranslations($tenantId, $id, $translations, $userId);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Resolve language: explicit query param → user preferred_language → 'en'
     */
    private function resolveLang(int $userId): string
    {
        $explicit = $_GET['lang'] ?? null;
        return $this->service->resolveLang(
            ($explicit !== '' ? $explicit : null),
            $userId
        );
    }

    private function param(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}