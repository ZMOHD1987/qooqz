<?php
declare(strict_types=1);

// api/v1/models/product_attribute_assignments/controllers/ProductAttributeAssignmentsController.php

final class ProductAttributeAssignmentsController
{
    private ProductAttributeAssignmentsService $service;

    public function __construct(ProductAttributeAssignmentsService $service)
    {
        $this->service = $service;
    }

    public function list(): array
    {
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
        $attributeId = isset($_GET['attribute_id']) ? (int)$_GET['attribute_id'] : null;
        return $this->service->list($productId, $attributeId);
    }

    public function getByProduct(int $productId): array
    {
        return $this->service->getByProduct($productId);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $this->service->delete((int) $data['id'], $userId);
    }

    public function deleteByProduct(int $productId): void
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $this->service->deleteByProduct($productId, $userId);
    }
}