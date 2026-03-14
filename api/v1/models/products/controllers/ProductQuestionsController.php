<?php
declare(strict_types=1);

final class ProductQuestionsController
{
    private ProductQuestionsService $service;

    public function __construct(ProductQuestionsService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir, string $lang): array
    {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $id, string $lang): ?array
    {
        return $this->service->get($tenantId, $id, $lang);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): bool
    {
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }

    public function deleteByProduct(int $tenantId, int $productId): bool
    {
        return $this->service->deleteByProduct($tenantId, $productId);
    }

    public function incrementHelpful(int $tenantId, int $id): bool
    {
        return $this->service->incrementHelpful($tenantId, $id);
    }
}