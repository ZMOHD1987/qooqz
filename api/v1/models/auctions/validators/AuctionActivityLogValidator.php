<?php
declare(strict_types=1);

final class AuctionActivityLogValidator
{
    private array $errors = [];

    private const VALID_ACTIVITY_TYPES = [
        'created', 'started', 'bid_placed', 'auto_bid_placed', 'outbid',
        'extended', 'paused', 'resumed', 'ended', 'cancelled',
        'winner_declared', 'payment_received', 'item_shipped'
    ];

    public function validate(array $data): bool
    {
        $this->errors = [];

        foreach (['auction_id', 'activity_type'] as $field) {
            if (empty($data[$field])) {
                $this->errors[] = "Field '{$field}' is required";
            }
        }

        if (!empty($data['activity_type']) && !in_array($data['activity_type'], self::VALID_ACTIVITY_TYPES, true)) {
            $this->errors[] = 'Invalid activity_type value';
        }

        if (isset($data['amount']) && $data['amount'] !== null && !is_numeric($data['amount'])) {
            $this->errors[] = 'amount must be numeric';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
