<?php
declare(strict_types=1);

final class ReturnsValidator
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
            foreach (['order_id', 'user_id'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (isset($data['order_id'])
            && (!is_numeric($data['order_id']) || (int)$data['order_id'] <= 0)
        ) {
            $this->errors[] = 'order_id must be a positive integer';
        }

        if (isset($data['user_id'])
            && (!is_numeric($data['user_id']) || (int)$data['user_id'] <= 0)
        ) {
            $this->errors[] = 'user_id must be a positive integer';
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value';
        }

        if (isset($data['reason']) && strlen((string)$data['reason']) > 65535) {
            $this->errors[] = 'reason is too long';
        }

        if (isset($data['admin_notes']) && strlen((string)$data['admin_notes']) > 65535) {
            $this->errors[] = 'admin_notes is too long';
        }

        if (isset($data['return_number']) && strlen((string)$data['return_number']) > 50) {
            $this->errors[] = 'return_number must not exceed 50 characters';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}