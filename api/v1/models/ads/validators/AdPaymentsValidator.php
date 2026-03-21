<?php
declare(strict_types=1);

final class AdPaymentsValidator
{
    private array $errors = [];

    private const VALID_STATUSES = ['pending', 'paid', 'failed'];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if ($scenario === 'create') {
            foreach (['campaign_id', 'currency_id'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        foreach (['campaign_id', 'currency_id'] as $intField) {
            if (!empty($data[$intField]) && (!is_numeric($data[$intField]) || (int)$data[$intField] < 1)) {
                $this->errors[] = "Field '{$intField}' must be a positive integer";
            }
        }

        if (isset($data['amount']) && $data['amount'] !== null && (!is_numeric($data['amount']) || $data['amount'] < 0)) {
            $this->errors[] = 'Amount must be a non-negative number';
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
