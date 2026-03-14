<?php
declare(strict_types=1);

final class RolePermissionsValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'assign'): bool
    {
        $this->errors = [];

        if ($scenario === 'assign') {
            if (!isset($data['role_id']) || !is_numeric($data['role_id'])) {
                $this->errors[] = 'Role ID is required';
            }

            if (!isset($data['permission_id']) || !is_numeric($data['permission_id'])) {
                $this->errors[] = 'Permission ID is required';
            }
        } elseif ($scenario === 'delete') {
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for delete';
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}