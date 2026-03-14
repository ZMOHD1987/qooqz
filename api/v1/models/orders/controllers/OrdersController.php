<?php
declare(strict_types=1);

final class OrdersController
{
    private OrdersService $service;

    public function __construct(OrdersService $service)
    {
        $this->service = $service;
    }

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($tenantId, $id, $lang);
    }

    public function getByOrderNumber(int $tenantId, string $orderNumber): ?array
    {
        return $this->service->getByOrderNumber($tenantId, $orderNumber);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }
}
