<?php
declare(strict_types=1);

final class AutoBidSettingsValidator
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
            foreach (['auction_id', 'user_id', 'max_bid_amount'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (isset($data['max_bid_amount']) && (!is_numeric($data['max_bid_amount']) || (float)$data['max_bid_amount'] <= 0)) {
            $this->errors[] = 'max_bid_amount must be a positive number';
        }

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $this->errors[] = 'is_active must be 0 or 1';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
