<?php
// api/validators/RolesValidator.php

if (!class_exists('RolesValidator')) {

class RolesValidator
{
    public static function validate(array $data)
    {
        $errors = [];

        if (empty($data['key_name']) || trim($data['key_name']) === '') {
            $errors['key_name'] = 'Key name is required';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['key_name'])) {
            $errors['key_name'] = 'Key name must contain only lowercase letters, numbers, and underscores';
        }

        if (empty($data['display_name']) || trim($data['display_name']) === '') {
            $errors['display_name'] = 'Display name is required';
        }

        // Only validate id if it's provided and not empty
        if (isset($data['id']) && $data['id'] !== '' && $data['id'] !== null) {
            if (!is_numeric($data['id']) || (int)$data['id'] <= 0) {
                $errors['id'] = 'Invalid role ID';
            }
        }

        return empty($errors) ? true : $errors;
    }
}

}
