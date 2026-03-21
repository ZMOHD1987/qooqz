<?php
declare(strict_types=1);

final class AdPlacementsValidator
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
            if (empty($data['name']) || trim((string)$data['name']) === '') {
                $this->errors[] = "Field 'name' is required";
            }
            if (empty($data['placement_key']) || trim((string)$data['placement_key']) === '') {
                $this->errors[] = "Field 'placement_key' is required";
            }
        }

        if (!empty($data['name']) && mb_strlen((string)$data['name']) > 255) {
            $this->errors[] = 'Name must not exceed 255 characters';
        }

        if (!empty($data['code'])) {
            if (mb_strlen((string)$data['code']) > 100) {
                $this->errors[] = 'Code must not exceed 100 characters';
            }
            if (!preg_match('/^[a-z0-9_-]+$/i', (string)$data['code'])) {
                $this->errors[] = 'Code may only contain letters, numbers, underscores and hyphens';
            }
        }

        if (!empty($data['placement_key'])) {
            if (mb_strlen((string)$data['placement_key']) > 100) {
                $this->errors[] = 'Placement key must not exceed 100 characters';
            }
            if (!preg_match('/^[a-z0-9_-]+$/i', (string)$data['placement_key'])) {
                $this->errors[] = 'Placement key may only contain letters, numbers, underscores and hyphens';
            }
        }

        if (!empty($data['page']) && mb_strlen((string)$data['page']) > 100) {
            $this->errors[] = 'Page must not exceed 100 characters';
        }

        foreach (['width', 'height'] as $dim) {
            if (isset($data[$dim]) && $data[$dim] !== '' && $data[$dim] !== null) {
                if (!is_numeric($data[$dim]) || (int)$data[$dim] < 1) {
                    $this->errors[] = ucfirst($dim) . ' must be a positive integer';
                }
            }
        }

        if (isset($data['max_ads']) && $data['max_ads'] !== '' && $data['max_ads'] !== null) {
            if (!is_numeric($data['max_ads']) || (int)$data['max_ads'] < 1) {
                $this->errors[] = 'Max ads must be a positive integer';
            }
        }

        if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive', 'draft'], true)) {
            $this->errors[] = "Status must be 'active', 'inactive' or 'draft'";
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}