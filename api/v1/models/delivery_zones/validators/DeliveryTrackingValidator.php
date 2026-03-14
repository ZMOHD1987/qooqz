<?php
declare(strict_types=1);

final class DeliveryTrackingValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['delivery_order_id', 'latitude', 'longitude'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("$field is required.");
                }
            }
        }

        if (isset($data['latitude'])) {
            if (!is_numeric($data['latitude']) || $data['latitude'] < -90 || $data['latitude'] > 90) {
                throw new InvalidArgumentException('Invalid latitude.');
            }
        }

        if (isset($data['longitude'])) {
            if (!is_numeric($data['longitude']) || $data['longitude'] < -180 || $data['longitude'] > 180) {
                throw new InvalidArgumentException('Invalid longitude.');
            }
        }
    }
}