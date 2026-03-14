<?php
declare(strict_types=1);

namespace App\Models\Jobs\Validators;

use InvalidArgumentException;

final class JobCategoriesValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        // التحقق من الحقول المطلوبة
        if (!$isUpdate) {
            if (!isset($data['tenant_id']) || $data['tenant_id'] === '') {
                throw new InvalidArgumentException("Field 'tenant_id' is required.");
            }

            // Note: 'name' is not a field in job_categories table
            // It only exists in job_category_translations table
            // Translations are validated separately via validateTranslation()
        }

        // التحقق من tenant_id
        if (isset($data['tenant_id'])) {
            if (!is_numeric($data['tenant_id']) || $data['tenant_id'] <= 0) {
                throw new InvalidArgumentException("tenant_id must be a positive integer.");
            }
        }

        // التحقق من parent_id
        if (isset($data['parent_id']) && $data['parent_id'] !== null) {
            if (!is_numeric($data['parent_id']) || $data['parent_id'] <= 0) {
                throw new InvalidArgumentException("parent_id must be a positive integer or null.");
            }
        }

        // التحقق من slug
        if (isset($data['slug'])) {
            if (strlen($data['slug']) > 255) {
                throw new InvalidArgumentException("Slug must be at most 255 characters.");
            }
            if (!empty($data['slug']) && !preg_match('/^[a-z0-9\-]+$/i', $data['slug'])) {
                throw new InvalidArgumentException("Slug must contain only letters, numbers, and hyphens.");
            }
        }

        // التحقق من sort_order
        if (isset($data['sort_order']) && $data['sort_order'] !== null) {
            if (!is_numeric($data['sort_order'])) {
                throw new InvalidArgumentException("sort_order must be a number.");
            }
        }

        // التحقق من is_active
        if (isset($data['is_active'])) {
            if (!in_array((int)$data['is_active'], [0, 1], true)) {
                throw new InvalidArgumentException("is_active must be 0 or 1.");
            }
        }

        // التحقق من name في الترجمة
        if (isset($data['name'])) {
            if (strlen($data['name']) > 255) {
                throw new InvalidArgumentException("name must be at most 255 characters.");
            }
            if (trim($data['name']) === '') {
                throw new InvalidArgumentException("name cannot be empty.");
            }
        }
    }

    /**
     * التحقق من صحة بيانات الترجمة
     */
    public function validateTranslation(array $data): void
    {
        if (empty($data['language_code'])) {
            throw new InvalidArgumentException("language_code is required.");
        }

        if (strlen($data['language_code']) > 8) {
            throw new InvalidArgumentException("language_code must be at most 8 characters.");
        }

        if (empty($data['name'])) {
            throw new InvalidArgumentException("name is required in translation.");
        }

        if (strlen($data['name']) > 255) {
            throw new InvalidArgumentException("name must be at most 255 characters.");
        }

        if (trim($data['name']) === '') {
            throw new InvalidArgumentException("name cannot be empty.");
        }
    }

    /**
     * التحقق من إمكانية نقل الفئة
     */
    public function validateMove(int $categoryId, ?int $newParentId): void
    {
        if ($newParentId !== null) {
            if ($categoryId === $newParentId) {
                throw new InvalidArgumentException("Cannot move category to itself.");
            }
        }
    }
}