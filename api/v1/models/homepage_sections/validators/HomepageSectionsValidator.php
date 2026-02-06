<?php
declare(strict_types=1);

// api/v1/models/homepage_sections/validators/HomepageSectionsValidator.php

final class HomepageSectionsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // section_type
        $allowedTypes = ['slider', 'categories', 'featured_products', 'new_products', 'deals', 'brands', 'vendors', 'banners', 'testimonials', 'custom_html', 'other'];
        if (empty($data['section_type'])) {
            $errors['section_type'] = 'Section type is required';
        } elseif (!in_array($data['section_type'], $allowedTypes)) {
            $errors['section_type'] = 'Invalid section type';
        }

        // title (optional)
        if (isset($data['title']) && strlen($data['title']) > 255) {
            $errors['title'] = 'Title is too long';
        }

        // subtitle (optional)
        if (isset($data['subtitle']) && strlen($data['subtitle']) > 500) {
            $errors['subtitle'] = 'Subtitle is too long';
        }

        // layout_type
        $allowedLayouts = ['grid', 'slider', 'list', 'carousel', 'masonry'];
        if (isset($data['layout_type']) && !in_array($data['layout_type'], $allowedLayouts)) {
            $errors['layout_type'] = 'Invalid layout type';
        }

        // items_per_row (optional)
        if (isset($data['items_per_row']) && (!is_numeric($data['items_per_row']) || $data['items_per_row'] < 1 || $data['items_per_row'] > 12)) {
            $errors['items_per_row'] = 'Items per row must be between 1 and 12';
        }

        // background_color (optional)
        if (isset($data['background_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['background_color'])) {
            $errors['background_color'] = 'Background color must be a valid hex color (e.g., #FFFFFF)';
        }

        // text_color (optional)
        if (isset($data['text_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['text_color'])) {
            $errors['text_color'] = 'Text color must be a valid hex color (e.g., #000000)';
        }

        // padding (optional)
        if (isset($data['padding']) && strlen($data['padding']) > 50) {
            $errors['padding'] = 'Padding is too long';
        }

        // custom_css (optional)
        if (isset($data['custom_css']) && strlen($data['custom_css']) > 65535) {
            $errors['custom_css'] = 'Custom CSS is too long';
        }

        // custom_html (optional)
        if (isset($data['custom_html']) && strlen($data['custom_html']) > 65535) {
            $errors['custom_html'] = 'Custom HTML is too long';
        }

        // data_source (optional)
        if (isset($data['data_source']) && strlen($data['data_source']) > 255) {
            $errors['data_source'] = 'Data source is too long';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // sort_order (optional)
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // theme_id (optional, bigint)
        if (isset($data['theme_id']) && (!is_numeric($data['theme_id']) || $data['theme_id'] <= 0)) {
            $errors['theme_id'] = 'Theme ID must be a positive integer';
        }

        // translations (optional array)
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $trans) {
                if (isset($trans['title']) && strlen($trans['title']) > 255) {
                    $errors['translations'][$lang]['title'] = 'Translation title is too long';
                }
                if (isset($trans['subtitle']) && strlen($trans['subtitle']) > 500) {
                    $errors['translations'][$lang]['subtitle'] = 'Translation subtitle is too long';
                }
            }
        }

        return $errors;
    }
}