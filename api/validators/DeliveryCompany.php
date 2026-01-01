<?php
/**
 * api/validators/DeliveryCompany.php
 *
 * Validation helpers for DeliveryCompany create/update payloads.
 *
 * Save as UTF-8 without BOM.
 */

declare(strict_types=1);

if (!function_exists('validate_delivery_company_create')) {
    function validate_delivery_company_create(array $input): array
    {
        $errors = [];
        $data = [];

        // name required
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') $errors['name'] = 'Name is required';
        $data['name'] = $name;

        // optional string fields
        $data['slug'] = isset($input['slug']) ? trim((string)$input['slug']) : null;
        $data['phone'] = isset($input['phone']) ? trim((string)$input['phone']) : null;
        $data['email'] = isset($input['email']) ? trim((string)$input['email']) : null;
        $data['website_url'] = isset($input['website_url']) ? trim((string)$input['website_url']) : null;
        $data['api_url'] = isset($input['api_url']) ? trim((string)$input['api_url']) : null;
        $data['api_key'] = isset($input['api_key']) ? trim((string)$input['api_key']) : null;
        $data['tracking_url'] = isset($input['tracking_url']) ? trim((string)$input['tracking_url']) : null;

        // optional numeric fields
        $data['parent_id'] = isset($input['parent_id']) && $input['parent_id'] !== '' ? (int)$input['parent_id'] : null;
        $data['country_id'] = isset($input['country_id']) && $input['country_id'] !== '' ? (int)$input['country_id'] : null;
        $data['city_id'] = isset($input['city_id']) && $input['city_id'] !== '' ? (int)$input['city_id'] : null;
        $data['is_active'] = isset($input['is_active']) ? ((int)$input['is_active'] ? 1 : 0) : 0;
        $data['sort_order'] = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
        $data['rating_average'] = isset($input['rating_average']) ? (float)str_replace(',', '.', $input['rating_average']) : 0.0;

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }
}

if (!function_exists('validate_delivery_company_update')) {
    function validate_delivery_company_update(array $input, bool $isAdmin = false): array
    {
        $errors = [];
        $data = [];

        $allowedAdmin = ['parent_id','user_id','name','slug','phone','email','website_url','api_url','api_key','tracking_url','city_id','country_id','is_active','sort_order','rating_average','rating_count','logo_url'];
        $allowedOwner = ['name','phone','email','website_url','tracking_url','city_id','country_id'];

        $allowed = $isAdmin ? $allowedAdmin : $allowedOwner;

        foreach ($allowed as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== '') {
                if (in_array($f, ['parent_id','user_id','city_id','country_id','is_active','sort_order','rating_count'], true)) {
                    $data[$f] = (int)$input[$f];
                } elseif ($f === 'rating_average') {
                    $data[$f] = (float)str_replace(',', '.', $input[$f]);
                } else {
                    $data[$f] = trim((string)$input[$f]);
                }
            }
        }

        if (isset($data['name']) && $data['name'] === '') $errors['name'] = 'Name is required';

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }
}