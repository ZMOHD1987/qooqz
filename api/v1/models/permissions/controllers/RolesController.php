<?php
declare(strict_types=1);

final class RolesController
{
    private RolesService $service;

    public function __construct(RolesService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->service->list($tenantId, $limit, $offset);
    }

    public function count(int $tenantId): int
    {
        return $this->service->count($tenantId);
    }

    public function get(int $tenantId, int $id): array
    {
        return $this->service->get($tenantId, $id);
    }

    public function create(int $tenantId, array $data, ?int $userId = null): array
    {
        return $this->service->save($tenantId, $data, $userId);
    }

    public function update(int $tenantId, array $data, ?int $userId = null): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        return $this->service->save($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, array $data, ?int $userId = null): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $this->service->delete($tenantId, (int)$data['id'], $userId);
    }

    public function getPermissions(int $tenantId, int $roleId): array
    {
        return $this->service->getRolePermissions($tenantId, $roleId);
    }

    public function assignPermissions(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
    {
        $this->service->assignPermissions($tenantId, $roleId, $permissionIds, $userId);
    }
}