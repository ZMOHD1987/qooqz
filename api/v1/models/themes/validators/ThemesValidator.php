<?php
declare(strict_types=1);

// api/v1/models/themes/validators/ThemesValidator.php

final class ThemesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // name
        if (empty($data['name'])) {
            $errors['name'] = 'Theme name is required';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Theme name is too long';
        }

        // slug
        if (empty($data['slug'])) {
            $errors['slug'] = 'Slug is required';
        } elseif (strlen($data['slug']) > 255) {
            $errors['slug'] = 'Slug is too long';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, and hyphens';
        }

        // description (optional)
        if (isset($data['description']) && strlen($data['description']) > 65535) {
            $errors['description'] = 'Description is too long';
        }

        // thumbnail_url (optional)
        if (isset($data['thumbnail_url']) && strlen($data['thumbnail_url']) > 500) {
            $errors['thumbnail_url'] = 'Thumbnail URL is too long';
        }

        // preview_url (optional)
        if (isset($data['preview_url']) && strlen($data['preview_url']) > 500) {
            $errors['preview_url'] = 'Preview URL is too long';
        }

        // version (optional)
        if (isset($data['version']) && strlen($data['version']) > 50) {
            $errors['version'] = 'Version is too long';
        }

        // author (optional)
        if (isset($data['author']) && strlen($data['author']) > 255) {
            $errors['author'] = 'Author name is too long';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // is_default
        if (isset($data['is_default']) && !in_array($data['is_default'], [0, 1])) {
            $errors['is_default'] = 'Is default must be 0 or 1';
        }

        return $errors;
    }
}