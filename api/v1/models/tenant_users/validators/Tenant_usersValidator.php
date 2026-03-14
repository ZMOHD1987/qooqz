<?php
declare(strict_types=1);

final class Tenant_usersValidator
{
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // For create operations
        if (!$isUpdate) {
            // user_id is required for create
            if (empty($data['user_id']) || !is_numeric($data['user_id'])) {
                $errors['user_id'] = 'User ID is required and must be numeric';
            } elseif ($data['user_id'] <= 0) {
                $errors['user_id'] = 'User ID must be greater than 0';
            }

            // role_id is required for create
            if (empty($data['role_id']) || !is_numeric($data['role_id'])) {
                $errors['role_id'] = 'Role ID is required and must be numeric';
            } elseif ($data['role_id'] <= 0) {
                $errors['role_id'] = 'Role ID must be greater than 0';
            }
        }

        // For update operations
        if ($isUpdate) {
            // role_id is optional for update but must be valid if provided
            if (isset($data['role_id'])) {
                if (!is_numeric($data['role_id'])) {
                    $errors['role_id'] = 'Role ID must be numeric';
                } elseif ($data['role_id'] <= 0) {
                    $errors['role_id'] = 'Role ID must be greater than 0';
                }
            }
        }

        // is_active validation
        if (isset($data['is_active'])) {
            // accept 0/1 as int or string '0'/'1'
            if (!is_numeric($data['is_active']) || !in_array((int)$data['is_active'], [0, 1], true)) {
                $errors['is_active'] = 'Is active must be 0 or 1';
            }
        }

        // tenant_id validation (if provided)
        if (isset($data['tenant_id'])) {
            if (!is_numeric($data['tenant_id']) || $data['tenant_id'] <= 0) {
                $errors['tenant_id'] = 'Tenant ID must be a positive number';
            }
        }

        // entity_id validation (optional field)
        if (isset($data['entity_id']) && $data['entity_id'] !== '' && $data['entity_id'] !== null) {
            if (!is_numeric($data['entity_id']) || $data['entity_id'] <= 0) {
                $errors['entity_id'] = 'Entity ID must be a positive number';
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

        if (!isset($data['is_active']) || !is_numeric($data['is_active']) || !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        return $errors;
    }

    /**
     * Validate filters
     */
    public static function validateFilters(array $filters): array
    {
        $errors = [];

        if (isset($filters['search']) && strlen((string)$filters['search']) < 2) {
            $errors['search'] = 'Search term must be at least 2 characters';
        }

        // Accept '0'/'1' (strings) as well as ints
        if (isset($filters['is_active'])) {
            if (!is_numeric($filters['is_active']) || !in_array((int)$filters['is_active'], [0, 1], true)) {
                $errors['is_active'] = 'Is active filter must be 0 or 1';
            }
        }

        if (isset($filters['role_id']) && (!is_numeric($filters['role_id']) || (int)$filters['role_id'] <= 0)) {
            $errors['role_id'] = 'Role ID filter must be a positive number';
        }

        if (isset($filters['user_id']) && (!is_numeric($filters['user_id']) || (int)$filters['user_id'] <= 0)) {
            $errors['user_id'] = 'User ID filter must be a positive number';
        }

        // tenant_id filter validation (optional)
        if (isset($filters['tenant_id']) && (!is_numeric($filters['tenant_id']) || (int)$filters['tenant_id'] <= 0)) {
            $errors['tenant_id'] = 'Tenant ID filter must be a positive number';
        }

        // entity_id filter validation (optional)
        if (isset($filters['entity_id']) && (!is_numeric($filters['entity_id']) || (int)$filters['entity_id'] <= 0)) {
            $errors['entity_id'] = 'Entity ID filter must be a positive number';
        }

        return $errors;
    }
}