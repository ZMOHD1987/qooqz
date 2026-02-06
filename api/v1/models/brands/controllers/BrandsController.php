<?php
declare(strict_types=1);

// api/v1/models/brands/controllers/BrandsController.php

final class BrandsController
{
    private BrandsService $service;

    public function __construct(BrandsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId): array
    {
        $featuredOnly = isset($_GET['featured']) && $_GET['featured'] === '1';
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->list($tenantId, $featuredOnly, $lang);
    }

    public function getActive(int $tenantId): array
    {
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->getActiveBrands($tenantId, $lang);
    }

    public function getFeatured(int $tenantId): array
    {
        $lang = $_GET['lang'] ?? 'en';
        return $this->service->getFeaturedBrands($tenantId, $lang);
    }

    public function get(int $tenantId, string $slug): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->get($tenantId, $slug, $lang, $allTranslations);
    }

    public function create(int $tenantId, array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['slug']) && empty($data['id'])) {
            throw new InvalidArgumentException('Slug or ID is required');
        }

        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, array $data): void
    {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int) $data['id'], $userId);
        } elseif (!empty($data['slug'])) {
            $this->service->delete($tenantId, $data['slug'], $userId);
        } else {
            throw new InvalidArgumentException('ID or slug is required');
        }
    }
}