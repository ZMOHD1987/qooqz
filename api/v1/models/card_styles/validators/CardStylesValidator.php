<?php
declare(strict_types=1);

// api/v1/models/card_styles/validators/CardStylesValidator.php

final class CardStylesValidator
{
    private const ALLOWED_CARD_TYPES = [
        'product', 'category', 'vendor', 'blog', 'feature', 'testimonial', 'other',
        'auction', 'notification', 'discount', 'jobs', 'plan',
    ];

    private const ALLOWED_HOVER_EFFECTS = ['none', 'lift', 'zoom', 'shadow', 'border', 'brightness'];

    private const ALLOWED_TEXT_ALIGNS = ['left', 'center', 'right'];

    /**
     * Returns the list of allowed card_type values.
     * Used by CardStylesService to auto-derive card_type from slug for legacy rows.
     */
    public static function getAllowedCardTypes(): array
    {
        return self::ALLOWED_CARD_TYPES;
    }

    public function validate(array $data): array
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
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            $errors['slug'] = 'Slug must contain only lowercase letters, numbers, and hyphens';
        }

        // card_type
        if (empty($data['card_type'])) {
            $errors['card_type'] = 'Card type is required';
        } elseif (!in_array($data['card_type'], self::ALLOWED_CARD_TYPES, true)) {
            $errors['card_type'] = 'Invalid card type. Allowed: ' . implode(', ', self::ALLOWED_CARD_TYPES);
        }

        // background_color (optional)
        if (!empty($data['background_color']) && !preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $data['background_color'])) {
            $errors['background_color'] = 'Background color must be a valid hex color (e.g., #FFFFFF)';
        }

        // border_color (optional)
        if (!empty($data['border_color']) && !preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $data['border_color'])) {
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

        // hover_effect (optional)
        if (!empty($data['hover_effect']) && !in_array($data['hover_effect'], self::ALLOWED_HOVER_EFFECTS, true)) {
            $errors['hover_effect'] = 'Invalid hover effect. Allowed: ' . implode(', ', self::ALLOWED_HOVER_EFFECTS);
        }

        // text_align (optional)
        if (!empty($data['text_align']) && !in_array($data['text_align'], self::ALLOWED_TEXT_ALIGNS, true)) {
            $errors['text_align'] = 'Invalid text align. Allowed: ' . implode(', ', self::ALLOWED_TEXT_ALIGNS);
        }

        // image_aspect_ratio (optional)
        if (!empty($data['image_aspect_ratio']) && strlen($data['image_aspect_ratio']) > 50) {
            $errors['image_aspect_ratio'] = 'Image aspect ratio is too long';
        }

        // is_active (optional)
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'is_active must be 0 or 1';
        }

        // theme_id (optional)
        if (isset($data['theme_id']) && $data['theme_id'] !== null && (!is_numeric($data['theme_id']) || (int)$data['theme_id'] <= 0)) {
            $errors['theme_id'] = 'Theme ID must be a positive integer';
        }

        return $errors;
    }
}