<?php
declare(strict_types=1);

final class Product_categoriesController
{
    private Product_categoriesService $service;

    public function __construct(Product_categoriesService $service)
    {
        $this->service = $service;
    }

    public function list(?int $limit = null, ?int $offset = null, array $filters = [], string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(array $filters = []): int
    {
        return $this->service->count($filters);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): array
    {
        return $this->service->save($data);
    }

    public function update(array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }
        return $this->service->save($data);
    }

    public function delete(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for delete');
        }
        $this->service->delete((int)$data['id']);
    }
}
