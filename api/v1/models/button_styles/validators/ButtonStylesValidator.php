<?php
declare(strict_types=1);

// api/v1/models/button_styles/validators/ButtonStylesValidator.php

final class ButtonStylesValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) > 255) {
            $errors['name'] = 'Name is too long';
        }

        // slug
        if (empty($data['slug'])) {
            $errors['slug'] = 'Slug is required';
        } elseif (strlen($data['slug']) > 255) {
            $errors['slug'] = 'Slug is too long';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, and hyphens';
        }

        // button_type
        $allowedTypes = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'outline', 'link'];
        if (empty($data['button_type'])) {
            $errors['button_type'] = 'Button type is required';
        } elseif (!in_array($data['button_type'], $allowedTypes)) {
            $errors['button_type'] = 'Invalid button type';
        }

        // background_color
        if (empty($data['background_color'])) {
            $errors['background_color'] = 'Background color is required';
        } elseif (!preg_match('/^#[a-fA-F0-9]{6}$/', $data['background_color'])) {
            $errors['background_color'] = 'Background color must be a valid hex color (e.g., #FF0000)';
        }

        // text_color
        if (empty($data['text_color'])) {
            $errors['text_color'] = 'Text color is required';
        } elseif (!preg_match('/^#[a-fA-F0-9]{6}$/', $data['text_color'])) {
            $errors['text_color'] = 'Text color must be a valid hex color (e.g., #FFFFFF)';
        }

        // border_color (optional)
        if (isset($data['border_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['border_color'])) {
            $errors['border_color'] = 'Border color must be a valid hex color (e.g., #000000)';
        }

        // border_width (optional)
        if (isset($data['border_width']) && (!is_numeric($data['border_width']) || $data['border_width'] < 0)) {
            $errors['border_width'] = 'Border width must be a non-negative integer';
        }

        // border_radius (optional)
        if (isset($data['border_radius']) && (!is_numeric($data['border_radius']) || $data['border_radius'] < 0)) {
            $errors['border_radius'] = 'Border radius must be a non-negative integer';
        }

        // padding (optional)
        if (isset($data['padding']) && strlen($data['padding']) > 50) {
            $errors['padding'] = 'Padding is too long';
        }

        // font_size (optional)
        if (isset($data['font_size']) && strlen($data['font_size']) > 50) {
            $errors['font_size'] = 'Font size is too long';
        }

        // font_weight (optional)
        if (isset($data['font_weight']) && strlen($data['font_weight']) > 50) {
            $errors['font_weight'] = 'Font weight is too long';
        }

        // hover colors (optional)
        if (isset($data['hover_background_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['hover_background_color'])) {
            $errors['hover_background_color'] = 'Hover background color must be a valid hex color';
        }
        if (isset($data['hover_text_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['hover_text_color'])) {
            $errors['hover_text_color'] = 'Hover text color must be a valid hex color';
        }
        if (isset($data['hover_border_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['hover_border_color'])) {
            $errors['hover_border_color'] = 'Hover border color must be a valid hex color';
        }

        // is_active
        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1])) {
            $errors['is_active'] = 'Is active must be 0 or 1';
        }

        // theme_id (optional, bigint)
        if (isset($data['theme_id']) && (!is_numeric($data['theme_id']) || $data['theme_id'] <= 0)) {
            $errors['theme_id'] = 'Theme ID must be a positive integer';
        }

        return $errors;
    }
}