<?php
declare(strict_types=1);

// api/v1/models/attribute_types/validators/AttributeTypesValidator.php

final class AttributeTypesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // code
        if (empty($data['code'])) {
            $errors['code'] = 'Code is required';
        } elseif (strlen($data['code']) > 50) {
            $errors['code'] = 'Code is too long';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['code'])) {
            $errors['code'] = 'Code must contain only letters, numbers, and underscores';
        }

        // name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = 'Name is too long';
        }

        // has_values
        if (isset($data['has_values']) && !in_array($data['has_values'], [0, 1])) {
            $errors['has_values'] = 'Has values must be 0 or 1';
        }

        // is_multi
        if (isset($data['is_multi']) && !in_array($data['is_multi'], [0, 1])) {
            $errors['is_multi'] = 'Is multi must be 0 or 1';
        }

        // is_visual
        if (isset($data['is_visual']) && !in_array($data['is_visual'], [0, 1])) {
            $errors['is_visual'] = 'Is visual must be 0 or 1';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        return $errors;
    }
}