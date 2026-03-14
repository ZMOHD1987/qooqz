<?php
declare(strict_types=1);

// api/v1/models/products/validators/ProductAttributesValidator.php

final class ProductAttributesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // slug
        if (empty($data['slug'])) {
            $errors['slug'] = 'Slug is required';
        } elseif (strlen($data['slug']) > 100) {
            $errors['slug'] = 'Slug is too long';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, and hyphens';
        }

        // attribute_type_id
        if (empty($data['attribute_type_id'])) {
            $errors['attribute_type_id'] = 'Attribute type ID is required';
        } elseif (!is_numeric($data['attribute_type_id']) || $data['attribute_type_id'] <= 0) {
            $errors['attribute_type_id'] = 'Attribute type ID must be a positive integer';
        }

        // is_filterable
        if (isset($data['is_filterable']) && !in_array($data['is_filterable'], [0, 1])) {
            $errors['is_filterable'] = 'Is filterable must be 0 or 1';
        }

        // is_visible
        if (isset($data['is_visible']) && !in_array($data['is_visible'], [0, 1])) {
            $errors['is_visible'] = 'Is visible must be 0 or 1';
        }

        // is_required
        if (isset($data['is_required']) && !in_array($data['is_required'], [0, 1])) {
            $errors['is_required'] = 'Is required must be 0 or 1';
        }

        // is_variation
        if (isset($data['is_variation']) && !in_array($data['is_variation'], [0, 1])) {
            $errors['is_variation'] = 'Is variation must be 0 or 1';
        }

        // sort_order
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // is_global
        if (isset($data['is_global']) && !in_array($data['is_global'], [0, 1])) {
            $errors['is_global'] = 'Is global must be 0 or 1';
        }

        // translations (optional array)
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $trans) {
                if (isset($trans['name']) && strlen($trans['name']) > 255) {
                    $errors['translations'][$lang]['name'] = 'Translation name is too long';
                }
                if (isset($trans['description']) && strlen($trans['description']) > 65535) {
                    $errors['translations'][$lang]['description'] = 'Translation description is too long';
                }
            }
        }

        return $errors;
    }
}