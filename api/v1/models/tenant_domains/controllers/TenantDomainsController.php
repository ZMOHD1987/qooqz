<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/TenantDomainsService.php';

/**
 * TenantDomainsController
 *
 * Thin HTTP adapter – receives parsed request data, delegates to
 * TenantDomainsService, and returns plain PHP arrays for the route
 * layer to serialise.
 */
final class TenantDomainsController
{
    private TenantDomainsService $service;

    public function __construct(TenantDomainsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->service->list($tenantId, $filters, $limit, $offset);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): array
    {
        return $this->service->create($data);
    }

    public function update(int $id, array $data): array
    {
        return $this->service->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->service->delete($id);
    }

    public function markVerified(int $id): array
    {
        return $this->service->markVerified($id);
    }

    public function updateSslStatus(int $id, array $data): array
    {
        $status    = $data['ssl_status'] ?? 'none';
        $expiresAt = $data['ssl_expires_at'] ?? null;
        return $this->service->updateSslStatus($id, $status, $expiresAt);
    }
}
