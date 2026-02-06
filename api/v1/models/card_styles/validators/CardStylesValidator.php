<?php
declare(strict_types=1);

// api/v1/models/card_styles/validators/CardStylesValidator.php

final class CardStylesValidator
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

        // card_type
        $allowedTypes = ['product', 'category', 'vendor', 'blog', 'feature', 'testimonial', 'other'];
        if (empty($data['card_type'])) {
            $errors['card_type'] = 'Card type is required';
        } elseif (!in_array($data['card_type'], $allowedTypes)) {
            $errors['card_type'] = 'Invalid card type';
        }

        // background_color (optional)
        if (isset($data['background_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['background_color'])) {
            $errors['background_color'] = 'Background color must be a valid hex color (e.g., #FFFFFF)';
        }

        // border_color (optional)
        if (isset($data['border_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $data['border_color'])) {
            $errors['border_color'] = 'Border color must be a valid hex color (e.g., #E0E0E0)';
        }

        // border_width (optional)
        if (isset($data['border_width']) && (!is_numeric($data['border_width']) || $data['border_width'] < 0)) {
            $errors['border_width'] = 'Border width must be a non-negative integer';
        }

        // border_radius (optional)
        if (isset($data['border_radius']) && (!is_numeric($data['border_radius']) || $data['border_radius'] < 0)) {
            $errors['border_radius'] = 'Border radius must be a non-negative integer';
        }

        // shadow_style (optional)
        if (isset($data['shadow_style']) && strlen($data['shadow_style']) > 100) {
            $errors['shadow_style'] = 'Shadow style is too long';
        }

        // padding (optional)
        if (isset($data['padding']) && strlen($data['padding']) > 50) {
            $errors['padding'] = 'Padding is too long';
        }

        // hover_effect
        $allowedEffects = ['none', 'lift', 'zoom', 'shadow', 'border', 'brightness'];
        if (isset($data['hover_effect']) && !in_array($data['hover_effect'], $allowedEffects)) {
            $errors['hover_effect'] = 'Invalid hover effect';
        }

        // text_align
        $allowedAligns = ['left', 'center', 'right'];
        if (isset($data['text_align']) && !in_array($data['text_align'], $allowedAligns)) {
            $errors['text_align'] = 'Invalid text alignment';
        }

        // image_aspect_ratio (optional)
        if (isset($data['image_aspect_ratio']) && strlen($data['image_aspect_ratio']) > 50) {
            $errors['image_aspect_ratio'] = 'Image aspect ratio is too long';
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