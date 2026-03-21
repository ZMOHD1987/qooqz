<?php
declare(strict_types=1);

final class EscrowDisputeEvidenceController
{
    private EscrowDisputeEvidenceService $service;

    public function __construct(EscrowDisputeEvidenceService $service)
    {
        $this->service = $service;
    }

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->service->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $id): array
    {
        return $this->service->get($tenantId, $id);
    }

    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }
}
