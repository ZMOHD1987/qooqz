<?php
declare(strict_types=1);

// api/v1/models/product_attribute_assignments/validators/ProductAttributeAssignmentsValidator.php

final class ProductAttributeAssignmentsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // product_id
        if (empty($data['product_id'])) {
            $errors['product_id'] = 'Product ID is required';
        } elseif (!is_numeric($data['product_id']) || $data['product_id'] <= 0) {
            $errors['product_id'] = 'Product ID must be a positive integer';
        }

        // attribute_id
        if (empty($data['attribute_id'])) {
            $errors['attribute_id'] = 'Attribute ID is required';
        } elseif (!is_numeric($data['attribute_id']) || $data['attribute_id'] <= 0) {
            $errors['attribute_id'] = 'Attribute ID must be a positive integer';
        }

        // attribute_value_id (optional)
        if (isset($data['attribute_value_id']) && (!is_numeric($data['attribute_value_id']) || $data['attribute_value_id'] <= 0)) {
            $errors['attribute_value_id'] = 'Attribute value ID must be a positive integer';
        }

        // custom_value (optional)
        if (isset($data['custom_value']) && strlen($data['custom_value']) > 255) {
            $errors['custom_value'] = 'Custom value is too long';
        }

        return $errors;
    }
}