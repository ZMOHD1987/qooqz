<?php
declare(strict_types=1);

class EntityFinancialBalancesController {
    private EntityFinancialBalancesService $service;

    public function __construct(EntityFinancialBalancesService $service) {
        $this->service = $service;
    }

    public function list(array $filters = []): array { return $this->service->list($filters); }
    public function find(int $entityId): ?array { return $this->service->find($entityId); }
    public function upsert(int $entityId, array $data): bool { return $this->service->upsert($entityId, $data); }
    public function recalculate(int $entityId): bool { return $this->service->recalculate($entityId); }
}
