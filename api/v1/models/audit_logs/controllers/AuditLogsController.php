<?php
declare(strict_types=1);

final class AuditLogsController
{
    private AuditLogsService $service;

    public function __construct(AuditLogsService $service)
    {
        $this->service = $service;
    }

    /**
     * Return paginated list of audit log entries.
     */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    /**
     * Return a single audit log entry by ID.
     * The full diff/old_values/new_values/metadata are included.
     */
    public function get(int $tenantId, int $id): array
    {
        return $this->service->get($tenantId, $id);
    }
}