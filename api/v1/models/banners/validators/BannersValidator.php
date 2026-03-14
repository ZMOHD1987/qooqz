<?php
declare(strict_types=1);

// api/v1/models/banners/validators/BannersValidator.php
// Updated: removed image_url / mobile_image_url validation (now handled via images table).

final class BannersValidator
{
    private const ALLOWED_POSITIONS = [
        'homepage_main', 'homepage_secondary', 'category_top',
        'product_sidebar', 'footer', 'popup', 'other',
    ];

    public function validate(array $data): array
    {
        $errors = [];

        // title — required; EN translation is mandatory but comes through translations array
        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        } elseif (mb_strlen((string)$data['title']) > 255) {
            $errors['title'] = 'Title is too long (max 255 characters)';
        }

        // subtitle — optional
        if (isset($data['subtitle']) && mb_strlen((string)$data['subtitle']) > 500) {
            $errors['subtitle'] = 'Subtitle is too long (max 500 characters)';
        }

        // link_url — optional
        if (isset($data['link_url']) && mb_strlen((string)$data['link_url']) > 500) {
            $errors['link_url'] = 'Link URL is too long (max 500 characters)';
        }

        // link_text — optional
        if (isset($data['link_text']) && mb_strlen((string)$data['link_text']) > 100) {
            $errors['link_text'] = 'Link text is too long (max 100 characters)';
        }

        // position — optional, must be a valid enum value
        if (isset($data['position']) && !in_array($data['position'], self::ALLOWED_POSITIONS, true)) {
            $errors['position'] = 'Invalid position value';
        }

        // background_color — optional hex color
        if (isset($data['background_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', (string)$data['background_color'])) {
            $errors['background_color'] = 'Background color must be a valid hex color (e.g. #FFFFFF)';
        }

        // text_color — optional hex color
        if (isset($data['text_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', (string)$data['text_color'])) {
            $errors['text_color'] = 'Text color must be a valid hex color (e.g. #000000)';
        }

        // button_style — optional
        if (isset($data['button_style']) && mb_strlen((string)$data['button_style']) > 100) {
            $errors['button_style'] = 'Button style is too long (max 100 characters)';
        }

        // sort_order — optional non-negative integer
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || (int)$data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        // is_active — optional 0/1
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'is_active must be 0 or 1';
        }

        // start_date / end_date — optional valid datetime
        if (!empty($data['start_date']) && !strtotime((string)$data['start_date'])) {
            $errors['start_date'] = 'Invalid start date';
        }
        if (!empty($data['end_date']) && !strtotime((string)$data['end_date'])) {
            $errors['end_date'] = 'Invalid end date';
        }

        // theme_id — optional positive integer
        if (!empty($data['theme_id']) && (!is_numeric($data['theme_id']) || (int)$data['theme_id'] <= 0)) {
            $errors['theme_id'] = 'Theme ID must be a positive integer';
        }

        // image_id — optional positive integer (references images.id)
        if (!empty($data['image_id']) && (!is_numeric($data['image_id']) || (int)$data['image_id'] <= 0)) {
            $errors['image_id'] = 'Image ID must be a positive integer';
        }

        // translations — optional nested array
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $t) {
                if (!is_array($t)) continue;
                if (isset($t['title'])     && mb_strlen((string)$t['title'])     > 255) $errors["translations.{$lang}.title"]     = 'Translation title is too long';
                if (isset($t['subtitle'])  && mb_strlen((string)$t['subtitle'])  > 500) $errors["translations.{$lang}.subtitle"]  = 'Translation subtitle is too long';
                if (isset($t['link_text']) && mb_strlen((string)$t['link_text']) > 100) $errors["translations.{$lang}.link_text"] = 'Translation link text is too long';
            }
        }

        return $errors;
    }
}