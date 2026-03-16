<?php
declare(strict_types=1);

final class SupportTicketsValidator
{
    private array $errors = [];

    private const VALID_STATUSES = [
        'open', 'pending', 'awaiting_customer', 'awaiting_vendor', 
        'in_progress', 'resolved', 'closed', 'cancelled'
    ];

    private const VALID_PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if ($scenario === 'create') {
            foreach (['user_id', 'category_id', 'subject', 'description'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value';
        }

        if (!empty($data['priority']) && !in_array($data['priority'], self::VALID_PRIORITIES, true)) {
            $this->errors[] = 'Invalid priority value';
        }

        if (isset($data['subject']) && strlen((string)$data['subject']) > 500) {
            $this->errors[] = 'Subject must not exceed 500 characters';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}