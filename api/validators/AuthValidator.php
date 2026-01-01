<?php
// htdocs/api/validators/AuthValidator.php
// Simple validation helpers for auth-related endpoints.
// Returns an array: ['valid' => bool, 'errors' => [], 'data' => sanitized_data]

class AuthValidator
{
    protected static function sanitizeString($v)
    {
        return trim(filter_var($v ?? '', FILTER_SANITIZE_STRING));
    }

    public static function validateRegister(array $input)
    {
        $errors = [];
        $data = [];

        $data['name'] = self::sanitizeString($input['name'] ?? '');
        if ($data['name'] === '') $errors['name'] = 'Name is required';

        $email = trim($input['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        } else {
            $data['email'] = strtolower($email);
        }

        $password = $input['password'] ?? '';
        $passwordConfirm = $input['password_confirmation'] ?? $input['password_confirm'] ?? '';
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif ($password !== $passwordConfirm) {
            $errors['password_confirmation'] = 'Passwords do not match';
        } else {
            $data['password'] = $password;
        }

        $phone = trim($input['phone'] ?? '');
        if ($phone !== '') {
            // basic phone sanitization (allow +, digits, spaces, -)
            $phoneFiltered = preg_replace('/[^\d\+\-\s]/', '', $phone);
            $data['phone'] = $phoneFiltered;
        }

        // optional captcha/recaptcha token
        if (isset($input['captcha'])) $data['captcha'] = trim($input['captcha']);

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateLogin(array $input)
    {
        $errors = [];
        $data = [];

        $identifier = trim($input['email'] ?? $input['identifier'] ?? '');
        if ($identifier === '') {
            $errors['email'] = 'Email or username is required';
        } else {
            $data['identifier'] = $identifier;
        }

        $password = $input['password'] ?? '';
        if ($password === '') {
            $errors['password'] = 'Password is required';
        } else {
            $data['password'] = $password;
        }

        // optional remember boolean
        $data['remember'] = !empty($input['remember']);

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateForgotPassword(array $input)
    {
        $errors = [];
        $data = [];

        $email = trim($input['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        } else {
            $data['email'] = strtolower($email);
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateResetPassword(array $input)
    {
        $errors = [];
        $data = [];

        $token = trim($input['token'] ?? '');
        if ($token === '') $errors['token'] = 'Reset token is required';
        $data['token'] = $token;

        $password = $input['password'] ?? '';
        $passwordConfirm = $input['password_confirmation'] ?? $input['password_confirm'] ?? '';
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif ($password !== $passwordConfirm) {
            $errors['password_confirmation'] = 'Passwords do not match';
        } else {
            $data['password'] = $password;
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateOTP(array $input)
    {
        $errors = [];
        $data = [];

        $code = trim($input['code'] ?? '');
        if ($code === '') $errors['code'] = 'OTP code is required';
        $data['code'] = $code;

        $identifier = trim($input['identifier'] ?? $input['phone'] ?? $input['email'] ?? '');
        if ($identifier === '') $errors['identifier'] = 'Identifier (phone/email) is required';
        $data['identifier'] = $identifier;

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }
}
?>