<?php
declare(strict_types=1);

final class JobCategoriesController
{
    private JobCategoriesService $service;

    public function __construct(JobCategoriesService $service)
    {
        $this->service = $service;
    }

    /**
     * List categories with filters, ordering, and pagination
     */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC',
        string $lang = 'ar'
    ): array {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($tenantId, $filters, $lang);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get category tree (hierarchical structure)
     */
    public function getTree(int $tenantId, ?int $parentId = null, string $lang = 'ar'): array
    {
        return $this->service->getTree($tenantId, $parentId, $lang);
    }

    /**
     * Get root categories
     */
    public function getRootCategories(int $tenantId, string $lang = 'ar'): array
    {
        return $this->service->getRootCategories($tenantId, $lang);
    }

    /**
     * Get children of a category
     */
    public function getChildren(int $tenantId, int $parentId, string $lang = 'ar'): array
    {
        return $this->service->getChildren($tenantId, $parentId, $lang);
    }

    /**
     * Get a single category by ID
     */
    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($tenantId, $id, $lang);
    }

    /**
     * Get a single category by slug
     */
    public function getBySlug(int $tenantId, string $slug, string $lang = 'ar'): ?array
    {
        return $this->service->getBySlug($tenantId, $slug, $lang);
    }

    /**
     * Get all translations for a category
     */
    public function getTranslations(int $categoryId): array
    {
        return $this->service->getTranslations($categoryId);
    }

    /**
     * Get available languages for a category
     */
    public function getAvailableLanguages(int $categoryId): array
    {
        return $this->service->getAvailableLanguages($categoryId);
    }

    /**
     * Get missing languages for a category
     */
    public function getMissingLanguages(int $categoryId): array
    {
        return $this->service->getMissingLanguages($categoryId);
    }

    /**
     * Create a new category
     */
    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    /**
     * Update an existing category
     */
    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    /**
     * Delete a category
     */
    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }

    /**
     * Save or update translation for a category
     */
    public function saveTranslation(int $categoryId, string $languageCode, array $data): bool
    {
        return $this->service->saveTranslation($categoryId, $languageCode, $data);
    }

    /**
     * Bulk save translations
     */
    public function bulkSaveTranslations(int $categoryId, array $translations): bool
    {
        return $this->service->bulkSaveTranslations($categoryId, $translations);
    }

    /**
     * Delete translation
     */
    public function deleteTranslation(int $categoryId, string $languageCode): bool
    {
        return $this->service->deleteTranslation($categoryId, $languageCode);
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(int $tenantId, int $id, int $sortOrder): bool
    {
        return $this->service->updateSortOrder($tenantId, $id, $sortOrder);
    }

    /**
     * Move category to different parent
     */
    public function moveToParent(int $tenantId, int $id, ?int $newParentId): bool
    {
        return $this->service->moveToParent($tenantId, $id, $newParentId);
    }

    /**
     * Reorder categories (batch update)
     */
    public function reorder(int $tenantId, array $orderData): bool
    {
        return $this->service->reorder($tenantId, $orderData);
    }
}