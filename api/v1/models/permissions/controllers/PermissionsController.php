<?php
declare(strict_types=1);

final class PermissionsController
{
    private PermissionsService $service;

    public function __construct(PermissionsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        return $this->service->list($tenantId, $limit, $offset, $filters);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->service->count($tenantId, $filters);
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
}