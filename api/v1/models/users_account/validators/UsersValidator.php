<?php
declare(strict_types=1);

final class UsersValidator
{
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // username
        if (empty($data['username']) || strlen($data['username']) < 3) {
            $errors['username'] = 'Username is required and must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }

        // email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }

        // password for create
        if (!$isUpdate && (empty($data['password']) || strlen($data['password']) < 6)) {
            $errors['password'] = 'Password is required for new users and must be at least 6 characters';
        }

        // role_id
        if (isset($data['role_id']) && $data['role_id'] && !is_numeric($data['role_id'])) {
            $errors['role_id'] = 'Role ID must be numeric';
        }

        // country_id
        if (isset($data['country_id']) && $data['country_id'] && !is_numeric($data['country_id'])) {
            $errors['country_id'] = 'Country ID must be numeric';
        }

        // city_id
        if (isset($data['city_id']) && $data['city_id'] && !is_numeric($data['city_id'])) {
            $errors['city_id'] = 'City ID must be numeric';
        }

        // phone
        if (isset($data['phone']) && $data['phone'] && !preg_match('/^\+?[0-9\s\-\(\)]+$/', $data['phone'])) {
            $errors['phone'] = 'Phone number format is invalid';
        }

        // preferred_language
        if (isset($data['preferred_language']) && !in_array($data['preferred_language'], ['en', 'ar', 'fr', 'es'])) {
            $errors['preferred_language'] = 'Preferred language must be en, ar, fr, or es';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // id for update
        if ($isUpdate && (empty($data['id']) || !is_numeric($data['id']))) {
            $errors['id'] = 'ID is required for update and must be numeric';
        }

        return $errors;
    }

    public static function validateFilters(array $filters): array
    {
        $errors = [];

        if (isset($filters['role_id']) && (!is_numeric($filters['role_id']) || $filters['role_id'] < 1)) {
            $errors['role_id'] = 'Role ID filter must be positive numeric';
        }

        if (isset($filters['search']) && strlen($filters['search']) > 100) {
            $errors['search'] = 'Search filter is too long';
        }

        return $errors;
    }
}