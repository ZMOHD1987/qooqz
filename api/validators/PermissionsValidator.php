<?php
// api/validators/PermissionsValidator.php
declare(strict_types=1);

/**
 * PermissionsValidator
 * - validate($data): returns array of errors; empty array means valid.
 */

class PermissionsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        $key = isset($data['key_name']) ? trim((string)$data['key_name']) : '';
        $display = isset($data['display_name']) ? trim((string)$data['display_name']) : '';

        if ($key === '') {
            $errors['key_name'] = 'Permission key is required';
        } else {
            if (strlen($key) > 100) $errors['key_name'] = 'Permission key is too long (max 100 chars)';
            elseif (!preg_match('/^[a-z0-9\._-]+$/i', $key)) $errors['key_name'] = 'Permission key contains invalid characters';
        }

        if ($display === '') {
            $errors['display_name'] = 'Display name is required';
        } else {
            if (strlen($display) > 150) $errors['display_name'] = 'Display name is too long (max 150 chars)';
        }

        if (isset($data['id']) && $data['id'] !== '' && !is_numeric($data['id'])) {
            $errors['id'] = 'Invalid permission ID';
        }

        return $errors;
    }
}
