<?php
// api/validators/Role_permissionsValidator.php

if (!class_exists('Role_permissionsValidator')) {

class Role_permissionsValidator
{
    public static function validate(array $data)
    {
        $errors = [];

        if (empty($data['role_id']) || !is_numeric($data['role_id']) || (int)$data['role_id'] <= 0) {
            $errors['role_id'] = 'Invalid role ID';
        }

        if (empty($data['permission_id']) || !is_numeric($data['permission_id']) || (int)$data['permission_id'] <= 0) {
            $errors['permission_id'] = 'Invalid permission ID';
        }

        if (isset($data['id']) && (!is_numeric($data['id']) || (int)$data['id'] <= 0)) {
            $errors['id'] = 'Invalid role_permission ID';
        }

        return empty($errors) ? true : $errors;
    }
}

}
