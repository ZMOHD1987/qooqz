<?php
declare(strict_types=1);

namespace App\Models\Permissions\Services;

use App\Models\Permissions\Repositories\PdoResourcePermissionsRepository;
use App\Models\Permissions\Validators\ResourcePermissionsValidator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service layer for resource_permissions
 */
class ResourcePermissionsService
{
    private PdoResourcePermissionsRepository $repo;
    private ResourcePermissionsValidator $validator;

    public function __construct(PdoResourcePermissionsRepository $repo, ResourcePermissionsValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(?int $tenantId = null, ?int $roleId = null, ?string $resourceType = null, ?int $permissionId = null): array
    {
        return $this->repo->list(array_filter([
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'resource_type' => $resourceType,
            'permission_id' => $permissionId
        ], fn($v)=>$v !== null && $v !== ''));
    }

    public function get(int $id): array
    {
        $row = $this->repo->get($id);
        if ($row === null) throw new InvalidArgumentException('Not found');
        return $row;
    }

    /**
     * Insert single item (throws if exists, prefer upsert through bulkUpsert)
     */
    public function create(array $item): int
    {
        $norm = $this->validator->validateUpsertItem($item);
        // prefer upsert for safety
        $id = $this->repo->upsertByUnique($norm);
        return $id;
    }

    /**
     * Update single by id
     */
    public function updateSingle(array $item): bool
    {
        if (empty($item['id'])) throw new InvalidArgumentException('id is required for update');
        $norm = $this->validator->validateUpsertItem($item);
        $id = (int)$norm['id'];
        $fields = $this->extractFieldsForUpdate($norm);
        return $this->repo->updateById($id, $fields);
    }

    /**
     * Bulk upsert/update
     */
    public function bulkUpsert(array $items): array
    {
        $norm = $this->validator->validateBulkItems($items);
        return $this->repo->bulkUpsert($norm);
    }

    public function delete(int $id): bool
    {
        return $this->repo->deleteById((int)$id);
    }

    private function extractFieldsForUpdate(array $norm): array
    {
        $allowed = ['resource_type','permission_id','role_id','tenant_id',
            'can_view_all','can_view_own','can_view_tenant','can_create',
            'can_edit_all','can_edit_own','can_delete_all','can_delete_own'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $norm)) {
                // Handle tenant_id conversion
                if ($k === 'tenant_id' && ($norm[$k] === 0 || $norm[$k] === '0')) {
                    $out[$k] = null;
                } else {
                    $out[$k] = $norm[$k];
                }
            }
        }
        return $out;
    }
}