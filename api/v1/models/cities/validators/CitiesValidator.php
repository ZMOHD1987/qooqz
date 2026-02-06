<?php
declare(strict_types=1);

// api/v1/models/cities/validators/CitiesValidator.php

final class CitiesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) > 200) {
            $errors['name'] = 'Name is too long';
        }

        // country_id
        if (empty($data['country_id'])) {
            $errors['country_id'] = 'Country ID is required';
        } elseif (!is_numeric($data['country_id']) || $data['country_id'] <= 0) {
            $errors['country_id'] = 'Country ID must be a positive integer';
        }

        // state (optional)
        if (!empty($data['state']) && strlen($data['state']) > 200) {
            $errors['state'] = 'State is too long';
        }

        // latitude (optional)
        if (!empty($data['latitude']) && (!is_numeric($data['latitude']) || $data['latitude'] < -90 || $data['latitude'] > 90)) {
            $errors['latitude'] = 'Latitude must be between -90 and 90';
        }

        // longitude (optional)
        if (!empty($data['longitude']) && (!is_numeric($data['longitude']) || $data['longitude'] < -180 || $data['longitude'] > 180)) {
            $errors['longitude'] = 'Longitude must be between -180 and 180';
        }

        // translations (optional array)
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $name) {
                if (empty($name) || strlen($name) > 200) {
                    $errors['translations'][$lang] = 'Translation name is invalid';
                }
            }
        }

        return $errors;
    }
}