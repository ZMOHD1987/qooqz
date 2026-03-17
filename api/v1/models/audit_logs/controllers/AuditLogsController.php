<?php
declare(strict_types=1);

final class AuditLogsController
{
    private AuditLogsService $service;

    public function __construct(AuditLogsService $service)
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
}