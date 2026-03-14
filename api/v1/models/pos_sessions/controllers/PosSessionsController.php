<?php
declare(strict_types=1);

final class PosSessionsController
{
    private PosSessionsService $service;

    public function __construct(PosSessionsService $service)
    {
        $this->service = $service;
    }

    /* ────────────────────────────────────────────────────
     * GET ?action=list
     * ──────────────────────────────────────────────────── */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'ps.opened_at',
        string $orderDir = 'DESC'
    ): array {
        $total   = $this->service->count($tenantId, $filters);
        $sessions = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $perPage  = $limit ?? $total;

        return [
            'sessions'    => $sessions,
            'total'       => $total,
            'page'        => $perPage > 0 ? (int)ceil(($offset ?? 0) / $perPage) + 1 : 1,
            'per_page'    => $perPage,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /* ────────────────────────────────────────────────────
     * GET ?action=current
     * ──────────────────────────────────────────────────── */
    public function current(int $tenantId, ?int $entityId = null): array
    {
        return ['session' => $this->service->getCurrent($tenantId, $entityId)];
    }

    /* ────────────────────────────────────────────────────
     * GET ?id=N
     * ──────────────────────────────────────────────────── */
    public function get(int $tenantId, int $id): array
    {
        return ['session' => $this->service->get($tenantId, $id)];
    }

    /* ────────────────────────────────────────────────────
     * GET ?action=session_orders&session_id=N
     * ──────────────────────────────────────────────────── */
    public function sessionOrders(int $tenantId, int $sessionId): array
    {
        return ['orders' => $this->service->sessionOrders($tenantId, $sessionId)];
    }

    /* ────────────────────────────────────────────────────
     * POST action=open
     * ──────────────────────────────────────────────────── */
    public function open(int $tenantId, array $data): array
    {
        $session = $this->service->open($tenantId, $data);
        return ['session' => $session, 'message' => 'Session opened successfully'];
    }

    /* ────────────────────────────────────────────────────
     * POST action=close
     * ──────────────────────────────────────────────────── */
    public function close(int $tenantId, array $data): array
    {
        $session = $this->service->close($tenantId, $data);
        return ['session' => $session, 'message' => 'Session closed successfully'];
    }

    /* ────────────────────────────────────────────────────
     * POST action=create_order
     * ──────────────────────────────────────────────────── */
    public function createOrder(int $tenantId, array $data): array
    {
        $result = $this->service->createOrder($tenantId, $data);
        return array_merge($result, ['message' => 'Order created successfully']);
    }
}
