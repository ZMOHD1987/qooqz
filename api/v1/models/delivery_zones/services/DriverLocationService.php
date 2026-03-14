<?php
declare(strict_types=1);

final class DriverLocationService
{
    private DriverLocationRepositoryInterface $repo;
    private DriverLocationValidator $validator;

    public function __construct(
        DriverLocationRepositoryInterface $repo,
        DriverLocationValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
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

        // If the provider already has a location record, update it instead of inserting a duplicate
        if (isset($data['provider_id']) && (int)$data['provider_id'] > 0) {
            $existing = $this->repo->findByProviderId($tenantId, (int)$data['provider_id']);
            if ($existing) {
                $data['id'] = $existing['id'];
                $this->repo->update($tenantId, $data);
                return (int)$existing['id'];
            }
        }

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