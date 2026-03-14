<?php
declare(strict_types=1);

final class CurrenciesValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if (!isset($data['code']) || empty(trim($data['code']))) {
            $this->errors[] = 'Code is required';
        } elseif (strlen($data['code']) !== 3) {
            $this->errors[] = 'Code must be 3 characters (ISO 4217)';
        }

        if (!isset($data['name']) || empty(trim($data['name']))) {
            $this->errors[] = 'Name is required';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}