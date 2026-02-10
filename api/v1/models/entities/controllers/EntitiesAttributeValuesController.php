<?php
declare(strict_types=1);

final class EntitiesAttributeValuesController
{
    private EntitiesAttributeValuesService $service;

    public function __construct(EntitiesAttributeValuesService $service)
    {
        $this->service = $service;
    }

    /**
     * List attribute values with filters, ordering, and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    /**
     * Get a single attribute value by ID
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($id, $lang);
    }

    /**
     * Get attribute value by entity and attribute
     */
    public function getByEntityAndAttribute(int $entityId, int $attributeId, string $lang = 'ar'): ?array
    {
        return $this->service->getByEntityAndAttribute($entityId, $attributeId, $lang);
    }

    /**
     * Get all values for an entity
     */
    public function getEntityValues(int $entityId, string $lang = 'ar'): array
    {
        return $this->service->getEntityValues($entityId, $lang);
    }

    /**
     * Get all values for an attribute
     */
    public function getAttributeValues(int $attributeId, string $lang = 'ar'): array
    {
        return $this->service->getAttributeValues($attributeId, $lang);
    }

    /**
     * Create a new attribute value
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing attribute value
     */
    public function update(int $id, array $data): void
    {
        $this->service->update($id, $data);
    }

    /**
     * Save multiple values for an entity
     */
    public function saveEntityValues(int $entityId, array $values): array
    {
        return $this->service->saveEntityValues($entityId, $values);
    }

    /**
     * Delete an attribute value
     */
    public function delete(int $id): void
    {
        $this->service->delete($id);
    }

    /**
     * Delete all values for an entity
     */
    public function deleteEntityValues(int $entityId): void
    {
        $this->service->deleteEntityValues($entityId);
    }

    /**
     * Delete all values for an attribute
     */
    public function deleteAttributeValues(int $attributeId): void
    {
        $this->service->deleteAttributeValues($attributeId);
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        return $this->service->getStatistics();
    }
}