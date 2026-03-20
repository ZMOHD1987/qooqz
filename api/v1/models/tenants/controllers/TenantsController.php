<?php
declare(strict_types=1);

final class TenantsController
{
    private TenantsService $service;

    public function __construct(TenantsService $service)
    {
        $this->service = $service;
    }

    /**
     * List tenants with pagination and filters
     */
    public function list(int $perPage = 10, int $offset = 0, array $filters = []): array
    {
        return $this->service->list($perPage, $offset, $filters);
    }

    /**
     * Get total count with filters
     */
    public function count(array $filters = []): int
    {
        return $this->service->count($filters);
    }

    /**
     * Get single tenant
     */
    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    /**
     * Get tenant by domain
     */
    public function getByDomain(string $domain): array
    {
        return $this->service->getByDomain($domain);
    }

    /**
     * Get all active tenants
     */
    public function getActive(): array
    {
        return $this->service->getActive();
    }

    /**
     * Create new tenant
     */
    public function create(array $data, ?int $actingUserId = null): array
    {
        $userId = $actingUserId ?? (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
        return $this->service->create($data, $userId);
    }

    /**
     * Update tenant
     */
    public function update(array $data, int $id, ?int $actingUserId = null): array
    {
        $userId = $actingUserId ?? (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
        return $this->service->update($data, $id, $userId);
    }

    /**
     * Delete tenant
     */
    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required');
        }

        $userId = isset($data['user_id']) ? (int)$data['user_id']
                : (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
        $this->service->delete((int)$data['id'], $userId);
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(array $data, ?int $actingUserId = null): array
    {
        if (empty($data['ids']) || !is_array($data['ids'])) {
            throw new InvalidArgumentException('IDs array is required');
        }

        $userId = $actingUserId ?? (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
        return $this->service->bulkUpdateStatus(
            $data['ids'],
            $data['status'],
            $userId
        );
    }

    /**
     * Activate multiple tenants (convenience wrapper)
     */
    public function activate(array $data, ?int $actingUserId = null): array
    {
        if (empty($data['ids']) || !is_array($data['ids'])) {
            throw new InvalidArgumentException('IDs array is required');
        }
        $userId = $actingUserId ?? (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
        return $this->service->bulkUpdateStatus($data['ids'], 'active', $userId);
    }

    /**
     * Suspend multiple tenants (convenience wrapper)
     */
    public function suspend(array $data, ?int $actingUserId = null): array
    {
        if (empty($data['ids']) || !is_array($data['ids'])) {
            throw new InvalidArgumentException('IDs array is required');
        }
        $userId = $actingUserId ?? (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
        return $this->service->bulkUpdateStatus($data['ids'], 'suspended', $userId);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return $this->service->getStats();
    }
}