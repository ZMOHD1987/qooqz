<?php
declare(strict_types=1);

class SubscriptionPaymentsService {
    private PdoSubscriptionPaymentsRepository $repo;

    public function __construct(PdoSubscriptionPaymentsRepository $repo) {
        $this->repo = $repo;
    }

    public function all(array $filters = []): array { return $this->repo->all($filters); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function create(array $data): int { return $this->repo->create($data); }
    public function update(int $id, array $data): bool { return $this->repo->update($id, $data); }
    public function delete(int $id): bool { return $this->repo->delete($id); }
    public function markSuccess(int $id, string $gatewayTxnId, string $gatewayResponse): bool { return $this->repo->markSuccess($id, $gatewayTxnId, $gatewayResponse); }
    public function markRefunded(int $id): bool { return $this->repo->markRefunded($id); }
    public function stats(array $filters = []): array { return $this->repo->stats($filters); }
}
