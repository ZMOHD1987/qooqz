<?php
declare(strict_types=1);

// api/v1/models/categories/validators/TenantCategoriesValidator.php

final class TenantCategoriesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['tenant_id']) || !is_numeric($data['tenant_id'])) {
            $errors['tenant_id'] = 'Tenant ID is required and must be a number';
        }

        if (empty($data['category_id']) || !is_numeric($data['category_id'])) {
            $errors['category_id'] = 'Category ID is required and must be a number';
        }

        if (isset($data['is_active']) && !in_array($data['is_active'], [0,1])) {
            $errors['is_active'] = 'is_active must be 0 or 1';
        }

        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative number';
        }

        return $errors;
    }
}