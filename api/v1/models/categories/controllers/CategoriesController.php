<?php
declare(strict_types=1);

final class CategoriesController
{
    private CategoriesService $service;

    public function __construct(CategoriesService $service)
    {
        $this->service = $service;
    }

    /* ============================================================
     * LIST CATEGORIES WITH FILTERS
     * ============================================================ */
    public function list(int $tenantId): array
    {
        $filters = [
            'parent_id' => isset($_GET['parent_id']) && $_GET['parent_id'] !== ''
                ? (int) $_GET['parent_id']
                : null,
            'is_featured' => $_GET['is_featured'] ?? null,
            'is_active' => $_GET['is_active'] ?? null,
            'search' => $_GET['search'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 25
        ];

        $lang = $_GET['lang'] ?? 'ar';

        return $this->service->list($tenantId, $filters, $lang);
    }

    /* ============================================================
     * GET CATEGORY TREE
     * ============================================================ */
    public function tree(int $tenantId): array
    {
        $lang = $_GET['lang'] ?? 'ar';
        return [
            'items' => $this->service->getCategoryTree($tenantId, $lang),
            'meta' => [
                'total' => count($this->service->getCategoryTree($tenantId, $lang))
            ]
        ];
    }

    /* ============================================================
     * GET ACTIVE CATEGORIES
     * ============================================================ */
    public function getActive(int $tenantId): array
    {
        $lang = $_GET['lang'] ?? 'ar';
        $items = $this->service->getActiveCategories($tenantId, $lang);
        
        return [
            'items' => $items,
            'meta' => [
                'total' => count($items)
            ]
        ];
    }

    /* ============================================================
     * GET FEATURED CATEGORIES
     * ============================================================ */
    public function getFeatured(int $tenantId): array
    {
        $lang = $_GET['lang'] ?? 'ar';
        $items = $this->service->getFeaturedCategories($tenantId, $lang);
        
        return [
            'items' => $items,
            'meta' => [
                'total' => count($items)
            ]
        ];
    }

    /* ============================================================
     * GET BY ID (EDIT FORM)
     * ============================================================ */
    public function getById(int $tenantId, int $id): array
    {
        $lang = $_GET['lang'] ?? 'ar';
        $allTranslations = isset($_GET['all_translations']) &&
            in_array($_GET['all_translations'], ['1', 1, true], true);

        return $this->service->getById(
            $tenantId,
            $id,
            $lang,
            $allTranslations
        );
    }

    /* ============================================================
     * CREATE CATEGORY
     * ============================================================ */
    public function create(int $tenantId, array $data): array
    {
        $userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

        unset($data['id']); // حماية ضد تحديث غير مقصود

        return $this->service->save($tenantId, $data, $userId);
    }

    /* ============================================================
     * UPDATE CATEGORY
     * ============================================================ */
    public function update(int $tenantId, array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        $userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

        return $this->service->save($tenantId, $data, $userId);
    }

    /* ============================================================
     * DELETE CATEGORY
     * ============================================================ */
    public function delete(int $tenantId, array $data): void
    {
        $userId = $_SESSION['user_id'] ?? $data['user_id'] ?? null;

        if (!empty($data['id'])) {
            $this->service->deleteById(
                $tenantId,
                (int) $data['id'],
                $userId
            );
            return;
        }

        if (!empty($data['slug'])) {
            $this->service->deleteBySlug(
                $tenantId,
                (string) $data['slug'],
                $userId
            );
            return;
        }

        throw new InvalidArgumentException(
            'ID or slug is required to delete category'
        );
    }

    /* ============================================================
     * DELETE SINGLE TRANSLATION
     * ============================================================ */
    public function deleteTranslation(
        int $tenantId,
        int $categoryId,
        string $languageCode
    ): array {
        $userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

        if ($languageCode === '') {
            throw new InvalidArgumentException('Language code is required');
        }

        $this->service->deleteTranslation(
            $tenantId,
            $categoryId,
            $languageCode,
            $userId
        );

        return [
            'status' => 'success',
            'message' => 'Translation deleted successfully',
            'category_id' => $categoryId,
            'language_code' => $languageCode
        ];
    }

    /* ============================================================
     * BULK OPERATIONS
     * ============================================================ */
    public function bulkUpdate(int $tenantId, array $data): array
    {
        $userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;
        $action = $data['action'] ?? '';
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            throw new InvalidArgumentException('IDs array is required');
        }

        switch ($action) {
            case 'activate':
                $count = $this->service->bulkUpdateStatus($tenantId, $ids, true, $userId);
                $message = "Activated {$count} categories";
                break;
                
            case 'deactivate':
                $count = $this->service->bulkUpdateStatus($tenantId, $ids, false, $userId);
                $message = "Deactivated {$count} categories";
                break;
                
            case 'delete':
                $count = $this->service->bulkDelete($tenantId, $ids, $userId);
                $message = "Deleted {$count} categories";
                break;
                
            default:
                throw new InvalidArgumentException('Invalid bulk action');
        }

        return [
            'status' => 'success',
            'message' => $message,
            'affected' => $count
        ];
    }

    /* ============================================================
     * VALIDATE SLUG
     * ============================================================ */
    public function validateSlug(int $tenantId, array $data): array
    {
        $slug = $data['slug'] ?? '';
        $excludeId = isset($data['exclude_id']) ? (int)$data['exclude_id'] : null;

        if (empty($slug)) {
            throw new InvalidArgumentException('Slug is required');
        }

        $isValid = $this->service->validateSlug($tenantId, $slug, $excludeId);

        return [
            'valid' => $isValid,
            'slug' => $slug,
            'message' => $isValid ? 'Slug is available' : 'Slug already exists'
        ];
    }
}