<?php
declare(strict_types=1);

final class AdTranslationsValidator
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
            foreach (['ad_id', 'language_code'] as $field) {
                if (empty($data[$field])) {
                    $this->errors[] = "Field '{$field}' is required";
                }
            }
        }

        if (!empty($data['ad_id']) && (!is_numeric($data['ad_id']) || (int)$data['ad_id'] < 1)) {
            $this->errors[] = 'Field \'ad_id\' must be a positive integer';
        }

        if (!empty($data['language_code']) && strlen($data['language_code']) > 8) {
            $this->errors[] = 'language_code must not exceed 8 characters';
        }

        if (!empty($data['title']) && strlen($data['title']) > 255) {
            $this->errors[] = 'Title must not exceed 255 characters';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
