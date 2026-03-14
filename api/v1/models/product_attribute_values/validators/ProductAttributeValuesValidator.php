<?php
declare(strict_types=1);

// api/v1/models/product_attribute_values/validators/ProductAttributeValuesValidator.php

final class ProductAttributeValuesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // attribute_id
        if (empty($data['attribute_id'])) {
            $errors['attribute_id'] = 'Attribute ID is required';
        } elseif (!is_numeric($data['attribute_id']) || $data['attribute_id'] <= 0) {
            $errors['attribute_id'] = 'Attribute ID must be a positive integer';
        }

        // value
        if (empty($data['value'])) {
            $errors['value'] = 'Value is required';
        } elseif (strlen($data['value']) > 255) {
            $errors['value'] = 'Value is too long';
        }

        // slug
        if (empty($data['slug'])) {
            $errors['slug'] = 'Slug is required';
        } elseif (strlen($data['slug']) > 255) {
            $errors['slug'] = 'Slug is too long';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, and hyphens';
        }

        // sort_order
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // translations (optional array)
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $trans) {
                if (isset($trans['label']) && strlen($trans['label']) > 255) {
                    $errors['translations'][$lang]['label'] = 'Translation label is too long';
                }
            }
        }

        return $errors;
    }
}