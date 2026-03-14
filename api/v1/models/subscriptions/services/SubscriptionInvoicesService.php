<?php
declare(strict_types=1);

class SubscriptionInvoicesService {
    private PdoSubscriptionInvoicesRepository $repo;

    public function __construct(PdoSubscriptionInvoicesRepository $repo) {
        $this->repo = $repo;
    }

    public function list(array $filters = []): array { return $this->repo->list(isset($filters['limit']) ? (int)$filters['limit'] : null, isset($filters['offset']) ? (int)$filters['offset'] : null, $filters); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function create(array $data): int { return $this->repo->create($data); }
    public function update(int $id, array $data): bool { return $this->repo->update($id, $data); }
    public function markPaid(int $id, string $paymentMethod, string $transactionId): bool { return $this->repo->markPaid($id, $paymentMethod, $transactionId); }
    public function delete(int $id): bool { return $this->repo->delete($id); }
    public function stats(): array { return $this->repo->stats(); }
}
