<?php
declare(strict_types=1);

namespace App\Models\Carts\Validators;

use InvalidArgumentException;

final class CartsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['entity_id'];
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        // Validate entity_id
        if (isset($data['entity_id']) && !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("entity_id must be numeric.");
        }

        // Validate user_id if provided
        if (isset($data['user_id']) && $data['user_id'] !== null && !is_numeric($data['user_id'])) {
            throw new InvalidArgumentException("user_id must be numeric.");
        }

        // Validate session_id length
        if (isset($data['session_id']) && strlen($data['session_id']) > 255) {
            throw new InvalidArgumentException("session_id must be at most 255 chars.");
        }

        // Validate device_id length
        if (isset($data['device_id']) && strlen($data['device_id']) > 255) {
            throw new InvalidArgumentException("device_id must be at most 255 chars.");
        }

        // Validate IP address length
        if (isset($data['ip_address']) && strlen($data['ip_address']) > 45) {
            throw new InvalidArgumentException("ip_address must be at most 45 chars.");
        }

        // Validate numeric fields
        foreach (['total_items', 'loyalty_points_used'] as $numField) {
            if (isset($data[$numField]) && !is_numeric($data[$numField])) {
                throw new InvalidArgumentException("$numField must be numeric.");
            }
        }

        // Validate decimal fields
        foreach (['subtotal', 'tax_amount', 'shipping_cost', 'discount_amount', 'total_amount'] as $decField) {
            if (isset($data[$decField]) && !is_numeric($data[$decField])) {
                throw new InvalidArgumentException("$decField must be numeric.");
            }
            if (isset($data[$decField]) && (float)$data[$decField] < 0) {
                throw new InvalidArgumentException("$decField must be non-negative.");
            }
        }

        // Validate currency_code
        if (isset($data['currency_code']) && strlen($data['currency_code']) > 8) {
            throw new InvalidArgumentException("currency_code must be at most 8 chars.");
        }

        // Validate coupon_code
        if (isset($data['coupon_code']) && strlen($data['coupon_code']) > 100) {
            throw new InvalidArgumentException("coupon_code must be at most 100 chars.");
        }

        // Validate status enum
        $validStatuses = ['active', 'abandoned', 'converted', 'expired'];
        if (isset($data['status']) && !in_array($data['status'], $validStatuses, true)) {
            throw new InvalidArgumentException("status must be one of: " . implode(', ', $validStatuses));
        }

        // Validate discount_id if provided
        if (isset($data['discount_id']) && $data['discount_id'] !== null && !is_numeric($data['discount_id'])) {
            throw new InvalidArgumentException("discount_id must be numeric.");
        }

        // Validate converted_to_order_id if provided
        if (isset($data['converted_to_order_id']) && $data['converted_to_order_id'] !== null && !is_numeric($data['converted_to_order_id'])) {
            throw new InvalidArgumentException("converted_to_order_id must be numeric.");
        }
    }
}
