<?php
declare(strict_types=1);

final class TicketMessagesValidator
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

        if ($scenario === 'create') {
            foreach (['ticket_id', 'sender_user_id', 'message'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        // Validate IDs
        foreach (['ticket_id', 'sender_user_id'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (int)$data[$field] <= 0)) {
                $this->errors[] = "{$field} must be a positive integer";
            }
        }

        // Check message is not empty string if set
        if (isset($data['message']) && trim((string)$data['message']) === '') {
            $this->errors[] = 'Message cannot be empty';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}