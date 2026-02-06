<?php
declare(strict_types=1);

// api/v1/models/brands/services/BrandsService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoBrandsRepository.php';
require_once __DIR__ . '/../validators/BrandsValidator.php';

final class BrandsService
{
    private PdoBrandsRepository $repo;
    private BrandsValidator $validator;

    public function __construct(
        PdoBrandsRepository $repo,
        BrandsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, bool $featuredOnly = false, string $lang = 'en'): array
    {
        return $this->repo->all($tenantId, $featuredOnly, $lang);
    }

    public function get(int $tenantId, string $slug, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->find($tenantId, $slug, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Brand not found');
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

        $row = $this->repo->findById($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved brand');
        }

        // Add translations to response
        $row['translations'] = $this->repo->getTranslations($id);

        return $row;
    }

    public function delete(int $tenantId, string $slug, ?int $userId = null): void
    {
        if (!$this->repo->delete($tenantId, $slug, $userId)) {
            throw new RuntimeException('Failed to delete brand');
        }
    }

    public function deleteById(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->deleteById($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete brand');
        }
    }

    public function getActiveBrands(int $tenantId, string $lang = 'en'): array
    {
        return $this->repo->getActiveBrands($tenantId, $lang);
    }

    public function getFeaturedBrands(int $tenantId, string $lang = 'en'): array
    {
        return $this->repo->getFeaturedBrands($tenantId, $lang);
    }
}