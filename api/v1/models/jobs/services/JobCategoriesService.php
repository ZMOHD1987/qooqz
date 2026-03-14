<?php
declare(strict_types=1);

final class JobCategoriesService
{
    private PdoJobCategoriesRepository $repo;
    private PdoJobCategoryTranslationsRepository $translationsRepo;
    private $validator;

    public function __construct(
        PdoJobCategoriesRepository $repo,
        PdoJobCategoryTranslationsRepository $translationsRepo,
        $validator = null
    ) {
        $this->repo = $repo;
        $this->translationsRepo = $translationsRepo;
        $this->validator = $validator;
    }

    /**
     * List categories with filters, ordering, and pagination
     */
    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir,
        string $lang = 'ar'
    ): array {
        return $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
    }

    /**
     * Count categories with filters
     */
    public function count(int $tenantId, array $filters = [], string $lang = 'ar'): int
    {
        return $this->repo->count($tenantId, $filters, $lang);
    }

    /**
     * Get single category by ID
     */
    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->repo->find($tenantId, $id, $lang);
    }

    /**
     * Get single category by slug
     */
    public function getBySlug(int $tenantId, string $slug, string $lang = 'ar'): ?array
    {
        return $this->repo->findBySlug($tenantId, $slug, $lang);
    }

    /**
     * Get category tree (hierarchical structure)
     */
    public function getTree(int $tenantId, ?int $parentId = null, string $lang = 'ar'): array
    {
        return $this->repo->getTree($tenantId, $parentId, $lang);
    }

    /**
     * Get root categories (no parent)
     */
    public function getRootCategories(int $tenantId, string $lang = 'ar'): array
    {
        return $this->repo->getRootCategories($tenantId, $lang);
    }

    /**
     * Get children of a category
     */
    public function getChildren(int $tenantId, int $parentId, string $lang = 'ar'): array
    {
        return $this->repo->getChildren($tenantId, $parentId, $lang);
    }

    /**
     * Get all translations for a category
     */
    public function getTranslations(int $categoryId): array
    {
        return $this->translationsRepo->getAllForCategory($categoryId);
    }

    /**
     * Get available languages for a category
     */
    public function getAvailableLanguages(int $categoryId): array
    {
        return $this->translationsRepo->getAvailableLanguages($categoryId);
    }

    /**
     * Get missing languages for a category
     */
    public function getMissingLanguages(int $categoryId): array
    {
        return $this->translationsRepo->getMissingLanguages($categoryId);
    }

    /**
     * Create new category
     */
    public function create(int $tenantId, array $data): int
    {
        // Add tenant_id
        $data['tenant_id'] = $tenantId;

        // Validate
        if ($this->validator) {
            $this->validator->validate($data, false);
        }

        // Save category
        $categoryId = $this->repo->save($tenantId, $data);

        // Save translations if provided as array
        if ($categoryId && !empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $translation) {
                if (!empty($translation['language_code']) && !empty($translation['name'])) {
                    $this->saveTranslation($categoryId, $translation['language_code'], $translation);
                }
            }
        }
        // Legacy: Save single translation if provided
        elseif ($categoryId && !empty($data['name'])) {
            $lang = $data['language_code'] ?? 'ar';
            $this->saveTranslation($categoryId, $lang, $data);
        }

        // Handle deleted translations if provided
        if (!empty($data['deleted_translations']) && is_array($data['deleted_translations'])) {
            foreach ($data['deleted_translations'] as $lang) {
                $this->deleteTranslation($categoryId, $lang);
            }
        }

        return $categoryId;
    }

    /**
     * Update existing category
     */
    public function update(int $tenantId, array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        // Validate
        if ($this->validator) {
            $this->validator->validate($data, true);
        }

        // Update category
        $categoryId = $this->repo->save($tenantId, $data);

        // Save translations if provided as array
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $translation) {
                if (!empty($translation['language_code']) && !empty($translation['name'])) {
                    $this->saveTranslation($categoryId, $translation['language_code'], $translation);
                }
            }
        }
        // Legacy: Update single translation if provided
        elseif (!empty($data['name'])) {
            $lang = $data['language_code'] ?? 'ar';
            $this->saveTranslation($categoryId, $lang, $data);
        }

        // Handle deleted translations if provided
        if (!empty($data['deleted_translations']) && is_array($data['deleted_translations'])) {
            foreach ($data['deleted_translations'] as $lang) {
                $this->deleteTranslation($categoryId, $lang);
            }
        }

        return $categoryId;
    }

    /**
     * Save or update translation
     */
    public function saveTranslation(int $categoryId, string $languageCode, array $data): bool
    {
        // Validate translation data
        if ($this->validator) {
            $translationData = array_merge($data, ['language_code' => $languageCode]);
            $this->validator->validateTranslation($translationData);
        }

        return $this->translationsRepo->save($categoryId, $languageCode, $data);
    }

    /**
     * Bulk save translations
     */
    public function bulkSaveTranslations(int $categoryId, array $translations): bool
    {
        return $this->translationsRepo->bulkSave($categoryId, $translations);
    }

    /**
     * Delete translation
     */
    public function deleteTranslation(int $categoryId, string $languageCode): bool
    {
        return $this->translationsRepo->delete($categoryId, $languageCode);
    }

    /**
     * Delete category
     */
    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(int $tenantId, int $id, int $sortOrder): bool
    {
        return $this->repo->updateSortOrder($tenantId, $id, $sortOrder);
    }

    /**
     * Move category to different parent
     */
    public function moveToParent(int $tenantId, int $id, ?int $newParentId): bool
    {
        // Validate move
        if ($this->validator) {
            $this->validator->validateMove($id, $newParentId);
        }

        return $this->repo->moveToParent($tenantId, $id, $newParentId);
    }

    /**
     * Reorder categories (batch update sort orders)
     */
    public function reorder(int $tenantId, array $orderData): bool
    {
        foreach ($orderData as $item) {
            if (isset($item['id']) && isset($item['sort_order'])) {
                $this->repo->updateSortOrder($tenantId, (int)$item['id'], (int)$item['sort_order']);
            }
        }
        return true;
    }
}