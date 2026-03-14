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

        return $this->service->list($tenantId, $categoryId, $isActive, $page, $limit);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): array
    {
        return $this->service->save($data);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) throw new InvalidArgumentException('ID is required');
        return $this->service->save($data);
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
}