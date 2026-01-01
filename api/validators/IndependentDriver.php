<?php
// api/validators/IndependentDriver.php
declare(strict_types=1);

class IndependentDriverValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        $name = trim((string)($data['full_name'] ?? $data['name'] ?? ''));
        if ($name === '') $errors['full_name'][] = 'Driver name is required';
        elseif (mb_strlen($name) > 255) $errors['full_name'][] = 'Name too long';

        $phone = trim((string)($data['phone'] ?? ''));
        if ($phone === '') $errors['phone'][] = 'Phone is required';
        elseif (!preg_match('/^[0-9+\-\s]{6,30}$/', $phone)) $errors['phone'][] = 'Phone format invalid';

        if (isset($data['email']) && $data['email'] !== '') {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'][] = 'Invalid email';
        }

        // vehicle_type: accept either one of allowed values OR any short string (letters/digits/hyphen/space)
        $vehicle_type = trim((string)($data['vehicle_type'] ?? ''));
        $allowedVehicles = ['motorcycle','car','van','truck'];
        if ($vehicle_type === '') {
            $errors['vehicle_type'][] = 'Vehicle type is required';
        } else {
            // allow custom short values (e.g. "33333" or localized names)
            if (!in_array(strtolower($vehicle_type), $allowedVehicles, true)) {
                if (!preg_match('/^[\p{L}0-9\s\-\_]{1,50}$/u', $vehicle_type)) {
                    $errors['vehicle_type'][] = 'Invalid vehicle type';
                }
            }
        }

        if (isset($data['status']) && $data['status'] !== '') {
            $s = strtolower((string)$data['status']);
            if (!in_array($s, ['active','inactive','busy','offline'], true)) $errors['status'][] = 'Invalid status';
        }

        return $errors;
    }
}