<?php
declare(strict_types=1);

final class ReturnItemsValidator
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
            foreach (['return_id', 'order_item_id', 'product_id', 'quantity'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        // Validation for numeric IDs
        foreach (['return_id', 'order_item_id', 'product_id'] as $field) {
            if (isset($data[$field]) && (!is_numeric($data[$field]) || (int)$data[$field] <= 0)) {
                $this->errors[] = "{$field} must be a positive integer";
            }
        }

        // Quantity validation
        if (isset($data['quantity'])) {
            if (!is_numeric($data['quantity']) || (int)$data['quantity'] <= 0) {
                $this->errors[] = 'quantity must be a positive integer';
            }
        }

        // Refund amount validation
        if (isset($data['refund_amount']) && $data['refund_amount'] !== null && !is_numeric($data['refund_amount'])) {
            $this->errors[] = 'refund_amount must be numeric';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}