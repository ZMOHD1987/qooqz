<?php
declare(strict_types=1);

final class TicketCategoriesValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        // التحقق من وجود اسم للغة الحالية على الأقل
        if ($scenario === 'create') {
            if (empty($data['name'])) {
                $this->errors[] = 'Category name is required';
            }
        }

        if (isset($data['priority_level']) && (!is_numeric($data['priority_level']) || $data['priority_level'] < 1 || $data['priority_level'] > 10)) {
            $this->errors[] = 'Priority level must be between 1 and 10';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}