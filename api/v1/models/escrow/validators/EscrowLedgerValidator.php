<?php
declare(strict_types=1);

final class EscrowLedgerValidator
{
    private array $errors = [];

    private const VALID_TRANSACTION_TYPES = [
        'fund', 'fee', 'release', 'refund', 'partial_refund'
    ];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'create') {
            foreach (['escrow_id', 'entity_id', 'entity_type', 'transaction_type', 'amount'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (!empty($data['transaction_type']) && !in_array($data['transaction_type'], self::VALID_TRANSACTION_TYPES, true)) {
            $this->errors[] = 'Invalid transaction_type value';
        }

        if (!empty($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
            $this->errors[] = 'Amount must be a positive number';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
