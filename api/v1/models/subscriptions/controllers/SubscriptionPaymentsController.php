<?php
declare(strict_types=1);

class SubscriptionPaymentsController {
    private SubscriptionPaymentsService $service;

    public function __construct(SubscriptionPaymentsService $service) {
        $this->service = $service;
    }

    public function all(array $filters = []): array { return $this->service->all($filters); }
    public function find(int $id): ?array { return $this->service->find($id); }
    public function create(array $data): int { return $this->service->create($data); }
    public function update(int $id, array $data): bool { return $this->service->update($id, $data); }
    public function delete(int $id): bool { return $this->service->delete($id); }
    public function markSuccess(int $id, string $gatewayTxnId, string $gatewayResponse): bool { return $this->service->markSuccess($id, $gatewayTxnId, $gatewayResponse); }
    public function markRefunded(int $id): bool { return $this->service->markRefunded($id); }
    public function stats(array $filters = []): array { return $this->service->stats($filters); }
}
