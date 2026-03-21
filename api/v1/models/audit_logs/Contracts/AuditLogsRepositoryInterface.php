<?php
declare(strict_types=1);

interface AuditLogsRepositoryInterface
{
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array;

    public function count(int $tenantId, array $filters = []): int;

    public function find(int $tenantId, int $id): ?array;

    /**
     * Persist an audit log entry.
     *
     * Required key:
     *   string  $data['action']          - e.g. "product.update"
     *
     * Optional keys:
     *   int     $data['tenant_id']
     *   int     $data['user_id']
     *   string  $data['entity_type']     - e.g. "product"
     *   int     $data['entity_id']
     *   string  $data['ip_address']
     *   string  $data['user_agent']
     *   array   $data['payload']         - free-form context bag (legacy)
     *   array   $data['old_values']      - snapshot BEFORE the change
     *   array   $data['new_values']      - snapshot AFTER the change
     *   array   $data['diff']            - [{field,old,new}] (auto-computed if absent)
     *   array   $data['metadata']        - arbitrary contextual key-value pairs
     *   string  $data['trace']           - optional stack trace / breadcrumb
     *   string  $data['http_method']     - HTTP verb (GET/POST/PUT/DELETE)
     *   string  $data['http_url']        - request path + query string
     *   string  $data['session_id']      - PHP session ID
     *   string  $data['request_id']      - unique request identifier (UUID)
     *   int     $data['duration_ms']     - operation duration in milliseconds
     */
    public function save(array $data): int;
}