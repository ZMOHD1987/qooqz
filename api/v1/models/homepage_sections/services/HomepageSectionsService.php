<?php
declare(strict_types=1);

// api/v1/models/homepage_sections/services/HomepageSectionsService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoHomepageSectionsRepository.php';
require_once __DIR__ . '/../validators/HomepageSectionsValidator.php';

final class HomepageSectionsService
{
    private PdoHomepageSectionsRepository $repo;
    private HomepageSectionsValidator $validator;

    public function __construct(
        PdoHomepageSectionsRepository $repo,
        HomepageSectionsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?string $sectionType = null, ?int $themeId = null, string $lang = 'en'): array
    {
        return $this->repo->all($tenantId, $sectionType, $themeId, $lang);
    }

    public function get(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->find($tenantId, $id, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Homepage section not found');
        }

        return $row;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): array
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($tenantId, $data, $userId);

        $row = $this->repo->find($tenantId, $id, 'en', true); // Get with all translations
        if (!$row) {
            throw new RuntimeException('Failed to load saved homepage section');
        }

        return $row;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete homepage section');
        }
    }

    public function getSectionTypes(int $tenantId): array
    {
        return $this->repo->getSectionTypes($tenantId);
    }

    public function getActiveSections(int $tenantId, string $lang = 'en', ?int $themeId = null): array
    {
        return $this->repo->getActiveSections($tenantId, $lang, $themeId);
    }
}