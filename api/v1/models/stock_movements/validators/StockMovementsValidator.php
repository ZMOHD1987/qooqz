<?php
declare(strict_types=1);

/**
 * Static validation methods for stock movement operations.
 * Each method returns ['valid' => bool, 'errors' => array].
 */
final class StockMovementsValidator
{
    private const ALLOWED_TYPES = ['restock', 'sale', 'return', 'adjustment'];

    /**
     * Validate data for creating a stock movement.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['product_id']) || !is_numeric($data['product_id'])) {
            $errors[] = "Field 'product_id' is required and must be numeric";
        }

        if (!isset($data['change_quantity']) || !is_numeric($data['change_quantity']) || (int)$data['change_quantity'] === 0) {
            $errors[] = "Field 'change_quantity' is required and must be a non-zero integer";
        }

        if (empty($data['type']) || !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors[] = "Field 'type' is required and must be one of: " . implode(', ', self::ALLOWED_TYPES);
        }

        if (isset($data['variant_id']) && $data['variant_id'] !== '' && !is_numeric($data['variant_id'])) {
            $errors[] = "Field 'variant_id' must be numeric";
        }

        if (isset($data['reference_id']) && $data['reference_id'] !== '' && !is_numeric($data['reference_id'])) {
            $errors[] = "Field 'reference_id' must be numeric";
        }

        if (isset($data['notes']) && !is_string($data['notes'])) {
            $errors[] = "Field 'notes' must be a string";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate data for updating a stock movement.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (empty($data)) {
            $errors[] = "No data provided for update";
            return ['valid' => false, 'errors' => $errors];
        }

        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            $errors[] = "Field 'product_id' must be numeric";
        }

        if (isset($data['change_quantity']) && (!is_numeric($data['change_quantity']) || (int)$data['change_quantity'] === 0)) {
            $errors[] = "Field 'change_quantity' must be a non-zero integer";
        }

        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors[] = "Field 'type' must be one of: " . implode(', ', self::ALLOWED_TYPES);
        }

        if (isset($data['variant_id']) && $data['variant_id'] !== '' && !is_numeric($data['variant_id'])) {
            $errors[] = "Field 'variant_id' must be numeric";
        }

        if (isset($data['reference_id']) && $data['reference_id'] !== '' && !is_numeric($data['reference_id'])) {
            $errors[] = "Field 'reference_id' must be numeric";
        }

        if (isset($data['notes']) && !is_string($data['notes'])) {
            $errors[] = "Field 'notes' must be a string";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
