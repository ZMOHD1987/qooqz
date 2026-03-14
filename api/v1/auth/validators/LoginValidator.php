<?php
declare(strict_types=1);

/**
 * LoginValidator
 *
 * Validates that at least one identifier is present: username OR email OR phone,
 * and that password is present.
 */

class LoginValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        $identifier = trim((string)($data['username'] ?? $data['email'] ?? $data['phone'] ?? ''));
        if ($identifier === '') {
            $errors['username'] = 'Username, email, or phone is required';
        }

        if (empty(trim((string)($data['password'] ?? '')))) {
            $errors['password'] = 'Password is required';
        }

        return $errors;
    }
}