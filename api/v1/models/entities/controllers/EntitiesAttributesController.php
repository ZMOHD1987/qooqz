<?php
declare(strict_types=1);

final class EntitiesAttributesController
{
    private EntitiesAttributesService $service;

    public function __construct(EntitiesAttributesService $service)
    {
        $this->service = $service;
    }

    /**
     * List attributes with filters, ordering, and pagination
     */
    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC',
        string $lang = 'ar'
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    /**
     * Get a single attribute by ID
     */
    public function get(int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($id, $lang);
    }

    /**
     * Get a single attribute by slug
     */
    public function getBySlug(string $slug, string $lang = 'ar'): ?array
    {
        return $this->service->getBySlug($slug, $lang);
    }

    /**
     * Create a new attribute
     */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /**
     * Update an existing attribute
     */
    public function update(int $id, array $data): void
    {
        $this->service->update($id, $data);
    }

    /**
     * Delete an attribute
     */
    public function delete(int $id): void
    {
        $this->service->delete($id);
    }

    /**
     * Get attribute translations
     */
    public function getTranslations(int $id): array
    {
        return $this->service->getTranslations($id);
    }

    /**
     * Get available languages
     */
    public function getLanguages(): array
    {
        return $this->service->getLanguages();
    }
}