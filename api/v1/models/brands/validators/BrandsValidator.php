<?php
declare(strict_types=1);

final class BrandsValidator
{
    /**
     * Validate brand data. Supports translations in two formats:
     * 1) Associative array keyed by language code: ["en" => ["name" => "...", ...], ...]
     * 2) Indexed array with "language_code" key: [["language_code" => "en", "name" => "...", ...], ...]
     */
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // slug مطلوب دائماً
        if (empty($data['slug'])) {
            $errors['slug'] = 'Slug is required';
        } elseif (strlen($data['slug']) > 255) {
            $errors['slug'] = 'Slug is too long';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, and hyphens';
        }

        // entity_id مطلوب (NOT NULL في الجدول)
        if ($isUpdate) {
            if (array_key_exists('entity_id', $data) && (!is_numeric($data['entity_id']) || (int)$data['entity_id'] <= 0)) {
                $errors['entity_id'] = 'Entity ID must be a positive integer';
            }
        } else {
            if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                $errors['entity_id'] = 'Entity ID is required and must be numeric';
            }
        }

        // website_url اختياري
        if (isset($data['website_url']) && strlen($data['website_url']) > 500) {
            $errors['website_url'] = 'Website URL is too long';
        }

        // is_active اختياري، يجب أن يكون 0 أو 1
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // is_featured اختياري، يجب أن يكون 0 أو 1
        if (isset($data['is_featured']) && !in_array((int)$data['is_featured'], [0, 1], true)) {
            $errors['is_featured'] = 'Is featured must be 0 or 1';
        }

        // sort_order اختياري، يجب أن يكون عدداً صحيحاً غير سالب
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || (int)$data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // التحقق من الترجمات
        if (!empty($data['translations']) && is_array($data['translations'])) {
            $normalizedTranslations = self::normalizeTranslations($data['translations']);
            foreach ($normalizedTranslations as $lang => $trans) {
                if (!is_string($lang) || strlen($lang) > 8) {
                    $errors['translations'][$lang] = 'Invalid language code';
                    continue;
                }
                if (isset($trans['name']) && strlen($trans['name']) > 255) {
                    $errors['translations'][$lang]['name'] = 'Name is too long';
                }
                if (isset($trans['meta_title']) && strlen($trans['meta_title']) > 255) {
                    $errors['translations'][$lang]['meta_title'] = 'Meta title is too long';
                }
            }
        }

        return $errors;
    }

    /**
     * Normalize translations to associative array keyed by language code.
     * Supports both formats.
     */
    public static function normalizeTranslations(array $translations): array
    {
        $normalized = [];
        foreach ($translations as $key => $value) {
            // If the key is a string (likely language code), assume already normalized
            if (is_string($key) && strlen($key) <= 8 && is_array($value)) {
                $normalized[$key] = $value;
            }
            // If value is an array and contains 'language_code', use that as key
            elseif (is_array($value) && isset($value['language_code'])) {
                $lang = $value['language_code'];
                unset($value['language_code']);
                $normalized[$lang] = $value;
            }
        }
        return $normalized;
    }
}