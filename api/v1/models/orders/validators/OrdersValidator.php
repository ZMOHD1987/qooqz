<?php
declare(strict_types=1);

namespace App\Models\Orders\Validators;

use InvalidArgumentException;

final class OrdersValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['user_id', 'subtotal', 'total_amount', 'grand_total'];
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        if (isset($data['user_id']) && !is_numeric($data['user_id'])) {
            throw new InvalidArgumentException("user_id must be numeric.");
        }

        foreach (['subtotal', 'tax_amount', 'shipping_cost', 'discount_amount', 'total_amount', 'grand_total'] as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("$field must be numeric.");
            }
        }

        $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'refunded', 'failed'];
        if (isset($data['status']) && !in_array($data['status'], $validStatuses, true)) {
            throw new InvalidArgumentException("Invalid status value.");
        }

        $validPaymentStatuses = ['pending', 'paid', 'partial', 'failed', 'refunded'];
        if (isset($data['payment_status']) && !in_array($data['payment_status'], $validPaymentStatuses, true)) {
            throw new InvalidArgumentException("Invalid payment_status value.");
        }
    }
}
