<?php
declare(strict_types=1);

// api/v1/models/products/services/ProductAttributeTranslationsService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoProductAttributeTranslationsRepository.php';
require_once __DIR__ . '/../validators/ProductAttributeTranslationsValidator.php';

final class ProductAttributeTranslationsService
{
    private PdoProductAttributeTranslationsRepository $repo;
    private ProductAttributeTranslationsValidator $validator;

    public function __construct(
        PdoProductAttributeTranslationsRepository $repo,
        ProductAttributeTranslationsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(?int $attributeId = null, ?string $languageCode = null): array
    {
        return $this->repo->all($attributeId, $languageCode);
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Product attribute translation not found');
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

        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved product attribute translation');
        }

        return $row;
    }

    public function delete(int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($id, $userId)) {
            throw new RuntimeException('Failed to delete product attribute translation');
        }
    }
}