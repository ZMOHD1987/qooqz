<?php
declare(strict_types=1);

// api/v1/models/brands/validators/BrandsValidator.php

final class BrandsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // slug
        if (empty($data['slug'])) {
            $errors['slug'] = 'Slug is required';
        } elseif (strlen($data['slug']) > 255) {
            $errors['slug'] = 'Slug is too long';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, and hyphens';
        }

        // logo_url (optional)
        if (isset($data['logo_url']) && strlen($data['logo_url']) > 500) {
            $errors['logo_url'] = 'Logo URL is too long';
        }

        // banner_url (optional)
        if (isset($data['banner_url']) && strlen($data['banner_url']) > 500) {
            $errors['banner_url'] = 'Banner URL is too long';
        }

        // website_url (optional)
        if (isset($data['website_url']) && strlen($data['website_url']) > 500) {
            $errors['website_url'] = 'Website URL is too long';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // is_featured
        if (isset($data['is_featured']) && !in_array($data['is_featured'], [0, 1])) {
            $errors['is_featured'] = 'Is featured must be 0 or 1';
        }

        // sort_order (optional)
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // translations (optional array)
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $trans) {
                if (isset($trans['name']) && strlen($trans['name']) > 255) {
                    $errors['translations'][$lang]['name'] = 'Translation name is too long';
                }
                if (isset($trans['description']) && strlen($trans['description']) > 65535) {
                    $errors['translations'][$lang]['description'] = 'Translation description is too long';
                }
                if (isset($trans['meta_title']) && strlen($trans['meta_title']) > 255) {
                    $errors['translations'][$lang]['meta_title'] = 'Translation meta title is too long';
                }
                if (isset($trans['meta_description']) && strlen($trans['meta_description']) > 65535) {
                    $errors['translations'][$lang]['meta_description'] = 'Translation meta description is too long';
                }
            }
        }

        return $errors;
    }
}