<?php
declare(strict_types=1);

// api/v1/models/banners/validators/BannersValidator.php

final class BannersValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // title
        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        } elseif (strlen($data['title']) > 255) {
            $errors['title'] = 'Title is too long';
        }

        // subtitle (optional)
        if (isset($data['subtitle']) && strlen($data['subtitle']) > 500) {
            $errors['subtitle'] = 'Subtitle is too long';
        }

        // image_url
        if (empty($data['image_url'])) {
            $errors['image_url'] = 'Image URL is required';
        } elseif (strlen($data['image_url']) > 500) {
            $errors['image_url'] = 'Image URL is too long';
        }

        // mobile_image_url (optional)
        if (isset($data['mobile_image_url']) && strlen($data['mobile_image_url']) > 500) {
            $errors['mobile_image_url'] = 'Mobile image URL is too long';
        }

        // link_url (optional)
        if (isset($data['link_url']) && strlen($data['link_url']) > 500) {
            $errors['link_url'] = 'Link URL is too long';
        }

        // link_text (optional)
        if (isset($data['link_text']) && strlen($data['link_text']) > 100) {
            $errors['link_text'] = 'Link text is too long';
        }

        // position
        $allowedPositions = ['homepage_main', 'homepage_secondary', 'category_top', 'product_sidebar', 'footer', 'popup', 'other'];
        if (isset($data['position']) && !in_array($data['position'], $allowedPositions)) {
            $errors['position'] = 'Invalid position';
        }

        // background_color (optional)
        if (isset($data['background_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['background_color'])) {
            $errors['background_color'] = 'Background color must be a valid hex color (e.g., #FFFFFF)';
        }

        // text_color (optional)
        if (isset($data['text_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['text_color'])) {
            $errors['text_color'] = 'Text color must be a valid hex color (e.g., #000000)';
        }

        // button_style (optional)
        if (isset($data['button_style']) && strlen($data['button_style']) > 100) {
            $errors['button_style'] = 'Button style is too long';
        }

        // sort_order (optional)
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || $data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // start_date (optional)
        if (isset($data['start_date']) && !strtotime($data['start_date'])) {
            $errors['start_date'] = 'Invalid start date';
        }

        // end_date (optional)
        if (isset($data['end_date']) && !strtotime($data['end_date'])) {
            $errors['end_date'] = 'Invalid end date';
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
                if (isset($trans['link_text']) && strlen($trans['link_text']) > 100) {
                    $errors['translations'][$lang]['link_text'] = 'Translation link text is too long';
                }
            }
        }

        return $errors;
    }
}