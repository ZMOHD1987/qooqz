<?php
declare(strict_types=1);

// api/v1/models/banners/services/BannersService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoBannersRepository.php';
require_once __DIR__ . '/../validators/BannersValidator.php';

final class BannersService
{
    private PdoBannersRepository $repo;
    private BannersValidator $validator;

    public function __construct(
        PdoBannersRepository $repo,
        BannersValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?string $position = null, ?int $themeId = null, string $lang = 'en'): array
    {
        return $this->repo->all($tenantId, $position, $themeId, $lang);
    }

    public function get(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->find($tenantId, $id, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Banner not found');
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
            throw new RuntimeException('Failed to load saved banner');
        }

        return $row;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete banner');
        }
    }

    public function getPositions(int $tenantId): array
    {
        return $this->repo->getPositions($tenantId);
    }

    public function getActiveBanners(int $tenantId, string $position, string $lang = 'en', ?int $themeId = null): array
    {
        return $this->repo->getActiveBanners($tenantId, $position, $lang, $themeId);
    }
}