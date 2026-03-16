<?php
declare(strict_types=1);

final class TicketStatusHistoryValidator
{
    private array $errors = [];

    private const VALID_STATUSES = [
        'open', 'pending', 'awaiting_customer', 'awaiting_vendor', 
        'in_progress', 'resolved', 'closed', 'cancelled'
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
            foreach (['ticket_id', 'new_status'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (isset($data['ticket_id']) && (!is_numeric($data['ticket_id']) || (int)$data['ticket_id'] <= 0)) {
            $this->errors[] = 'ticket_id must be a positive integer';
        }

        if (!empty($data['new_status']) && !in_array($data['new_status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid new_status value';
        }

        if (!empty($data['old_status']) && !in_array($data['old_status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid old_status value';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}