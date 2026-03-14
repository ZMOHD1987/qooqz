<?php
declare(strict_types=1);

namespace App\Models\CartItems\Validators;

use InvalidArgumentException;

final class CartItemsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['cart_id', 'product_id', 'quantity', 'unit_price', 'product_name', 'sku'];
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        // Validate cart_id
        if (isset($data['cart_id']) && !is_numeric($data['cart_id'])) {
            throw new InvalidArgumentException("cart_id must be numeric.");
        }

        // Validate product_id
        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException("product_id must be numeric.");
        }

        // Validate product_variant_id if provided
        if (isset($data['product_variant_id']) && $data['product_variant_id'] !== null && !is_numeric($data['product_variant_id'])) {
            throw new InvalidArgumentException("product_variant_id must be numeric.");
        }

        // Validate entity_id
        if (isset($data['entity_id']) && !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("entity_id must be numeric.");
        }

        // Validate product_name
        if (isset($data['product_name']) && strlen($data['product_name']) > 500) {
            throw new InvalidArgumentException("product_name must be at most 500 chars.");
        }

        // Validate SKU
        if (isset($data['sku']) && strlen($data['sku']) > 100) {
            throw new InvalidArgumentException("sku must be at most 100 chars.");
        }

        // Validate quantity
        if (isset($data['quantity'])) {
            if (!is_numeric($data['quantity'])) {
                throw new InvalidArgumentException("quantity must be numeric.");
            }
            if ((int)$data['quantity'] < 1) {
                throw new InvalidArgumentException("quantity must be at least 1.");
            }
        }

        // Validate decimal fields
        foreach (['unit_price', 'sale_price', 'discount_amount', 'tax_amount', 'subtotal', 'total'] as $decField) {
            if (isset($data[$decField]) && !is_numeric($data[$decField])) {
                throw new InvalidArgumentException("$decField must be numeric.");
            }
            if (isset($data[$decField]) && (float)$data[$decField] < 0) {
                throw new InvalidArgumentException("$decField must be non-negative.");
            }
        }

        // Validate tax_rate
        if (isset($data['tax_rate'])) {
            if (!is_numeric($data['tax_rate'])) {
                throw new InvalidArgumentException("tax_rate must be numeric.");
            }
            if ((float)$data['tax_rate'] < 0 || (float)$data['tax_rate'] > 100) {
                throw new InvalidArgumentException("tax_rate must be between 0 and 100.");
            }
        }

        // Validate currency_code
        if (isset($data['currency_code']) && strlen($data['currency_code']) > 8) {
            throw new InvalidArgumentException("currency_code must be at most 8 chars.");
        }

        // Validate is_gift
        if (isset($data['is_gift']) && !in_array((int)$data['is_gift'], [0, 1], true)) {
            throw new InvalidArgumentException("is_gift must be 0 or 1.");
        }
    }
}
