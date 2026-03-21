<?php
declare(strict_types=1);

final class EscrowStatusHistoryValidator
{
    private array $errors = [];

    private const VALID_STATUSES = [
        'pending', 'funded', 'in_transit', 'delivered',
        'released', 'disputed', 'refunded', 'cancelled'
    ];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'create') {
            foreach (['escrow_id', 'status'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (isset($data['escrow_id']) && (!is_numeric($data['escrow_id']) || (int)$data['escrow_id'] <= 0)) {
            $this->errors[] = 'escrow_id must be a positive integer';
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
