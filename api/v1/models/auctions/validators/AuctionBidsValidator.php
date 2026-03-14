<?php
declare(strict_types=1);

final class AuctionBidsValidator
{
    private array $errors = [];

    private const VALID_BID_TYPES = ['manual', 'auto', 'buy_now'];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        foreach (['auction_id', 'user_id', 'bid_amount'] as $field) {
            if (empty($data[$field])) {
                $this->errors[] = "Field '{$field}' is required";
            }
        }

        if (!empty($data['bid_type']) && !in_array($data['bid_type'], self::VALID_BID_TYPES, true)) {
            $this->errors[] = 'Invalid bid_type value';
        }

        foreach (['bid_amount', 'max_auto_bid'] as $numField) {
            if (isset($data[$numField]) && $data[$numField] !== null && !is_numeric($data[$numField])) {
                $this->errors[] = "{$numField} must be numeric";
            }
        }

        if (isset($data['bid_amount']) && is_numeric($data['bid_amount']) && (float)$data['bid_amount'] <= 0) {
            $this->errors[] = 'bid_amount must be greater than zero';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
