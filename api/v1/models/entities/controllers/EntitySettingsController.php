<?php
declare(strict_types=1);

final class EntitySettingsController
{
    private EntitySettingsService $service;

    public function __construct(EntitySettingsService $service)
    {
        $this->service = $service;
    }

    /**
     * List entity settings with filters, ordering, and pagination
     *
     * @param int|null $limit
     * @param int|null $offset
     * @param array $filters
     * @param string $orderBy
     * @param string $orderDir
     * @return array
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'entity_id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    /**
     * Get a single entity setting by ID
     *
     * @param int $entityId
     * @return array|null
     */
    public function get(int $entityId): ?array
    {
        return $this->service->get($entityId);
    }

    /**
     * Create new entity settings
     *
     * @param int $entityId
     * @param array $data
     * @return bool
     */
    public function create(int $entityId, array $data): bool
    {
        return $this->service->create($entityId, $data);
    }

    /**
     * Update existing entity settings
     *
     * @param int $entityId
     * @param array $data
     * @return bool
     */
    public function update(int $entityId, array $data): bool
    {
        return $this->service->update($entityId, $data);
    }

    /**
     * Delete entity settings by ID
     *
     * @param int $entityId
     * @return bool
     */
    public function delete(int $entityId): bool
    {
        return $this->service->delete($entityId);
    }

    /**
     * Toggle boolean field value
     *
     * @param int $entityId
     * @param string $field
     * @return bool
     */
    public function toggle(int $entityId, string $field): bool
    {
        return $this->service->toggle($entityId, $field);
    }
}