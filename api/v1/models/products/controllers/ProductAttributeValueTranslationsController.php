<?php
declare(strict_types=1);

// api/v1/models/product_attribute_value_translations/controllers/ProductAttributeValueTranslationsController.php

final class ProductAttributeValueTranslationsController
{
    private ProductAttributeValueTranslationsService $service;

    public function __construct(ProductAttributeValueTranslationsService $service)
    {
        $this->service = $service;
    }

    public function list(): array
    {
        $attributeValueId = isset($_GET['attribute_value_id']) ? (int)$_GET['attribute_value_id'] : null;
        $languageCode = $_GET['language_code'] ?? null;
        return $this->service->list($attributeValueId, $languageCode);
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
}