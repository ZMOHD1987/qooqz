<?php
declare(strict_types=1);

interface AuctionWatchersRepositoryInterface
{
    public function all(int $auctionId): array;

    public function find(int $auctionId, int $userId): ?array;

    public function save(array $data): int;

    public function delete(int $auctionId, int $userId): bool;
}
