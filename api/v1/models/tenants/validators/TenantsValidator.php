<?php
declare(strict_types=1);

final class TenantsValidator
{
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // name validation - required for both create and update
        if (empty($data['name']) || !is_string($data['name'])) {
            $errors['name'] = 'Name is required and must be a string';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        } elseif (strlen($data['name']) > 150) {
            $errors['name'] = 'Name must not exceed 150 characters';
        }

        // domain validation - optional but must be valid if provided
        if (!empty($data['domain'])) {
            if (!is_string($data['domain'])) {
                $errors['domain'] = 'Domain must be a string';
            } elseif (strlen($data['domain']) > 255) {
                $errors['domain'] = 'Domain must not exceed 255 characters';
            } elseif (!filter_var($data['domain'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $errors['domain'] = 'Domain must be a valid domain name';
            }
        }

        // owner_user_id validation - required for create, optional for update
        if (!$isUpdate || isset($data['owner_user_id'])) {
            if (empty($data['owner_user_id']) || !is_numeric($data['owner_user_id'])) {
                $errors['owner_user_id'] = 'Owner user ID is required and must be numeric';
            } elseif ($data['owner_user_id'] <= 0) {
                $errors['owner_user_id'] = 'Owner user ID must be greater than 0';
            }
        }

        // status validation
        if (isset($data['status'])) {
            $validStatuses = ['active', 'suspended'];
            if (!in_array($data['status'], $validStatuses, true)) {
                $errors['status'] = 'Status must be one of: ' . implode(', ', $validStatuses);
            }
        }

        return $errors;
    }

    /**
     * Validate bulk operations
     */
    public static function validateBulk(array $data): array
    {
        $errors = [];

        if (empty($data['ids']) || !is_array($data['ids'])) {
            $errors['ids'] = 'IDs array is required';
        } else {
            foreach ($data['ids'] as $id) {
                if (!is_numeric($id) || $id <= 0) {
                    $errors['ids'] = 'All IDs must be positive numbers';
                    break;
                }
            }
        }

        if (empty($data['status']) || !in_array($data['status'], ['active', 'suspended'], true)) {
            $errors['status'] = 'Status must be active or suspended';
        }

        return $errors;
    }

    /**
     * Validate filters
     */
    public static function validateFilters(array $filters): array
    {
        $errors = [];

        if (isset($filters['search']) && strlen($filters['search']) < 2) {
            $errors['search'] = 'Search term must be at least 2 characters';
        }

        if (isset($filters['status']) && !in_array($filters['status'], ['active', 'suspended'], true)) {
            $errors['status'] = 'Status filter must be active or suspended';
        }

        if (isset($filters['owner_user_id']) && (!is_numeric($filters['owner_user_id']) || $filters['owner_user_id'] <= 0)) {
            $errors['owner_user_id'] = 'Owner user ID filter must be a positive number';
        }

        return $errors;
    }
}