<?php
declare(strict_types=1);

// api/v1/models/categories/services/TenantCategoriesService.php
require_once __DIR__ . '/../repositories/PdoTenantCategoriesRepository.php';
require_once __DIR__ . '/../validators/TenantCategoriesValidator.php';

final class TenantCategoriesService
{
    private PdoTenantCategoriesRepository $repo;

    public function __construct(PdoTenantCategoriesRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(?int $tenantId = null, ?int $categoryId = null, ?int $isActive = null, int $page = 1, int $limit = 25, string $lang = 'ar'): array
    {
        $offset = ($page - 1) * $limit;
        return $this->repo->all($tenantId, $categoryId, $isActive, $offset, $limit, $lang);
    }

    public function get(int $id, string $lang = 'ar'): array
    {
        $row = $this->repo->find($id, $lang);
        if (!$row) throw new RuntimeException('Tenant Category not found');
        return $row;
    }

    public function save(array $data, string $lang = 'ar'): array
    {
        $errors = TenantCategoriesValidator::validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors));
        }

        $id = $this->repo->save($data);
        return $this->get($id, $lang);
    }

    public function toggleStatus(int $id, int $isActive): array
    {
        $row = $this->repo->find($id);
        if (!$row) throw new RuntimeException('Tenant Category not found');

        $data = ['id' => $id, 'is_active' => $isActive];
        $this->repo->save($data);
        
        // Return updated row without second query
        $row['is_active'] = $isActive;
        return $row;
    }

    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete Tenant Category');
        }
    }
}