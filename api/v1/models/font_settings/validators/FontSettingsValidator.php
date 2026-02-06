<?php
declare(strict_types=1);

// api/v1/models/font_settings/validators/FontSettingsValidator.php

final class FontSettingsValidator
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

        // font_family
        if (empty($data['font_family'])) {
            $errors['font_family'] = 'Font family is required';
        } elseif (strlen($data['font_family']) > 255) {
            $errors['font_family'] = 'Font family is too long';
        }

        // font_size (optional)
        if (isset($data['font_size']) && strlen($data['font_size']) > 50) {
            $errors['font_size'] = 'Font size is too long';
        }

        // font_weight (optional)
        if (isset($data['font_weight']) && strlen($data['font_weight']) > 50) {
            $errors['font_weight'] = 'Font weight is too long';
        }

        // line_height (optional)
        if (isset($data['line_height']) && strlen($data['line_height']) > 50) {
            $errors['line_height'] = 'Line height is too long';
        }

        // category
        $allowedCategories = ['heading', 'body', 'button', 'navigation', 'other'];
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