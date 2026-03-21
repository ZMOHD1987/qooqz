<?php
declare(strict_types=1);

final class AdPlacementItemsValidator
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
            foreach (['placement_id', 'ad_id'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        foreach (['placement_id', 'ad_id'] as $intField) {
            if (!empty($data[$intField]) && (!is_numeric($data[$intField]) || (int)$data[$intField] < 1)) {
                $this->errors[] = "Field '{$intField}' must be a positive integer";
            }
        }

        if (isset($data['priority']) && $data['priority'] !== null && $data['priority'] !== '' && (!is_numeric($data['priority']) || (int)$data['priority'] < 1)) {
            $this->errors[] = 'Priority must be a positive integer';
        }

        if (isset($data['weight']) && $data['weight'] !== null && $data['weight'] !== '' && (!is_numeric($data['weight']) || (int)$data['weight'] < 1)) {
            $this->errors[] = 'Weight must be a positive integer';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
