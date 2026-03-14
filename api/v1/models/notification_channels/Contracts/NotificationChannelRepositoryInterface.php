<?php
declare(strict_types=1);

/**
 * Interface NotificationChannelRepositoryInterface
 *
 * Contract for NotificationChannels data access layer.
 *
 * Location: api/v1/models/notifications/notification_channels/Contracts/NotificationChannelRepositoryInterface.php
 */
interface NotificationChannelRepositoryInterface
{
    /**
     * Retrieve a paginated list of notification channels.
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
        string $orderBy = 'nc.id',
        string $orderDir = 'ASC'
    ): array;

    /**
     * Count total channel rows.
     *
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int;

    /**
     * Find a channel by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array;

    /**
     * Find a channel by its unique code.
     *
     * @param string $code
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array;

    /**
     * Create a new channel.
     *
     * @param array $data
     * @return int
     * @throws InvalidArgumentException
     */
    public function create(array $data): int;

    /**
     * Update an existing channel.
     *
     * @param array $data
     * @return bool
     * @throws InvalidArgumentException
     */
    public function update(array $data): bool;

    /**
     * Delete a channel by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
}
