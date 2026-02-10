<?php
declare(strict_types=1);

final class EntitiesService
{
    public function __construct(
        private PdoEntitiesRepository $repo
    ) {}

    public function list(...$args): array
    {
        return [
            'items'=>$this->repo->all(...$args),
            'total'=>$this->repo->count($args[0],$args[3])
        ];
    }

    public function get(int $tenantId,int $id,string $lang): ?array
    {
        return $this->repo->find($tenantId,$id,$lang);
    }

    public function save(int $tenantId,array $data,string $lang): int
    {
        $id = $this->repo->save($tenantId,$data);
        if (!empty($data['store_name'])) {
            $this->repo->saveTranslation($id,$lang,$data);
        }
        return $id;
    }

    public function delete(int $tenantId,int $id): bool
    {
        return $this->repo->delete($tenantId,$id);
    }
}
