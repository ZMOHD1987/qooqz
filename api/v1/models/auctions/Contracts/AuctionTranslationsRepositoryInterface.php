<?php
declare(strict_types=1);

interface AuctionTranslationsRepositoryInterface
{
    public function all(int $auctionId): array;

    public function find(int $auctionId, string $languageCode): ?array;

    public function save(array $data): int;

    public function delete(int $auctionId, string $languageCode): bool;
}
