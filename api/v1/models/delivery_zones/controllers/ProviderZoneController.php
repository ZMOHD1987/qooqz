<?php
declare(strict_types=1);

final class ProviderZoneController
{
    private ProviderZoneService $service;

    public function __construct(ProviderZoneService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $providerId, int $zoneId, string $lang): ?array
    {
        return $this->service->get($tenantId, $providerId, $zoneId, $lang);
    }

    public function create(int $tenantId, array $data): bool
    {
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $providerId, int $zoneId): bool
    {
        return $this->service->delete($tenantId, $providerId, $zoneId);
    }
}