<?php
declare(strict_types=1);

final class AuctionWatchersController
{
    private AuctionWatchersService $service;

    public function __construct(AuctionWatchersService $service)
    {
        $this->service = $service;
    }

    public function list(int $auctionId): array
    {
        return $this->service->list($auctionId);
    }

    public function get(int $auctionId, int $userId): array
    {
        return $this->service->get($auctionId, $userId);
    }

    public function save(array $data): int
    {
        return $this->service->save($data);
    }

    public function delete(int $auctionId, int $userId): bool
    {
        return $this->service->delete($auctionId, $userId);
    }
}
