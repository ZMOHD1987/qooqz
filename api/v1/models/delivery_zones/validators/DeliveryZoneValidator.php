<?php
declare(strict_types=1);

final class DeliveryZoneValidator
{
    private const ALLOWED_TYPES = ['city', 'district', 'radius', 'polygon'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['provider_id', 'zone_name', 'zone_type', 'delivery_fee'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("$field is required.");
                }
            }
        }

        if (isset($data['zone_type']) && !in_array($data['zone_type'], self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid zone_type. Allowed: ' . implode(', ', self::ALLOWED_TYPES));
        }

        if (isset($data['delivery_fee']) && !is_numeric($data['delivery_fee'])) {
            throw new InvalidArgumentException('delivery_fee must be numeric.');
        }

        if (isset($data['zone_type'])) {
            if ($data['zone_type'] === 'polygon') {
                $zoneValueProvided = array_key_exists('zone_value', $data);
                $zoneValueEmpty    = $zoneValueProvided && empty($data['zone_value']);

                if (!$isUpdate && !$zoneValueProvided) {
                    throw new InvalidArgumentException('Polygon zones require zone_value (GeoJSON geometry).');
                }
                if ($zoneValueEmpty) {
                    throw new InvalidArgumentException('Polygon zones require zone_value (GeoJSON geometry).');
                }
            }

            if ($data['zone_type'] === 'polygon' && !empty($data['zone_value'])) {
                $raw = is_array($data['zone_value']) ? json_encode($data['zone_value']) : $data['zone_value'];
                if (!is_string($raw) || json_decode($raw) === null) {
                    throw new InvalidArgumentException('zone_value must be valid JSON.');
                }
            }

            if ($data['zone_type'] === 'radius') {
                if (!isset($data['center_lat'], $data['center_lng'], $data['radius_km'])) {
                    throw new InvalidArgumentException('Radius zones require center_lat, center_lng, and radius_km.');
                }
            }
            
            if ($data['zone_type'] === 'city' && empty($data['city_id'])) {
                 throw new InvalidArgumentException('City zones require city_id.');
            }
        }

        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('is_active must be 0 or 1.');
        }
    }
}