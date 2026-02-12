<?php
declare(strict_types=1);

class FlashSalesValidator {

    public static function validateCreate(array $data): array {
        $errors = [];
        if (empty($data['sale_name'])) $errors[] = 'sale_name is required';
        if (empty($data['start_date'])) $errors[] = 'start_date is required';
        if (empty($data['end_date']))   $errors[] = 'end_date is required';
        if (!isset($data['discount_value']) || (float)$data['discount_value'] <= 0) $errors[] = 'discount_value must be > 0';
        if (!empty($data['discount_type']) && !in_array($data['discount_type'], ['percentage','fixed'])) {
            $errors[] = 'discount_type must be percentage or fixed';
        }
        if (!empty($data['start_date']) && !empty($data['end_date']) && strtotime($data['start_date']) >= strtotime($data['end_date'])) {
            $errors[] = 'end_date must be after start_date';
        }
        return $errors;
    }

    public static function validateUpdate(array $data): array {
        $errors = [];
        if (isset($data['discount_value']) && (float)$data['discount_value'] <= 0) $errors[] = 'discount_value must be > 0';
        if (!empty($data['discount_type']) && !in_array($data['discount_type'], ['percentage','fixed'])) {
            $errors[] = 'discount_type must be percentage or fixed';
        }
        if (!empty($data['start_date']) && !empty($data['end_date']) && strtotime($data['start_date']) >= strtotime($data['end_date'])) {
            $errors[] = 'end_date must be after start_date';
        }
        return $errors;
    }

    public static function validateProduct(array $data): array {
        $errors = [];
        if (empty($data['flash_sale_id'])) $errors[] = 'flash_sale_id is required';
        if (empty($data['product_id']))    $errors[] = 'product_id is required';
        if (!isset($data['original_price']) || (float)$data['original_price'] <= 0) $errors[] = 'original_price must be > 0';
        if (!isset($data['sale_price']) || (float)$data['sale_price'] <= 0)         $errors[] = 'sale_price must be > 0';
        if (isset($data['original_price']) && isset($data['sale_price']) && (float)$data['sale_price'] >= (float)$data['original_price']) {
            $errors[] = 'sale_price must be less than original_price';
        }
        return $errors;
    }

    public static function validateTranslation(array $data): array {
        $errors = [];
        if (empty($data['flash_sale_id']))  $errors[] = 'flash_sale_id is required';
        if (empty($data['language_code']))  $errors[] = 'language_code is required';
        if (empty($data['field_name']))     $errors[] = 'field_name is required';
        if (!isset($data['value']) || $data['value'] === '') $errors[] = 'value is required';
        $allowedFields = ['sale_name','description'];
        if (!empty($data['field_name']) && !in_array($data['field_name'], $allowedFields)) {
            $errors[] = 'field_name must be one of: ' . implode(', ', $allowedFields);
        }
        return $errors;
    }
}
