<?php
declare(strict_types=1);

// api/v1/models/category_attributes/services/CategoryAttributesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoCategoryAttributesRepository.php';
require_once __DIR__ . '/../validators/CategoryAttributesValidator.php';

final class CategoryAttributesService
{
    private PdoCategoryAttributesRepository $repo;
    private CategoryAttributesValidator $validator;

    public function __construct(
        PdoCategoryAttributesRepository $repo,
        CategoryAttributesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?int $categoryId = null, string $lang = 'en'): array
    {
        return $this->repo->all($tenantId, $categoryId, $lang);
    }

    public function get(int $tenantId, int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->find($tenantId, $id, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Category attribute not found');
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
            throw new RuntimeException('Failed to load saved category attribute');
        }

        // Add translations to response
        $row['translations'] = $this->repo->getTranslations($id);

        return $row;
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete category attribute');
        }
    }

    public function getByCategory(int $tenantId, int $categoryId, string $lang = 'en'): array
    {
        return $this->repo->getByCategory($tenantId, $categoryId, $lang);
    }
}