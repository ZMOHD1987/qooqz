<?php
declare(strict_types=1);

final class TimezonesValidator
{
    /**
     * Validate data for creating a timezone
     *
     * Returns array of errors keyed by field. Empty array means valid.
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validateCreate(array $data): array
    {
        $errors = [];

        $tz = $data['timezone'] ?? '';
        if (!is_string($tz) || trim($tz) === '') {
            $errors['timezone'] = 'required';
        } elseif (mb_strlen($tz) > 64) {
            $errors['timezone'] = 'too_long';
        }

        $label = $data['label'] ?? null;
        if ($label !== null && (!is_string($label) || mb_strlen($label) > 128)) {
            $errors['label'] = 'invalid_or_too_long';
        }

        return $errors;
    }

    /**
     * Validate data for updating a timezone
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validateUpdate(array $data): array
    {
        $errors = [];

        if (empty($data['id'])) {
            $errors['id'] = 'required';
        }

        // reuse create validation for other fields
        $createErrors = $this->validateCreate($data);
        return array_merge($errors, $createErrors);
    }
}