<?php
declare(strict_types=1);

final class RolesService
{
    private PdoRolesRepository $repository;
    private RolesValidator $validator;

    public function __construct(PdoRolesRepository $repository, RolesValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->repository->all($tenantId, $limit, $offset);
    }

    public function count(int $tenantId): int
    {
        return $this->repository->count($tenantId);
    }

    public function get(int $tenantId, int $id): array
    {
        $data = $this->repository->find($tenantId, $id);
        if (!$data) {
            throw new RuntimeException('Role not found');
        }
        return $data;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): array
    {
        $isUpdate = !empty($data['id']);

        if (!$this->validator->validate($data, $isUpdate ? 'update' : 'create')) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }

        $id = $this->repository->save($tenantId, $data, $userId);
        return $this->get($tenantId, $id);
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repository->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete role');
        }
    }

    public function getRolePermissions(int $tenantId, int $roleId): array
    {
        return $this->repository->getRolePermissions($tenantId, $roleId);
    }

    public function assignPermissions(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
    {
        $this->repository->assignPermissions($tenantId, $roleId, $permissionIds, $userId);
    }
}