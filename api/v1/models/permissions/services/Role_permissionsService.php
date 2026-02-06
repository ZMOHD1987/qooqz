<?php
declare(strict_types=1);

final class RolePermissionsService
{
    private PdoRolePermissionsRepository $repository;
    private RolePermissionsValidator $validator;

    public function __construct(
        PdoRolePermissionsRepository $repository,
        RolePermissionsValidator $validator
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    /**
     * جلب جميع صلاحيات Tenant
     */
    public function list(int $tenantId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->repository->all($tenantId, $limit, $offset);
    }

    /**
     * عد الصلاحيات
     */
    public function count(int $tenantId): int
    {
        return $this->repository->count($tenantId);
    }

    /**
     * جلب صلاحية معينة
     */
    public function get(int $tenantId, int $id): array
    {
        $data = $this->repository->find($tenantId, $id);
        if (!$data) {
            throw new RuntimeException('Role permission not found');
        }
        return $data;
    }

    /**
     * Assign Single أو Bulk
     * @param array $data يمكن أن يكون associative أو array من العناصر
     */
    public function assign(int $tenantId, array $data, ?int $userId = null): array
    {
        $created = [];

        // إذا Bulk
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $normalized = [
                    'role_id'       => $row['role_id']       ?? $row['roleId']       ?? null,
                    'permission_id' => $row['permission_id'] ?? $row['permissionId'] ?? null,
                ];

                if (!$this->validator->validate($normalized, 'assign')) {
                    throw new InvalidArgumentException(
                        implode(', ', $this->validator->getErrors())
                    );
                }

                // تحقق من التكرار
                if (!$this->exists($tenantId, (int)$normalized['role_id'], (int)$normalized['permission_id'])) {
                    $id = $this->repository->assign(
                        $tenantId,
                        (int)$normalized['role_id'],
                        (int)$normalized['permission_id'],
                        $userId
                    );
                    $created[] = $this->get($tenantId, $id);
                }
            }

            return $created;
        }

        // Single
        $normalized = [
            'role_id'       => $data['role_id']       ?? $data['roleId']       ?? null,
            'permission_id' => $data['permission_id'] ?? $data['permissionId'] ?? null,
        ];

        if (!$this->validator->validate($normalized, 'assign')) {
            throw new InvalidArgumentException(
                implode(', ', $this->validator->getErrors())
            );
        }

        // تحقق من التكرار
        if (!$this->exists($tenantId, (int)$normalized['role_id'], (int)$normalized['permission_id'])) {
            $id = $this->repository->assign(
                $tenantId,
                (int)$normalized['role_id'],
                (int)$normalized['permission_id'],
                $userId
            );
            $created[] = $this->get($tenantId, $id);
        }

        return $created;
    }

    /**
     * Assign Multiple (باستخدام role_id و array من permission_ids)
     */
    public function assignMultiple(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
    {
        // حذف الحالي
        $this->repository->assignMultiple($tenantId, $roleId, $permissionIds, $userId);
    }

    /**
     * حذف صلاحية
     */
    public function delete(int $tenantId, array $data, ?int $userId = null): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        if (!$this->repository->delete($tenantId, (int)$data['id'], $userId)) {
            throw new RuntimeException('Failed to delete role permission');
        }
    }

    /**
     * تحقق إذا الصلاحية موجودة بالفعل
     */
    private function exists(int $tenantId, int $roleId, int $permissionId): bool
    {
        $all = $this->repository->all($tenantId);
        foreach ($all as $row) {
            if ($row['role_id'] === $roleId && $row['permission_id'] === $permissionId) {
                return true;
            }
        }
        return false;
    }
}
