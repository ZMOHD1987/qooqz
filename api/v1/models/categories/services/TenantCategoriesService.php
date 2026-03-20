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

    /**
     * Full tree-sync for a tenant.
     *
     * Accepts a list of explicitly-selected category IDs from the checkbox tree.
     * When $includeChildren is true (default), every selected parent's descendants
     * are automatically resolved and added to the set before syncing.
     *
     * @param  int   $tenantId
     * @param  int[] $categoryIds      IDs checked in the UI
     * @param  bool  $includeChildren  Auto-expand parent → children
     * @param  int   $isActive
     * @param  string $lang
     * @return array{added:int, removed:int, resolved_ids:int[]}
     */
    public function sync(int $tenantId, array $categoryIds, bool $includeChildren = true, int $isActive = 1, string $lang = 'ar'): array
    {
        // De-duplicate and cast to int
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        // Validate all submitted IDs exist in the categories table
        $missing = $this->repo->findMissingCategoryIds($categoryIds);
        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'Unknown category IDs: ' . implode(', ', $missing)
            );
        }

        // Optionally expand parents to include all their descendants
        $resolvedIds = $includeChildren
            ? $this->repo->getDescendantIds($categoryIds)
            : $categoryIds;

        $resolvedIds = array_values(array_unique(array_map('intval', $resolvedIds)));

        $stats = $this->repo->syncForTenant($tenantId, $resolvedIds, $isActive);

        return array_merge($stats, ['resolved_ids' => $resolvedIds]);
    }
}