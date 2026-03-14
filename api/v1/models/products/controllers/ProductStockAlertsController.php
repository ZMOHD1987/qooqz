<?php
declare(strict_types=1);

final class ProductStockAlertsController
{
    private ProductStockAlertsService $service;
    private ProductStockAlertsValidator $validator;

    public function __construct(ProductStockAlertsService $service, ProductStockAlertsValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    public function list(array $filters = [], ?int $limit = null, ?int $offset = null, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        $items = $this->service->list($filters, $limit, $offset, $orderBy, $orderDir);
        $total = $this->service->count($filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    public function create(array $data): int
    {
        $this->validator->validate($data);
        return $this->service->create($data);
    }

    public function update(array $data): int
    {
        $this->validator->validate($data, true);
        return $this->service->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}