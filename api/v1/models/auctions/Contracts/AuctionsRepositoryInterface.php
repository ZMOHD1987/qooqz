<?php
declare(strict_types=1);

interface AuctionsRepositoryInterface
{
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array;

    public function count(int $tenantId, array $filters = []): int;

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array;

    public function save(int $tenantId, array $data): int;

    public function delete(int $tenantId, int $id): bool;
}