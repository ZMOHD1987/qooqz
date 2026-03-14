<?php
declare(strict_types=1);

/**
 * Interface NotificationRecipientRepositoryInterface
 *
 * Contract for NotificationRecipients data access layer.
 *
 * Location: api/v1/models/notifications/notification_recipients/Contracts/NotificationRecipientRepositoryInterface.php
 */
interface NotificationRecipientRepositoryInterface
{
    /**
     * Retrieve a paginated, filtered list of notification recipients.
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param array    $filters  Keyed filters: notification_id, recipient_type, recipient_id, is_read
     * @param string   $orderBy
     * @param string   $orderDir
     * @return array<int, array<string, mixed>>
     */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'nr.id',
        string $orderDir = 'DESC'
    ): array;

    /**
     * Count total recipient rows, optionally filtered.
     *
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int;

    /**
     * Find a single recipient row by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array;

    /**
     * Create a new recipient row.
     *
     * @param array $data
     * @return int  The new row's ID
     * @throws InvalidArgumentException
     */
    public function create(array $data): int;

    /**
     * Update a recipient row (primarily mark as read).
     *
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(array $data): bool;

    /**
     * Mark a specific recipient row as read.
     *
     * @param int $id
     * @return bool
     */
    public function markRead(int $id): bool;

    /**
     * Mark all unread notifications as read for a given recipient.
     *
     * @param string $recipientType
     * @param int    $recipientId
     * @return int   Number of rows affected
     */
    public function markAllRead(string $recipientType, int $recipientId): int;

    /**
     * Delete a single recipient row by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
}
