<?php
declare(strict_types=1);

final class ProductVariantAttributesService
{
    private PdoProductVariantAttributesRepository $repo;
    private ProductVariantAttributesValidator $validator;

    public function __construct(PdoProductVariantAttributesRepository $repo, ProductVariantAttributesValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?int $limit=null, ?int $offset=null, array $filters=[], string $orderBy='id', string $orderDir='DESC'): array
    {
        return $this->repo->all($tenantId,$limit,$offset,$filters,$orderBy,$orderDir);
    }

    public function count(int $tenantId, array $filters=[]): int
    {
        return $this->repo->count($tenantId,$filters);
    }

    public function get(int $tenantId,int $id): ?array
    {
        return $this->repo->find($tenantId,$id);
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data);
        return $this->repo->save($tenantId,$data);
    }

    public function update(int $tenantId,array $data): int
    {
        if(empty($data['id'])) throw new InvalidArgumentException("ID is required.");
        $this->validator->validate($data,true);
        return $this->repo->save($tenantId,$data);
    }

    public function delete(int $tenantId,int $id): bool
    {
        return $this->repo->delete($tenantId,$id);
    }
}
