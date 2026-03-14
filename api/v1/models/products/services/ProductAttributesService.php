<?php
declare(strict_types=1);

// api/v1/models/products/services/ProductAttributesService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoProductAttributesRepository.php';
require_once __DIR__ . '/../validators/ProductAttributesValidator.php';

final class ProductAttributesService
{
    private PdoProductAttributesRepository $repo;
    private ProductAttributesValidator $validator;

    public function __construct(
        PdoProductAttributesRepository $repo,
        ProductAttributesValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(?int $attributeTypeId = null, string $lang = 'en'): array
    {
        return $this->repo->all($attributeTypeId, $lang);
    }

    public function get(string $slug, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->find($slug, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Product attribute not found');
        }

        return $row;
    }

    public function save(array $data, ?int $userId = null): array
    {
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $id = $this->repo->save($data, $userId);

        $row = $this->repo->findById($id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved product attribute');
        }

        // Add translations to response
        $row['translations'] = $this->repo->getTranslations($id);

        return $row;
    }

    public function delete(string $slug, ?int $userId = null): void
    {
        if (!$this->repo->delete($slug, $userId)) {
            throw new RuntimeException('Failed to delete product attribute');
        }
    }

    public function deleteById(int $id, ?int $userId = null): void
    {
        if (!$this->repo->deleteById($id, $userId)) {
            throw new RuntimeException('Failed to delete product attribute');
        }
    }
}