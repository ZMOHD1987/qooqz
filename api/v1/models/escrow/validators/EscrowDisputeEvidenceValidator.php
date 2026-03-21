<?php
declare(strict_types=1);

final class EscrowDisputeEvidenceValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'create') {
            foreach (['dispute_id', 'file_url'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (isset($data['dispute_id']) && (!is_numeric($data['dispute_id']) || (int)$data['dispute_id'] <= 0)) {
            $this->errors[] = 'dispute_id must be a positive integer';
        }

        if (!empty($data['file_url']) && strlen($data['file_url']) > 500) {
            $this->errors[] = 'file_url must not exceed 500 characters';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
