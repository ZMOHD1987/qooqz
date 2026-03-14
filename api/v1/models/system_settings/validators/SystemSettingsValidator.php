<?php
declare(strict_types=1);

// api/v1/models/system_settings/validators/SystemSettingsValidator.php

final class SystemSettingsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // setting_key
        if (empty($data['setting_key'])) {
            $errors['setting_key'] = 'Setting key is required';
        } elseif (strlen($data['setting_key']) > 255) {
            $errors['setting_key'] = 'Setting key is too long';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['setting_key'])) {
            $errors['setting_key'] = 'Setting key must contain only letters, numbers, and underscores';
        }

        // setting_value (can be empty)
        if (isset($data['setting_value']) && strlen($data['setting_value']) > 65535) {
            $errors['setting_value'] = 'Setting value is too long';
        }

        // setting_type
        $allowedTypes = ['text', 'number', 'boolean', 'json', 'file', 'email'];
        if (isset($data['setting_type']) && !in_array($data['setting_type'], $allowedTypes)) {
            $errors['setting_type'] = 'Invalid setting type';
        }

        // category
        if (empty($data['category'])) {
            $errors['category'] = 'Category is required';
        } elseif (strlen($data['category']) > 100) {
            $errors['category'] = 'Category is too long';
        }

        // description (optional)
        if (isset($data['description']) && strlen($data['description']) > 65535) {
            $errors['description'] = 'Description is too long';
        }

        // is_public
        if (isset($data['is_public']) && !in_array($data['is_public'], [0, 1])) {
            $errors['is_public'] = 'Is public must be 0 or 1';
        }

        // is_editable
        if (isset($data['is_editable']) && !in_array($data['is_editable'], [0, 1])) {
            $errors['is_editable'] = 'Is editable must be 0 or 1';
        }

        return $errors;
    }
}