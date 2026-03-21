<?php
declare(strict_types=1);

final class AuditLogsValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        // الحد الأدنى المطلوب هو الـ action
        if (empty($data['action'])) {
            $this->errors[] = 'Action is required';
        }

        if (isset($data['payload']) && !is_string($data['payload']) && !is_array($data['payload'])) {
            $this->errors[] = 'Payload must be an array or string';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}