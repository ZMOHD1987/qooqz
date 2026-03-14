<?php
declare(strict_types=1);

final class LanguagesValidator
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

        if (!isset($data['name']) || empty(trim($data['name']))) {
            $this->errors[] = 'Name is required';
        }

        if (!isset($data['code']) || empty(trim($data['code']))) {
            $this->errors[] = 'Code is required';
        } elseif (!preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $data['code'])) {
            $this->errors[] = 'Code must be valid language code (e.g., en, en-US)';
        }

        if (isset($data['direction']) && !in_array($data['direction'], ['ltr', 'rtl'])) {
            $this->errors[] = 'Direction must be ltr or rtl';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}