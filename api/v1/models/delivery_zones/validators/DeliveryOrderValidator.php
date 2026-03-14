<?php
declare(strict_types=1);

final class DeliveryOrderValidator
{
    private const STATUSES = ['pending', 'assigned', 'accepted', 'picked_up', 'on_the_way', 'delivered', 'cancelled'];
    private const CANCELLED_BY = ['customer', 'provider', 'admin', 'system'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['order_id', 'pickup_address_id', 'dropoff_address_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("$field is required.");
                }
            }
        }

        if (isset($data['order_id']) && !is_numeric($data['order_id'])) {
            throw new InvalidArgumentException('order_id must be numeric.');
        }

        if (isset($data['delivery_status']) && !in_array($data['delivery_status'], self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid delivery_status. Allowed: ' . implode(', ', self::STATUSES));
        }

        if (isset($data['cancelled_by']) && !in_array($data['cancelled_by'], self::CANCELLED_BY, true)) {
            throw new InvalidArgumentException('Invalid cancelled_by value.');
        }

        foreach (['delivery_fee', 'calculated_fee', 'provider_payout'] as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("$field must be numeric.");
            }
        }
    }
}