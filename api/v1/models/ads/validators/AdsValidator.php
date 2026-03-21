<?php
declare(strict_types=1);

final class AdsValidator
{
    private array $errors = [];

    private const VALID_STATUSES     = ['active', 'paused', 'rejected'];
    private const VALID_TARGET_TYPES = ['url', 'entity'];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if ($scenario === 'create') {
            if (empty($data['campaign_id']) || !is_numeric($data['campaign_id'])) {
                $this->errors[] = "Field 'campaign_id' is required and must be a positive integer";
            }
        }

        if (!empty($data['campaign_id']) && (!is_numeric($data['campaign_id']) || (int)$data['campaign_id'] < 1)) {
            $this->errors[] = "Field 'campaign_id' must be a positive integer";
        }

        if (!empty($data['target_type']) && !in_array($data['target_type'], self::VALID_TARGET_TYPES, true)) {
            $this->errors[] = 'Invalid target_type value; allowed: url, entity';
        }

        if (!empty($data['target_value']) && strlen($data['target_value']) > 500) {
            $this->errors[] = 'target_value must not exceed 500 characters';
        }

        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $this->errors[] = 'Invalid status value; allowed: active, paused, rejected';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
