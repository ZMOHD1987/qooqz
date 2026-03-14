<?php
declare(strict_types=1);

final class AuctionsValidator
{
    private array $errors = [];

    private const VALID_AUCTION_TYPES   = ['normal', 'reserve', 'buy_now', 'dutch', 'sealed_bid'];
    private const VALID_STATUSES        = ['draft', 'scheduled', 'active', 'paused', 'ended', 'cancelled', 'sold'];
    private const VALID_CONDITIONS      = ['new', 'like_new', 'very_good', 'good', 'acceptable', 'for_parts'];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if ($scenario === 'create') {
            foreach (['title', 'starting_price', 'currency_id', 'entity_id', 'start_date', 'end_date'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (isset($data['currency_id'])
            && (!is_numeric($data['currency_id']) || (int)$data['currency_id'] <= 0)
        ) {
            $this->errors[] = 'currency_id must be a positive integer';
        }

        if (isset($data['entity_id'])
            && (!is_numeric($data['entity_id']) || (int)$data['entity_id'] <= 0)
        ) {
            $this->errors[] = 'entity_id must be a positive integer';
        }

        if (!empty($data['auction_type']) && !in_array($data['auction_type'], self::VALID_AUCTION_TYPES, true)) {
            $this->errors[] = 'Invalid auction_type value';
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value';
        }

        if (!empty($data['condition_type']) && !in_array($data['condition_type'], self::VALID_CONDITIONS, true)) {
            $this->errors[] = 'Invalid condition_type value';
        }

        foreach (['starting_price', 'reserve_price', 'current_price', 'buy_now_price', 'bid_increment', 'shipping_cost', 'winning_amount'] as $numField) {
            if (isset($data[$numField]) && $data[$numField] !== null && !is_numeric($data[$numField])) {
                $this->errors[] = "{$numField} must be numeric";
            }
        }

        if (isset($data['title']) && strlen((string)$data['title']) > 500) {
            $this->errors[] = 'title must not exceed 500 characters';
        }

        if (isset($data['slug']) && strlen((string)$data['slug']) > 255) {
            $this->errors[] = 'slug must not exceed 255 characters';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
