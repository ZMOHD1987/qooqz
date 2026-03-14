<?php
declare(strict_types=1);

class EntityFinancialBalancesService {
    private PdoEntityFinancialBalancesRepository $repo;

    public function __construct(PdoEntityFinancialBalancesRepository $repo) {
        $this->repo = $repo;
    }

    public function list(array $filters = []): array { return $this->repo->list(isset($filters['limit']) ? (int)$filters['limit'] : null, isset($filters['offset']) ? (int)$filters['offset'] : null, $filters); }
    public function find(int $entityId): ?array { return $this->repo->find($entityId); }
    public function upsert(int $entityId, array $data): bool { return $this->repo->upsert($entityId, $data); }
    public function recalculate(int $entityId): bool { return $this->repo->recalculate($entityId); }
}
