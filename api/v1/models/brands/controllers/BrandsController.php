<?php
declare(strict_types=1);

final class BrandsController
{
    private BrandsService $service;

    public function __construct(BrandsService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /brands?page=1&limit=20&order_by=sort_order&order_dir=ASC&lang=en&filter[entity_id]=5&filter[is_active]=1
     */
    public function list(int $tenantId): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        $orderBy = $_GET['order_by'] ?? 'sort_order';
        $orderDir = $_GET['order_dir'] ?? 'ASC';

        $filters = [];
        if (isset($_GET['filter']) && is_array($_GET['filter'])) {
            $filters = $_GET['filter'];
        }

        return $this->service->list($tenantId, $filters, $orderBy, $orderDir, $limit, $offset, $lang);
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

    public function getById(int $tenantId, int $id): array
    {
        $lang = $_GET['lang'] ?? 'en';
        $allTranslations = isset($_GET['all_translations']) && $_GET['all_translations'] === '1';
        return $this->service->getById($tenantId, $id, $lang, $allTranslations);
    }

    public function create(int $tenantId, array $data): array
    {
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id']) && empty($data['slug'])) {
            throw new InvalidArgumentException('Either id or slug is required for update');
        }
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        return $this->service->save($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, array $data): void
    {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;

        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int)$data['id'], $userId);
        } elseif (!empty($data['slug'])) {
            $this->service->delete($tenantId, $data['slug'], $userId);
        } else {
            throw new InvalidArgumentException('ID or slug is required');
        }
    }
}