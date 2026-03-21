<?php
declare(strict_types=1);

final class EscrowDisputesValidator
{
    private array $errors = [];

    private const VALID_DISPUTE_TYPES = [
        'not_received', 'not_as_described', 'damaged', 'wrong_item', 'other'
    ];

    private const VALID_STATUSES = [
        'open', 'under_review', 'resolved_buyer', 'resolved_seller', 'resolved_partial', 'closed'
    ];

    private const VALID_RESOLUTION_TYPES = [
        'refund_full', 'refund_partial', 'release_payment', 'cancelled'
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
            foreach (['escrow_id', 'raised_by_entity_id', 'raised_by_entity_type', 'dispute_type', 'description'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (!empty($data['dispute_type']) && !in_array($data['dispute_type'], self::VALID_DISPUTE_TYPES, true)) {
            $this->errors[] = 'Invalid dispute_type value';
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value';
        }

        if (!empty($data['resolution_type']) && !in_array($data['resolution_type'], self::VALID_RESOLUTION_TYPES, true)) {
            $this->errors[] = 'Invalid resolution_type value';
        }

        if (isset($data['refund_amount']) && $data['refund_amount'] !== null && (!is_numeric($data['refund_amount']) || $data['refund_amount'] < 0)) {
            $this->errors[] = 'refund_amount must be a non-negative number';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
