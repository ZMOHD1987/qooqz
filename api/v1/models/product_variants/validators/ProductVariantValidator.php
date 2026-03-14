<?php
declare(strict_types=1);

use InvalidArgumentException;

final class ProductVariantValidator
{
    public function validate(array $data, bool $isUpdate=false): void
    {
        $required = ['product_id'];
        if (!$isUpdate) {
            foreach($required as $field) {
                if(!isset($data[$field])) {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        if(isset($data['sku']) && strlen($data['sku'])>100) {
            throw new InvalidArgumentException("SKU must be <= 100 chars.");
        }
        if(isset($data['barcode']) && strlen($data['barcode'])>100) {
            throw new InvalidArgumentException("Barcode must be <= 100 chars.");
        }
        foreach(['stock_quantity','low_stock_threshold','is_active','is_default'] as $intField) {
            if(isset($data[$intField]) && !is_numeric($data[$intField])) {
                throw new InvalidArgumentException("$intField must be numeric.");
            }
        }
    }
}