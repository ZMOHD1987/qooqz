<?php
declare(strict_types=1);

/**
 * ProviderZoneService
 *
 * Business logic layer for Provider Zones assignments.
 *
 * Location: api/v1/models/provider_zones/services/ProviderZoneService.php
 */
final class ProviderZoneService
{
    private ProviderZoneRepositoryInterface $repo;
    private ProviderZoneValidator $validator;

    public function __construct(
        ProviderZoneRepositoryInterface $repo,
        ProviderZoneValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    public function count(int $tenantId, array $filters): int
    {
        return $this->repo->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $providerId, int $zoneId, string $lang): ?array
    {
        return $this->repo->find($tenantId, $providerId, $zoneId, $lang);
    }

    public function create(int $tenantId, array $data): bool
    {
        $this->validator->validate($data);
        return $this->repo->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        $this->validator->validate($data, true);
        return $this->repo->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $providerId, int $zoneId): bool
    {
        return $this->repo->delete($tenantId, $providerId, $zoneId);
    }
}