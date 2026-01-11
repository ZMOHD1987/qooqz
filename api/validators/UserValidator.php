<?php
declare(strict_types=1);

class UserValidator
{
    protected static array $timezones = [];

    public static function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        // 1. تحويل المود تلقائياً بناءً على وجود المعرف
        if ($mode === 'save' || empty($mode)) {
            $mode = (!empty($data['id']) && is_numeric($data['id'])) ? 'update' : 'create';
        }

        /* ================== ID ================== */
        if ($mode === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $errors['id'] = 'Invalid user ID';
            }
        }

        /* ================== Username ================== */
        if ($mode === 'create' || array_key_exists('username', $data)) {
            $username = trim((string)($data['username'] ?? ''));

            if ($username === '') {
                $errors['username'] = 'Username is required';
            } elseif (mb_strlen($username) < 3) {
                $errors['username'] = 'Username must be at least 3 characters';
            } elseif (mb_strlen($username) > 50) {
                $errors['username'] = 'Username is too long';
            } elseif (!preg_match('/^[\p{L}\p{N}._-]+$/u', $username)) {
                $errors['username'] = 'Username contains invalid characters';
            }
        }

        /* ================== Email ================== */
        if ($mode === 'create' || array_key_exists('email', $data)) {
            $email = trim((string)($data['email'] ?? ''));

            if ($email === '') {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
        }

        /* ================== Password ================== */
        $password = (string)($data['password'] ?? '');
        if ($mode === 'create') {
            if ($password === '') {
                $errors['password'] = 'Password is required';
            } elseif (mb_strlen($password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            }
        } else {
            // في التحديث: نتحقق فقط إذا قام المستخدم بكتابة باسوورد جديد
            if ($password !== '' && mb_strlen($password) < 8) {
                $errors['password'] = 'New password must be at least 8 characters';
            }
        }

        /* ================== Country / City / Role ================== */
        // تعديل مهم: التعامل مع القيم الفارغة القادمة من الـ Select
        foreach (['country_id', 'city_id', 'role_id'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') { 
                if (!is_numeric($data[$key])) {
                    $errors[$key] = 'Invalid selection';
                }
            } elseif ($mode === 'create' && $key === 'role_id') {
                // الـ Role إلزامي عند الإنشاء
                $errors['role_id'] = 'Role is required';
            }
        }

        /* ================== Active Status ================== */
        // التعامل مع مسميات الحقول المختلفة (is_active أو status)
        $activeVal = $data['is_active'] ?? $data['status'] ?? null;
        if ($activeVal !== null && $activeVal !== '') {
            // قبول (active, inactive) أو (0, 1)
            if (is_string($activeVal)) {
                $activeVal = strtolower($activeVal);
                if (!in_array($activeVal, ['0', '1', 'active', 'inactive'], true)) {
                    $errors['status'] = 'Invalid status value';
                }
            }
        }

        return $errors;
    }
}