<?php
declare(strict_types=1);

// api/v1/models/product_attribute_assignments/services/ProductAttributeAssignmentsService.php

/*
|--------------------------------------------------------------------------
| Required dependencies (NO autoload, NO namespace)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../repositories/PdoProductAttributeAssignmentsRepository.php';
require_once __DIR__ . '/../validators/ProductAttributeAssignmentsValidator.php';

final class ProductAttributeAssignmentsService
{
    private PdoProductAttributeAssignmentsRepository $repo;
    private ProductAttributeAssignmentsValidator $validator;

    public function __construct(
        PdoProductAttributeAssignmentsRepository $repo,
        ProductAttributeAssignmentsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(?int $productId = null, ?int $attributeId = null): array
    {
        return $this->repo->all($productId, $attributeId);
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Product attribute assignment not found');
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
            throw new RuntimeException('Failed to load saved product attribute assignment');
        }

        return $row;
    }

    public function delete(int $id, ?int $userId = null): void
    {
        if (!$this->repo->delete($id, $userId)) {
            throw new RuntimeException('Failed to delete product attribute assignment');
        }
    }

    public function getByProduct(int $productId): array
    {
        return $this->repo->getByProduct($productId);
    }

    public function deleteByProduct(int $productId, ?int $userId = null): void
    {
        $this->repo->deleteByProduct($productId, $userId);
    }
}