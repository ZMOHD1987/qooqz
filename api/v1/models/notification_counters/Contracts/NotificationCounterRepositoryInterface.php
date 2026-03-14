<?php
declare(strict_types=1);

/**
 * Interface NotificationCounterRepositoryInterface
 *
 * Contract for NotificationCounters data access layer.
 *
 * Location: api/v1/models/notifications/notification_counters/Contracts/NotificationCounterRepositoryInterface.php
 */
interface NotificationCounterRepositoryInterface
{
    /**
     * Retrieve a paginated list of counter rows for a tenant.
     *
     * @param int      $tenantId
     * @param int|null $limit
     * @param int|null $offset
     * @param array    $filters  recipient_type, recipient_id
     * @param string   $orderBy
     * @param string   $orderDir
     * @return array<int, array<string, mixed>>
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'nc.id',
        string $orderDir = 'DESC'
    ): array;

    /**
     * Count total counter rows for a tenant.
     *
     * @param int   $tenantId
     * @param array $filters
     * @return int
     */
    public function count(int $tenantId, array $filters = []): int;

    /**
     * Find a counter row by ID within a tenant scope.
     *
     * @param int $tenantId
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $id): ?array;

    /**
     * Get unread_count for a specific recipient.
     *
     * @param int    $tenantId
     * @param string $recipientType
     * @param int    $recipientId
     * @return int
     */
    public function getUnreadCount(int $tenantId, string $recipientType, int $recipientId): int;

    /**
     * Increment unread counter for a recipient (INSERT or UPDATE).
     *
     * @param int    $tenantId
     * @param string $recipientType
     * @param int    $recipientId
     * @param int    $amount
     * @return bool
     */
    public function increment(int $tenantId, string $recipientType, int $recipientId, int $amount = 1): bool;

    /**
     * Reset unread counter to zero for a recipient.
     *
     * @param int    $tenantId
     * @param string $recipientType
     * @param int    $recipientId
     * @return bool
     */
    public function reset(int $tenantId, string $recipientType, int $recipientId): bool;

    /**
     * Create a counter row.
     *
     * @param int   $tenantId
     * @param array $data
     * @return int
     */
    public function create(int $tenantId, array $data): int;

    /**
     * Update a counter row.
     *
     * @param int   $tenantId
     * @param array $data
     * @return bool
     */
    public function update(int $tenantId, array $data): bool;

    /**
     * Delete a counter row.
     *
     * @param int $tenantId
     * @param int $id
     * @return bool
     */
    public function delete(int $tenantId, int $id): bool;
}
