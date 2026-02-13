<?php
declare(strict_types=1);

class EscrowPaymentsController {
    private EscrowPaymentsService $service;

    public function __construct(EscrowPaymentsService $service) {
        $this->service = $service;
    }

    public function all(array $filters = []): array { return $this->service->all($filters); }
    public function find(int $id): ?array { return $this->service->find($id); }
    public function create(array $data): int { return $this->service->create($data); }
    public function delete(int $id): bool { return $this->service->delete($id); }
    public function markSuccess(int $id, string $gatewayTxnId): bool { return $this->service->markSuccess($id, $gatewayTxnId); }
    public function markRefunded(int $id): bool { return $this->service->markRefunded($id); }
    public function stats(array $filters = []): array { return $this->service->stats($filters); }
}
