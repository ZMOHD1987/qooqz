<?php
declare(strict_types=1);

final class RolePermissionsController
{
    private RolePermissionsService $service;

    public function __construct(RolePermissionsService $service)
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

    public function assign(int $tenantId, array $data, ?int $userId = null): array
    {
        return $this->service->assign($tenantId, $data, $userId);
    }

    public function delete(int $tenantId, array $data, ?int $userId = null): void
    {
        $this->service->delete($tenantId, $data, $userId);
    }

    public function assignMultiple(int $tenantId, int $roleId, array $permissionIds, ?int $userId = null): void
    {
        $this->service->assignMultiple($tenantId, $roleId, $permissionIds, $userId);
    }
}