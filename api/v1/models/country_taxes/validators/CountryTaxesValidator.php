<?php
declare(strict_types=1);

// api/v1/models/country_taxes/validators/CountryTaxesValidator.php

final class CountryTaxesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // country_id
        if (empty($data['country_id'])) {
            $errors['country_id'] = 'Country ID is required';
        } elseif (!is_numeric($data['country_id']) || $data['country_id'] <= 0) {
            $errors['country_id'] = 'Country ID must be a positive integer';
        }

        // tax_class_id
        if (empty($data['tax_class_id'])) {
            $errors['tax_class_id'] = 'Tax class ID is required';
        } elseif (!is_numeric($data['tax_class_id']) || $data['tax_class_id'] <= 0) {
            $errors['tax_class_id'] = 'Tax class ID must be a positive integer';
        }

        // tax_name
        if (empty($data['tax_name'])) {
            $errors['tax_name'] = 'Tax name is required';
        } elseif (strlen($data['tax_name']) > 100) {
            $errors['tax_name'] = 'Tax name is too long';
        }

        // tax_name_ar
        if (empty($data['tax_name_ar'])) {
            $errors['tax_name_ar'] = 'Arabic tax name is required';
        } elseif (strlen($data['tax_name_ar']) > 100) {
            $errors['tax_name_ar'] = 'Arabic tax name is too long';
        }

        // tax_type
        $allowedTypes = ['vat', 'gst', 'sales_tax', 'customs'];
        if (isset($data['tax_type']) && !in_array($data['tax_type'], $allowedTypes)) {
            $errors['tax_type'] = 'Invalid tax type';
        }

        // tax_rate
        if (!isset($data['tax_rate'])) {
            $errors['tax_rate'] = 'Tax rate is required';
        } elseif (!is_numeric($data['tax_rate']) || $data['tax_rate'] < 0 || $data['tax_rate'] > 999.99) {
            $errors['tax_rate'] = 'Tax rate must be a decimal between 0 and 999.99';
        }

        // is_inclusive
        if (isset($data['is_inclusive']) && !in_array($data['is_inclusive'], [0, 1])) {
            $errors['is_inclusive'] = 'Is inclusive must be 0 or 1';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // effective_date (optional, but if provided, must be valid date)
        if (!empty($data['effective_date']) && !strtotime($data['effective_date'])) {
            $errors['effective_date'] = 'Effective date must be a valid date';
        }

        return $errors;
    }
}