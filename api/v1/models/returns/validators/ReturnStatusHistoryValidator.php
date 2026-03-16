<?php
declare(strict_types=1);

final class ReturnStatusHistoryValidator
{
    private array $errors = [];

    private const VALID_STATUSES = [
        'pending', 'approved', 'rejected', 'processing', 'completed', 'cancelled'
    ];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if ($scenario === 'create') {
            foreach (['return_id', 'status'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (isset($data['return_id']) && (!is_numeric($data['return_id']) || (int)$data['return_id'] <= 0)) {
            $this->errors[] = 'return_id must be a positive integer';
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}