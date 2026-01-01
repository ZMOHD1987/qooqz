<?php
// api/validators/DeliveryZone.php
// Simple validator for delivery zones. Returns array of errors (field => messages[]).
// Replace or extend with project's validator if available.

declare(strict_types=1);

class DeliveryZoneValidator
{
    // Validate payload (array) and return array of errors. Empty array = valid.
    public static function validate(array $data): array
    {
        $errors = [];

        // zone_name required, string, length
        $name = trim((string)($data['zone_name'] ?? ''));
        if ($name === '') {
            $errors['zone_name'][] = 'Zone name is required';
        } elseif (mb_strlen($name) > 255) {
            $errors['zone_name'][] = 'Zone name too long';
        }

        // zone_type must be one of polygon, rectangle, radius, circle
        $type = strtolower((string)($data['zone_type'] ?? ''));
        $allowedTypes = ['polygon','rectangle','radius','circle'];
        if ($type === '') {
            $errors['zone_type'][] = 'Zone type is required';
        } elseif (!in_array($type, $allowedTypes, true)) {
            $errors['zone_type'][] = 'Invalid zone type';
        }

        // zone_value should be valid JSON (if provided)
        if (isset($data['zone_value']) && $data['zone_value'] !== '') {
            $z = $data['zone_value'];
            if (!is_string($z)) {
                // accept arrays/objects too by encoding attempt
                try {
                    $z = json_encode($z, JSON_UNESCAPED_UNICODE);
                } catch (Throwable $e) {
                    $z = null;
                }
            }
            if ($z === null || (@json_decode($z) === null && json_last_error() !== JSON_ERROR_NONE)) {
                $errors['zone_value'][] = 'zone_value must be valid JSON geometry';
            }
        }

        // shipping_rate numeric >= 0 (optional)
        if (isset($data['shipping_rate']) && $data['shipping_rate'] !== '') {
            if (!is_numeric($data['shipping_rate'])) $errors['shipping_rate'][] = 'Shipping rate must be numeric';
            elseif ((float)$data['shipping_rate'] < 0) $errors['shipping_rate'][] = 'Shipping rate must be >= 0';
        }

        // free_shipping_threshold numeric >= 0 (optional)
        if (isset($data['free_shipping_threshold']) && $data['free_shipping_threshold'] !== '') {
            if (!is_numeric($data['free_shipping_threshold'])) $errors['free_shipping_threshold'][] = 'Free shipping threshold must be numeric';
            elseif ((float)$data['free_shipping_threshold'] < 0) $errors['free_shipping_threshold'][] = 'Free shipping threshold must be >= 0';
        }

        // estimated_delivery_days integer >= 0
        if (isset($data['estimated_delivery_days']) && $data['estimated_delivery_days'] !== '') {
            if (!is_numeric($data['estimated_delivery_days']) || (int)$data['estimated_delivery_days'] < 0) $errors['estimated_delivery_days'][] = 'Estimated delivery days must be a non-negative integer';
        }

        // status allowed values
        if (isset($data['status']) && $data['status'] !== '') {
            $status = strtolower((string)$data['status']);
            if (!in_array($status, ['active','inactive'], true)) $errors['status'][] = 'Invalid status';
        }

        return $errors;
    }
}