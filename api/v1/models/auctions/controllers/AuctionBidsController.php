<?php
declare(strict_types=1);

final class AuctionBidsController
{
    private AuctionBidsService $service;

    public function __construct(AuctionBidsService $service)
    {
        $this->service = $service;
    }

    public function list(
        int $auctionId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->service->list($auctionId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->service->count($auctionId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
