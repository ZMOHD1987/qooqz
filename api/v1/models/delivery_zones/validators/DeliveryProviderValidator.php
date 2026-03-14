<?php
declare(strict_types=1);

final class DeliveryProviderValidator
{
    private const PROVIDER_TYPES = ['company', 'entity_driver', 'independent_driver'];
    private const VEHICLE_TYPES  = ['bike', 'car', 'van', 'truck'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['tenant_user_id', 'provider_type'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("$field is required.");
                }
            }
        }

        if (isset($data['provider_type']) && !in_array($data['provider_type'], self::PROVIDER_TYPES, true)) {
            throw new InvalidArgumentException('Invalid provider_type. Allowed: ' . implode(', ', self::PROVIDER_TYPES));
        }

        if (isset($data['vehicle_type']) && !in_array($data['vehicle_type'], self::VEHICLE_TYPES, true)) {
            throw new InvalidArgumentException('Invalid vehicle_type. Allowed: ' . implode(', ', self::VEHICLE_TYPES));
        }

        if (isset($data['rating'])) {
            if (!is_numeric($data['rating']) || $data['rating'] < 0 || $data['rating'] > 5) {
                throw new InvalidArgumentException('Rating must be numeric between 0 and 5.');
            }
        }
        
        if (isset($data['is_online']) && !in_array($data['is_online'], [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('is_online must be 0 or 1.');
        }
    }
}