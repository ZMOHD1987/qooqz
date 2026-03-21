<?php
declare(strict_types=1);

final class EscrowTransactionsValidator
{
    private array $errors = [];

    private const VALID_STATUSES = [
        'pending', 'funded', 'in_transit', 'delivered',
        'released', 'disputed', 'refunded', 'cancelled'
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
            foreach (['buyer_entity_id', 'buyer_entity_type', 'seller_entity_id', 'seller_entity_type', 'amount'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (!empty($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
            $this->errors[] = 'Amount must be a positive number';
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value';
        }

        if (!empty($data['auto_release_days']) && (!is_numeric($data['auto_release_days']) || (int)$data['auto_release_days'] < 1)) {
            $this->errors[] = 'auto_release_days must be a positive integer';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
