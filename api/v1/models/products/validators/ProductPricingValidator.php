<?php
declare(strict_types=1);

namespace App\Models\Products\Validators;

use InvalidArgumentException;

final class ProductPricingValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            foreach (['product_id','price','currency_code'] as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("$field is required");
                }
            }
        }

        if (isset($data['price']) && !is_numeric($data['price'])) {
            throw new InvalidArgumentException("price must be numeric");
        }

        foreach (['tax_rate','cost_price','compare_at_price'] as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("$field must be numeric");
            }
        }

        if (isset($data['currency_code']) && strlen($data['currency_code']) !== 3) {
            throw new InvalidArgumentException("currency_code must be ISO 3 chars");
        }

        if (isset($data['pricing_type']) &&
            !in_array($data['pricing_type'], ['fixed','discount','auction','service'], true)
        ) {
            throw new InvalidArgumentException("Invalid pricing_type");
        }

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0,1], true)) {
            throw new InvalidArgumentException("is_active must be 0 or 1");
        }
    }
}
