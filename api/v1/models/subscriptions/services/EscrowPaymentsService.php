<?php
declare(strict_types=1);

class EscrowPaymentsService {
    private PdoEscrowPaymentsRepository $repo;

    public function __construct(PdoEscrowPaymentsRepository $repo) {
        $this->repo = $repo;
    }

    public function all(array $filters = []): array { return $this->repo->all($filters); }
    public function find(int $id): ?array { return $this->repo->find($id); }
    public function create(array $data): int { return $this->repo->create($data); }
    public function delete(int $id): bool { return $this->repo->delete($id); }
    public function markSuccess(int $id, string $gatewayTxnId): bool { return $this->repo->markSuccess($id, $gatewayTxnId); }
    public function markRefunded(int $id): bool { return $this->repo->markRefunded($id); }
    public function stats(array $filters = []): array { return $this->repo->stats($filters); }
}
