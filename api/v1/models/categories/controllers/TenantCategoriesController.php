<?php
declare(strict_types=1);

// api/v1/models/categories/controllers/TenantCategoriesController.php
final class TenantCategoriesController
{
    private TenantCategoriesService $service;

    public function __construct(TenantCategoriesService $service)
    {
        $this->service = $service;
    }

    public function list(): array
    {
        $tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        $lang = isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar';

        return $this->service->list($tenantId, $categoryId, $isActive, $page, $limit, $lang);
    }

    public function get(int $id): array
    {
        $lang = isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar';
        return $this->service->get($id, $lang);
    }

    public function create(array $data): array
    {
        $lang = isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar';
        return $this->service->save($data, $lang);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) throw new InvalidArgumentException('ID is required');
        $lang = isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar';
        return $this->service->save($data, $lang);
    }

    public function toggleStatus(array $data): array
    {
        if (empty($data['id']) || !isset($data['is_active'])) throw new InvalidArgumentException('ID and is_active required');
        return $this->service->toggleStatus((int)$data['id'], (int)$data['is_active']);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) throw new InvalidArgumentException('ID is required');
        $this->service->delete((int)$data['id']);
    }

    /**
     * Handle POST /api/categories-tenants/sync
     *
     * Expected body (JSON):
     * {
     *   "tenant_id": 5,
     *   "category_ids": [1, 2, 7],       // checked checkboxes
     *   "include_children": true,          // optional, default true
     *   "is_active": 1                     // optional, default 1
     * }
     */
    public function sync(array $data): array
    {
        if (empty($data['tenant_id']) || !is_numeric($data['tenant_id'])) {
            throw new InvalidArgumentException('tenant_id is required and must be a number');
        }

        $categoryIds = $data['category_ids'] ?? [];
        if (!is_array($categoryIds)) {
            throw new InvalidArgumentException('category_ids must be an array');
        }

        $tenantId        = (int)$data['tenant_id'];
        $includeChildren = isset($data['include_children']) ? (bool)$data['include_children'] : true;
        $isActive        = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $lang            = isset($_GET['lang']) ? (string)$_GET['lang'] : 'ar';

        return $this->service->sync($tenantId, $categoryIds, $includeChildren, $isActive, $lang);
    }
}