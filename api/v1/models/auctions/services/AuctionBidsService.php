<?php
declare(strict_types=1);

final class AuctionBidsService
{
    private PdoAuctionBidsRepository $repo;

    public function __construct(PdoAuctionBidsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $auctionId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->repo->all($auctionId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(int $auctionId, array $filters = []): int
    {
        return $this->repo->count($auctionId, $filters);
    }

    public function get(int $id): array
    {
        $data = $this->repo->find($id);
        if (!$data) {
            throw new RuntimeException('Auction bid not found');
        }
        return $data;
    }

    public function create(array $data): int
    {
        $validator = new AuctionBidsValidator();
        if (!$validator->validate($data, 'create')) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
