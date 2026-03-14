<?php
declare(strict_types=1);

final class Product_typesValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update' && empty($data['id'])) {
            $this->errors[] = 'ID is required';
        }

        if (empty($data['code'])) {
            $this->errors[] = 'Code is required';
        }

        if (empty($data['name'])) {
            $this->errors[] = 'Name is required';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
