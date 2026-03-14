<?php
declare(strict_types=1);

final class AuctionActivityLogController
{
    private AuctionActivityLogService $service;

    public function __construct(AuctionActivityLogService $service)
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

    public function log(array $data): int
    {
        return $this->service->log($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
