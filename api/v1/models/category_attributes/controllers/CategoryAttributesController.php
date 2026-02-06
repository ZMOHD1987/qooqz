<?php
declare(strict_types=1);

// api/v1/models/category_attributes/controllers/CategoryAttributesController.php

final class CategoryAttributesController
{
    private CategoryAttributesService $service;

    public function __construct(CategoryAttributesService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->list($tenantId, $categoryId, $lang);
    }

    public function getByCategory(int $tenantId, int $categoryId): array
    {
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->getByCategory($tenantId, $categoryId, $lang);
    }

    public function get(int $tenantId, int $id): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->get($tenantId, $id, $lang, $allTranslations);
    }

    public function create(int $tenantId, array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $this->service->delete($tenantId, (int) $data['id'], $userId);
    }
}