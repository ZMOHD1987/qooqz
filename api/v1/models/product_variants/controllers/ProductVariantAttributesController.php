<?php
declare(strict_types=1);

final class ProductVariantAttributesController
{
    private ProductVariantAttributesService $service;

    public function __construct(ProductVariantAttributesService $service)
    {
        $this->service = $service;
    }

    public function list(int $tenantId, ?int $limit=null, ?int $offset=null, array $filters=[], string $orderBy='id', string $orderDir='DESC'): array
    {
        return $this->service->list($tenantId,$limit,$offset,$filters,$orderBy,$orderDir);
    }

    public function count(int $tenantId,array $filters=[]): int
    {
        return $this->service->count($tenantId,$filters);
    }

    public function get(int $tenantId,int $id): ?array
    {
        return $this->service->get($tenantId,$id);
    }

    public function create(int $tenantId,array $data): int
    {
        return $this->service->create($tenantId,$data);
    }

    public function update(int $tenantId,array $data): int
    {
        return $this->service->update($tenantId,$data);
    }

    public function delete(int $tenantId,int $id): bool
    {
        return $this->service->delete($tenantId,$id);
    }
}
