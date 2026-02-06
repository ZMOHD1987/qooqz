<?php
declare(strict_types=1);

// api/v1/models/design_settings/validators/DesignSettingsValidator.php

final class DesignSettingsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // setting_key
        if (empty($data['setting_key'])) {
            $errors['setting_key'] = 'Setting key is required';
        } elseif (strlen($data['setting_key']) > 100) {
            $errors['setting_key'] = 'Setting key is too long';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['setting_key'])) {
            $errors['setting_key'] = 'Setting key must contain only letters, numbers, and underscores';
        }

        // setting_name
        if (empty($data['setting_name'])) {
            $errors['setting_name'] = 'Setting name is required';
        } elseif (strlen($data['setting_name']) > 255) {
            $errors['setting_name'] = 'Setting name is too long';
        }

        // setting_value (can be empty)
        if (isset($data['setting_value']) && strlen($data['setting_value']) > 65535) {
            $errors['setting_value'] = 'Setting value is too long';
        }

        // setting_type
        $allowedTypes = ['text', 'number', 'color', 'image', 'boolean', 'select', 'json'];
        if (isset($data['setting_type']) && !in_array($data['setting_type'], $allowedTypes)) {
            $errors['setting_type'] = 'Invalid setting type';
        }

        // category
        $allowedCategories = ['layout', 'header', 'footer', 'sidebar', 'homepage', 'product', 'cart', 'checkout', 'other'];
        if (isset($data['category']) && !in_array($data['category'], $allowedCategories)) {
            $errors['category'] = 'Invalid category';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // sort_order
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // theme_id (optional, bigint)
        if (isset($data['theme_id']) && (!is_numeric($data['theme_id']) || $data['theme_id'] <= 0)) {
            $errors['theme_id'] = 'Theme ID must be a positive integer';
        }

        return $errors;
    }
}