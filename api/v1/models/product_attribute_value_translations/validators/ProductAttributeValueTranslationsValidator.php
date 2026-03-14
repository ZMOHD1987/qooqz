<?php
declare(strict_types=1);

// api/v1/models/product_attribute_value_translations/validators/ProductAttributeValueTranslationsValidator.php

final class ProductAttributeValueTranslationsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // attribute_value_id
        if (empty($data['attribute_value_id'])) {
            $errors['attribute_value_id'] = 'Attribute value ID is required';
        } elseif (!is_numeric($data['attribute_value_id']) || $data['attribute_value_id'] <= 0) {
            $errors['attribute_value_id'] = 'Attribute value ID must be a positive integer';
        }

        // language_code
        if (empty($data['language_code'])) {
            $errors['language_code'] = 'Language code is required';
        } elseif (strlen($data['language_code']) > 8) {
            $errors['language_code'] = 'Language code is too long';
        }

        // label
        if (empty($data['label'])) {
            $errors['label'] = 'Label is required';
        } elseif (strlen($data['label']) > 255) {
            $errors['label'] = 'Label is too long';
        }

        return $errors;
    }
}