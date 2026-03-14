<?php
declare(strict_types=1);

final class PaymentMethodsController
{
    private PaymentMethodsService $service;

    public function __construct(PaymentMethodsService $service)
    {
        $this->service = $service;
    }

    public function list(?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function find(int $id): ?array
    {
        return $this->service->find($id);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->service->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}
