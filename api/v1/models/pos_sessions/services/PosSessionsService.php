<?php
declare(strict_types=1);

final class PosSessionsService
{
    private PdoPosSessionsRepository $repository;
    private PosSessionsValidator $validator;

    public function __construct(PdoPosSessionsRepository $repository, PosSessionsValidator $validator)
    {
        $this->repository = $repository;
        $this->validator  = $validator;
    }

    /* ────────────────────────────────────────────────────
     * List sessions (paginated)
     * ──────────────────────────────────────────────────── */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'ps.opened_at',
        string $orderDir = 'DESC'
    ): array {
        return $this->repository->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    /* ────────────────────────────────────────────────────
     * Count sessions
     * ──────────────────────────────────────────────────── */
    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repository->count($tenantId, $filters);
    }

    /* ────────────────────────────────────────────────────
     * Get single session
     * ──────────────────────────────────────────────────── */
    public function get(int $tenantId, int $id): array
    {
        $session = $this->repository->find($tenantId, $id);
        if (!$session) {
            throw new RuntimeException('POS session not found');
        }
        return $session;
    }

    /* ────────────────────────────────────────────────────
     * Get current open session
     * ──────────────────────────────────────────────────── */
    public function getCurrent(int $tenantId, ?int $entityId = null): ?array
    {
        return $this->repository->findOpen($tenantId, $entityId);
    }

    /* ────────────────────────────────────────────────────
     * Open a session
     * ──────────────────────────────────────────────────── */
    public function open(int $tenantId, array $data): array
    {
        if (!$this->validator->validate($data, 'open')) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }
        $id = $this->repository->open($tenantId, $data);
        return $this->get($tenantId, $id);
    }

    /* ────────────────────────────────────────────────────
     * Close a session
     * ──────────────────────────────────────────────────── */
    public function close(int $tenantId, array $data): array
    {
        if (!$this->validator->validate($data, 'close')) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }
        $sessionId = (int)$data['session_id'];
        $this->repository->close($tenantId, $sessionId, $data);
        return $this->get($tenantId, $sessionId);
    }

    /* ────────────────────────────────────────────────────
     * Create a POS order
     * ──────────────────────────────────────────────────── */
    public function createOrder(int $tenantId, array $data): array
    {
        if (!$this->validator->validate($data, 'create_order')) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }
        return $this->repository->createOrder($tenantId, $data);
    }

    /* ────────────────────────────────────────────────────
     * Get orders for a session
     * ──────────────────────────────────────────────────── */
    public function sessionOrders(int $tenantId, int $sessionId): array
    {
        return $this->repository->sessionOrders($tenantId, $sessionId);
    }
}
