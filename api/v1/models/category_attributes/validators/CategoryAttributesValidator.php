<?php
declare(strict_types=1);

// api/v1/models/category_attributes/validators/CategoryAttributesValidator.php

final class CategoryAttributesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // category_id
        if (empty($data['category_id'])) {
            $errors['category_id'] = 'Category ID is required';
        } elseif (!is_numeric($data['category_id']) || $data['category_id'] <= 0) {
            $errors['category_id'] = 'Category ID must be a positive integer';
        }

        // attribute_id
        if (empty($data['attribute_id'])) {
            $errors['attribute_id'] = 'Attribute ID is required';
        } elseif (!is_numeric($data['attribute_id']) || $data['attribute_id'] <= 0) {
            $errors['attribute_id'] = 'Attribute ID must be a positive integer';
        }

        // is_required
        if (isset($data['is_required']) && !in_array($data['is_required'], [0, 1])) {
            $errors['is_required'] = 'Is required must be 0 or 1';
        }

        // sort_order (optional)
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
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