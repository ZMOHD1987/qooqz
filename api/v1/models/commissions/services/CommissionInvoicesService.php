<?php
declare(strict_types=1);

class CommissionInvoicesService {
    private PdoCommissionInvoicesRepository $repo;

    public function __construct(PdoCommissionInvoicesRepository $repo) {
        $this->repo = $repo;
    }

    public function list(array $filters = []): array { return $this->repo->list(isset($filters['limit']) ? (int)$filters['limit'] : null, isset($filters['offset']) ? (int)$filters['offset'] : null, $filters); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function create(array $data): int { return $this->repo->create($data); }
    public function update(int $id, array $data): bool { return $this->repo->update($id, $data); }
    public function delete(int $id): bool { return $this->repo->delete($id); }
    public function stats(?int $tenantId = null, ?int $entityId = null): array { return $this->repo->stats($tenantId, $entityId); }
    public function generateInvoiceNumber(): string { return $this->repo->generateInvoiceNumber(); }
}
