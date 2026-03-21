<?php
declare(strict_types=1);

final class AuditLogsService
{
    private PdoAuditLogsRepository $repo;

    public function __construct(PdoAuditLogsRepository $repo)
    {
        $this->repo = $repo;
    }

    // ─────────────────────────────────────────────────────────────
    // Static helper — call from any route without instantiating
    // ─────────────────────────────────────────────────────────────

    /**
     * Record an audit event.
     *
     * @param string      $action      e.g. "product.update"
     * @param string|null $entityType  e.g. "product"
     * @param int|null    $entityId    Primary key of the affected row
     * @param array|null  $payload     Legacy free-form context (still supported)
     * @param int|null    $tenantId    Falls back to session / globals
     * @param int|null    $userId      Falls back to session
     * @param array|null  $oldValues   Full entity snapshot BEFORE the change
     * @param array|null  $newValues   Full entity snapshot AFTER the change
     * @param array|null  $metadata    Arbitrary key-value context bag
     * @param int|null    $durationMs  How long the operation took (ms)
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $payload = null,
        ?int $tenantId = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?int $durationMs = null
    ): void {
        if (!isset($GLOBALS['ADMIN_DB']) || !$GLOBALS['ADMIN_DB'] instanceof PDO) {
            error_log('AuditLog Error: Database connection not found in GLOBALS.');
            return;
        }

        try {
            $pdo  = $GLOBALS['ADMIN_DB'];
            $repo = new PdoAuditLogsRepository($pdo);

            // Build request URL (path + query string)
            $httpUrl = ($_SERVER['REQUEST_URI'] ?? null);

            // Unique request ID: reuse one generated per PHP process if available
            if (!isset($GLOBALS['_AUDIT_REQUEST_ID'])) {
                $GLOBALS['_AUDIT_REQUEST_ID'] = sprintf(
                    '%08x-%04x-%04x-%04x-%012x',
                    random_int(0, 0xffffffff),
                    random_int(0, 0xffff),
                    random_int(0x4000, 0x4fff),
                    random_int(0x8000, 0xbfff),
                    random_int(0, 0xffffffffffff)
                );
            }

            $data = [
                'tenant_id'   => $tenantId  ?? ($GLOBALS['TENANT_ID'] ?? ($_SESSION['tenant_id'] ?? null)),
                'user_id'     => $userId    ?? ($_SESSION['user_id']   ?? null),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'payload'     => $payload,
                'old_values'  => $oldValues,
                'new_values'  => $newValues,
                // diff is computed by the repository when old/new are provided
                'metadata'    => $metadata,
                'ip_address'  => $_SERVER['REMOTE_ADDR']     ?? null,
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'http_method' => $_SERVER['REQUEST_METHOD']  ?? null,
                'http_url'    => $httpUrl,
                'session_id'  => session_id() ?: null,
                'request_id'  => $GLOBALS['_AUDIT_REQUEST_ID'],
                'duration_ms' => $durationMs,
            ];

            $repo->save($data);
        } catch (\Throwable $e) {
            // Audit logging must never break the main flow
            error_log('AuditLog Exception: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Standard API methods (used by the controller)
    // ─────────────────────────────────────────────────────────────

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $id): array
    {
        $data = $this->repo->find($tenantId, $id);
        if (!$data) {
            throw new \RuntimeException('Log entry not found');
        }
        return $data;
    }
}