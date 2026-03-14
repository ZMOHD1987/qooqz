<?php
declare(strict_types=1);

/**
 * Interface NotificationTypeRepositoryInterface
 *
 * Contract for NotificationTypes data access layer.
 *
 * Location: api/v1/models/notifications/notification_types/Contracts/NotificationTypeRepositoryInterface.php
 */
interface NotificationTypeRepositoryInterface
{
    /**
     * Retrieve a paginated list of notification types.
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param array    $filters  is_active
     * @param string   $orderBy
     * @param string   $orderDir
     * @return array<int, array<string, mixed>>
     */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'nt.id',
        string $orderDir = 'ASC'
    ): array;

    /**
     * Count total type rows.
     *
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int;

    /**
     * Find a notification type by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array;

    /**
     * Find a notification type by its unique code.
     *
     * @param string $code
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array;

    /**
     * Create a new notification type.
     *
     * @param array $data
     * @return int
     * @throws InvalidArgumentException
     */
    public function create(array $data): int;

    /**
     * Update an existing notification type.
     *
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(array $data): bool;

    /**
     * Delete a notification type by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
}
