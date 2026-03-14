<?php
declare(strict_types=1);

final class AuctionWatchersService
{
    private PdoAuctionWatchersRepository $repo;

    public function __construct(PdoAuctionWatchersRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(int $auctionId): array
    {
        return $this->repo->all($auctionId);
    }

    public function get(int $auctionId, int $userId): array
    {
        $data = $this->repo->find($auctionId, $userId);
        if (!$data) {
            throw new RuntimeException('Auction watcher not found');
        }
        return $data;
    }

    public function save(array $data): int
    {
        $validator = new AuctionWatchersValidator();
        if (!$validator->validate($data)) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
        return $this->repo->save($data);
    }

    public function delete(int $auctionId, int $userId): bool
    {
        return $this->repo->delete($auctionId, $userId);
    }
}
