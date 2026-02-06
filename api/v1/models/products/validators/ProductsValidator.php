<?php
declare(strict_types=1);

namespace App\Models\Products\Validators;

use InvalidArgumentException;

final class ProductsValidator
{
    public function validate(array $data, bool $isUpdate=false): void
    {
        $required = ['product_type_id','tenant_id','sku','slug','is_active'];
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        if (isset($data['sku']) && strlen($data['sku'])>100) {
            throw new InvalidArgumentException("SKU must be at most 100 chars.");
        }

        if (isset($data['slug']) && strlen($data['slug'])>255) {
            throw new InvalidArgumentException("Slug must be at most 255 chars.");
        }

        if (isset($data['barcode']) && strlen($data['barcode'])>100) {
            throw new InvalidArgumentException("Barcode must be at most 100 chars.");
        }

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0,1], true)) {
            throw new InvalidArgumentException("is_active must be 0 or 1.");
        }

        // Optional numeric checks
        foreach (['stock_quantity','low_stock_threshold','total_sales','rating_count'] as $numField) {
            if (isset($data[$numField]) && !is_numeric($data[$numField])) {
                throw new InvalidArgumentException("$numField must be numeric.");
            }
        }

        foreach (['weight','length','width','height','rating_average','tax_rate'] as $floatField) {
            if (isset($data[$floatField]) && !is_numeric($data[$floatField])) {
                throw new InvalidArgumentException("$floatField must be numeric.");
            }
        }
    }
}
