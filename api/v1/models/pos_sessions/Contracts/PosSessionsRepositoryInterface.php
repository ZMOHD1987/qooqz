<?php
declare(strict_types=1);

/**
 * Contract for POS Sessions data access layer.
 * Any implementation (PDO, mock, etc.) must satisfy this interface.
 */
interface PosSessionsRepositoryInterface
{
    /**
     * Retrieve a paginated, filtered list of POS sessions for a tenant.
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'ps.opened_at',
        string $orderDir = 'DESC'
    ): array;

    /**
     * Count total sessions for a tenant, optionally filtered.
     */
    public function count(int $tenantId, array $filters = []): int;

    /**
     * Find a single POS session by ID within a tenant scope.
     */
    public function find(int $tenantId, int $id): ?array;

    /**
     * Find the current open session for a tenant/entity.
     */
    public function findOpen(int $tenantId, ?int $entityId = null): ?array;

    /**
     * Open a new POS session.
     *
     * @return int  New session ID
     */
    public function open(int $tenantId, array $data): int;

    /**
     * Close a POS session by ID.
     */
    public function close(int $tenantId, int $sessionId, array $data): bool;

    /**
     * Create a POS order within a session (insert order + items, update session totals).
     *
     * @return array  ['order_id' => int, 'order_number' => string, 'grand_total' => float, 'change' => float]
     */
    public function createOrder(int $tenantId, array $data): array;

    /**
     * Retrieve orders for a given POS session.
     */
    public function sessionOrders(int $tenantId, int $sessionId): array;
}
