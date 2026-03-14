<?php
declare(strict_types=1);

interface DriverLocationRepositoryInterface
{
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'dl.id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    public function count(int $tenantId, array $filters = []): int;

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array;

    public function findByProviderId(int $tenantId, int $providerId): ?array;

    public function create(int $tenantId, array $data): int;

    public function update(int $tenantId, array $data): bool;

    public function delete(int $tenantId, int $id): bool;
}