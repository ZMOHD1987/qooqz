<?php
declare(strict_types=1);

class SubscriptionInvoicesController {
    private SubscriptionInvoicesService $service;

    public function __construct(SubscriptionInvoicesService $service) {
        $this->service = $service;
    }

    public function list(array $filters = []): array { return $this->service->list($filters); }
    public function find(int $id): ?array { return $this->service->find($id); }
    public function create(array $data): int { return $this->service->create($data); }
    public function update(int $id, array $data): bool { return $this->service->update($id, $data); }
    public function markPaid(int $id, string $paymentMethod, string $transactionId): bool { return $this->service->markPaid($id, $paymentMethod, $transactionId); }
    public function delete(int $id): bool { return $this->service->delete($id); }
    public function stats(): array { return $this->service->stats(); }
}
