<?php
declare(strict_types=1);

// api/v1/models/products/validators/ProductAttributeTranslationsValidator.php

final class ProductAttributeTranslationsValidator
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

        // language_code
        if (empty($data['language_code'])) {
            $errors['language_code'] = 'Language code is required';
        } elseif (strlen($data['language_code']) > 8) {
            $errors['language_code'] = 'Language code is too long';
        }

        // name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Name is too long';
        }

        // description (optional)
        if (isset($data['description']) && strlen($data['description']) > 65535) {
            $errors['description'] = 'Description is too long';
        }

        return $errors;
    }
}