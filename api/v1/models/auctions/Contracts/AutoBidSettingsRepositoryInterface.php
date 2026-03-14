<?php
declare(strict_types=1);

interface AutoBidSettingsRepositoryInterface
{
    public function all(
        int $auctionId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array;

    public function count(int $auctionId, array $filters = []): int;

    public function find(int $id): ?array;

    public function findByUser(int $auctionId, int $userId): ?array;

    public function save(array $data): int;

    public function delete(int $id): bool;
}
