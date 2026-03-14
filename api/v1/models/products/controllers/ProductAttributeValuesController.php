<?php
declare(strict_types=1);

// api/v1/models/product_attribute_values/controllers/ProductAttributeValuesController.php

final class ProductAttributeValuesController
{
    private ProductAttributeValuesService $service;

    public function __construct(ProductAttributeValuesService $service)
    {
        $this->service = $service;
    }

    public function list(): array
    {
        $attributeId = isset($_GET['attribute_id']) ? (int)$_GET['attribute_id'] : null;
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->list($attributeId, $lang);
    }

    public function get(string $slug): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->get($slug, $lang, $allTranslations);
    }

    public function create(array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function update(array $data): array
    {
        if (empty($data['id']) && empty($data['slug'])) {
            throw new InvalidArgumentException('ID or slug is required');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($data, $userId);
    }

    public function delete(array $data): void
    {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

        if (!empty($data['id'])) {
            $this->service->deleteById((int) $data['id'], $userId);
        } elseif (!empty($data['slug'])) {
            $this->service->delete($data['slug'], $userId);
        } else {
            throw new InvalidArgumentException('ID or slug is required');
        }
    }
}