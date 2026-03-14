<?php
declare(strict_types=1);

/**
 * DeliveryProviderService
 *
 * Business logic layer for Delivery Providers.
 *
 * Location: api/v1/models/delivery_zones/services/DeliveryProviderService.php
 */
final class DeliveryProviderService
{
    private DeliveryProviderRepositoryInterface $repo;
    private DeliveryProviderValidator $validator;

    public function __construct(
        DeliveryProviderRepositoryInterface $repo,
        DeliveryProviderValidator $validator
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

    public function get(int $tenantId, int $id, string $lang): ?array
    {
        return $this->repo->find($tenantId, $id, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID required for update.');
        }
        $this->validator->validate($data, true);
        return $this->repo->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }
}