<?php
declare(strict_types=1);

final class CountriesValidator
{
    /**
     * Validate create payload
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validateCreate(array $data): array
    {
        $errors = [];

        $iso2 = $data['iso2'] ?? '';
        if ($iso2 === '' || !is_string($iso2)) {
            $errors['iso2'] = 'required';
        } elseif (strlen($iso2) !== 2) {
            $errors['iso2'] = 'invalid_length';
        }

        $iso3 = $data['iso3'] ?? '';
        if ($iso3 !== '' && (!is_string($iso3) || strlen($iso3) !== 3)) {
            $errors['iso3'] = 'invalid_length';
        }

        $name = $data['name'] ?? '';
        if ($name === '' || !is_string($name)) {
            $errors['name'] = 'required';
        } elseif (mb_strlen($name) > 200) {
            $errors['name'] = 'too_long';
        }

        $currency = $data['currency_code'] ?? null;
        if ($currency !== null && (!is_string($currency) || mb_strlen($currency) > 8)) {
            $errors['currency_code'] = 'invalid';
        }

        if (!empty($data['translations']) && !is_array($data['translations'])) {
            $errors['translations'] = 'must_be_array';
        } elseif (!empty($data['translations'])) {
            foreach ($data['translations'] as $i => $t) {
                if (empty($t['language_code']) || empty($t['name'])) {
                    $errors["translations.$i"] = 'language_code_and_name_required';
                } elseif (mb_strlen($t['name']) > 200) {
                    $errors["translations.$i"] = 'name_too_long';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate update payload
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