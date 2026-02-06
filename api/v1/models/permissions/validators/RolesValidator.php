<?php
declare(strict_types=1);

final class RolesValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if (!isset($data['key_name']) || empty(trim($data['key_name']))) {
            $this->errors[] = 'Key name is required';
        } elseif (!preg_match('/^[a-z_]+$/', $data['key_name'])) {
            $this->errors[] = 'Key name must contain only lowercase letters and underscores';
        }

        if (!isset($data['display_name']) || empty(trim($data['display_name']))) {
            $this->errors[] = 'Display name is required';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}