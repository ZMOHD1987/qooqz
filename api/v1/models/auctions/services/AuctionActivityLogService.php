<?php
declare(strict_types=1);

final class AuctionActivityLogService
{
    private PdoAuctionActivityLogRepository $repo;

    public function __construct(PdoAuctionActivityLogRepository $repo)
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
            throw new RuntimeException('Auction activity log entry not found');
        }
        return $data;
    }

    public function log(array $data): int
    {
        $validator = new AuctionActivityLogValidator();
        if (!$validator->validate($data)) {
            throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
        }
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
