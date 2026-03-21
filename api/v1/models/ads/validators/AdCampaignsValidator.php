<?php
declare(strict_types=1);

final class AdCampaignsValidator
{
    private array $errors = [];

    private const VALID_STATUSES = ['draft', 'active', 'paused', 'completed'];
    private const VALID_PRICING_MODELS = ['fixed', 'cpm', 'cpc'];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if ($scenario === 'create') {
            foreach (['name', 'currency_id'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (!empty($data['name']) && strlen($data['name']) > 255) {
            $this->errors[] = 'Name must not exceed 255 characters';
        }

        if (!empty($data['currency_id']) && (!is_numeric($data['currency_id']) || (int)$data['currency_id'] < 1)) {
            $this->errors[] = 'Field \'currency_id\' must be a positive integer';
        }

        if (!empty($data['entity_id']) && (!is_numeric($data['entity_id']) || (int)$data['entity_id'] < 1)) {
            $this->errors[] = 'Field \'entity_id\' must be a positive integer';
        }

        if (isset($data['budget']) && (!is_numeric($data['budget']) || $data['budget'] < 0)) {
            $this->errors[] = 'Budget must be a non-negative number';
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value';
        }

        if (!empty($data['pricing_model']) && !in_array($data['pricing_model'], self::VALID_PRICING_MODELS, true)) {
            $this->errors[] = 'Invalid pricing_model value';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
