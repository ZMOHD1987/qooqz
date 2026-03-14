<?php
declare(strict_types=1);

final class AuctionTranslationsValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        foreach (['auction_id', 'language_code', 'title'] as $field) {
            if (empty($data[$field])) {
                $this->errors[] = "Field '{$field}' is required";
            }
        }

        if (!empty($data['title']) && strlen((string)$data['title']) > 500) {
            $this->errors[] = 'title must not exceed 500 characters';
        }

        if (!empty($data['language_code']) && strlen((string)$data['language_code']) > 8) {
            $this->errors[] = 'language_code must not exceed 8 characters';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
