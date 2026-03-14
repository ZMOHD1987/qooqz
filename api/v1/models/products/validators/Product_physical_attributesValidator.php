<?php
declare(strict_types=1);

final class ProductPhysicalAttributesValidator
{
    private const ALLOWED_WEIGHT_UNITS = ['kg','g','lb'];
    private const ALLOWED_DIMENSION_UNITS = ['cm','mm','in'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        $hasProduct = !empty($data['product_id']);
        $hasVariant = !empty($data['variant_id']);

        // يجب أن يكون واحد فقط موجود
        if ($hasProduct === $hasVariant) {
            $msg = $hasProduct 
                ? 'Exactly one of product_id or variant_id must be provided.' 
                : ($isUpdate ? 'No product_id or variant_id provided for update.' : 'Product ID or Variant ID is required.');
            throw new InvalidArgumentException($msg);
        }

        // Validate numeric fields
        foreach (['weight','length','width','height'] as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("$field must be a number");
            }
        }

        // Validate units
        if (!empty($data['weight_unit']) && !in_array($data['weight_unit'], self::ALLOWED_WEIGHT_UNITS, true)) {
            throw new InvalidArgumentException("Invalid weight_unit. Allowed: ".implode(',', self::ALLOWED_WEIGHT_UNITS));
        }

        if (!empty($data['dimension_unit']) && !in_array($data['dimension_unit'], self::ALLOWED_DIMENSION_UNITS, true)) {
            throw new InvalidArgumentException("Invalid dimension_unit. Allowed: ".implode(',', self::ALLOWED_DIMENSION_UNITS));
        }
    }
}