<?php
declare(strict_types=1);

/**
 * HomepageSectionsValidator
 *
 * Validates all fields including new: component, layout_config.
 * Returns array of errors (empty = valid).
 *
 * Location: api/v1/models/homepage_sections/validators/HomepageSectionsValidator.php
 */
final class HomepageSectionsValidator
{
    private const ALLOWED_SECTION_TYPES = [
        'slider', 'categories', 'featured_products', 'new_products',
        'deals', 'brands', 'vendors', 'banners', 'testimonials',
        'custom_html', 'other',
    ];

    private const ALLOWED_LAYOUT_TYPES = [
        'grid', 'slider', 'list', 'carousel', 'masonry',
    ];

    public function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // section_type — required on create
        if (!$isUpdate || array_key_exists('section_type', $data)) {
            if (empty($data['section_type'])) {
                $errors['section_type'] = 'Section type is required.';
            } elseif (!in_array($data['section_type'], self::ALLOWED_SECTION_TYPES, true)) {
                $errors['section_type'] = 'Invalid section type. Allowed: '
                    . implode(', ', self::ALLOWED_SECTION_TYPES);
            }
        }

        // component (new field — optional string max 60)
        if (isset($data['component'])) {
            if (!is_string($data['component']) || strlen($data['component']) > 60) {
                $errors['component'] = 'Component must be a string of max 60 characters.';
            }
        }

        // title
        if (isset($data['title']) && strlen((string) $data['title']) > 255) {
            $errors['title'] = 'Title must not exceed 255 characters.';
        }

        // subtitle
        if (isset($data['subtitle']) && strlen((string) $data['subtitle']) > 500) {
            $errors['subtitle'] = 'Subtitle must not exceed 500 characters.';
        }

        // layout_type
        if (isset($data['layout_type']) && !in_array($data['layout_type'], self::ALLOWED_LAYOUT_TYPES, true)) {
            $errors['layout_type'] = 'Invalid layout type. Allowed: '
                . implode(', ', self::ALLOWED_LAYOUT_TYPES);
        }

        // layout_config (new field — optional JSON string or array)
        if (isset($data['layout_config']) && $data['layout_config'] !== null) {
            if (is_string($data['layout_config'])) {
                json_decode($data['layout_config']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['layout_config'] = 'layout_config must be valid JSON.';
                }
            } elseif (!is_array($data['layout_config'])) {
                $errors['layout_config'] = 'layout_config must be a JSON string or object.';
            }
        }

        // items_per_row
        if (isset($data['items_per_row'])) {
            $v = (int) $data['items_per_row'];
            if (!is_numeric($data['items_per_row']) || $v < 1 || $v > 12) {
                $errors['items_per_row'] = 'Items per row must be between 1 and 12.';
            }
        }

        // background_color
        if (isset($data['background_color']) && $data['background_color'] !== null) {
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', (string) $data['background_color'])) {
                $errors['background_color'] = 'background_color must be a valid hex color (e.g. #FFFFFF).';
            }
        }

        // text_color
        if (isset($data['text_color']) && $data['text_color'] !== null) {
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', (string) $data['text_color'])) {
                $errors['text_color'] = 'text_color must be a valid hex color (e.g. #000000).';
            }
        }

        // padding
        if (isset($data['padding']) && strlen((string) $data['padding']) > 50) {
            $errors['padding'] = 'Padding must not exceed 50 characters.';
        }

        // custom_css
        if (isset($data['custom_css']) && strlen((string) $data['custom_css']) > 65535) {
            $errors['custom_css'] = 'Custom CSS is too long (max 65535 characters).';
        }

        // custom_html
        if (isset($data['custom_html']) && strlen((string) $data['custom_html']) > 65535) {
            $errors['custom_html'] = 'Custom HTML is too long (max 65535 characters).';
        }

        // data_source
        if (isset($data['data_source']) && strlen((string) $data['data_source']) > 255) {
            $errors['data_source'] = 'Data source must not exceed 255 characters.';
        }

        // is_active
        if (isset($data['is_active']) && !in_array((int) $data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'is_active must be 0 or 1.';
        }

        // sort_order
        if (isset($data['sort_order'])) {
            if (!is_numeric($data['sort_order']) || (int) $data['sort_order'] < 0) {
                $errors['sort_order'] = 'sort_order must be a non-negative integer.';
            }
        }

        // theme_id
        if (isset($data['theme_id']) && $data['theme_id'] !== null) {
            if (!is_numeric($data['theme_id']) || (int) $data['theme_id'] <= 0) {
                $errors['theme_id'] = 'theme_id must be a positive integer.';
            }
        }

        // translations — nested validation for every language provided
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $langCode => $trans) {
                if (!is_string($langCode) || strlen($langCode) > 8) {
                    $errors['translations'][$langCode]['language_code'] = 'Invalid language code.';
                }
                if (isset($trans['title']) && strlen((string) $trans['title']) > 255) {
                    $errors['translations'][$langCode]['title'] = 'Translation title must not exceed 255 characters.';
                }
                if (isset($trans['subtitle']) && strlen((string) $trans['subtitle']) > 500) {
                    $errors['translations'][$langCode]['subtitle'] = 'Translation subtitle must not exceed 500 characters.';
                }
            }
        }

        return $errors;
    }
}