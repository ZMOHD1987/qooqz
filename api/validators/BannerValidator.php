<?php
// api/validators/BannerValidator.php
// Robust validator for banners

if (!class_exists('BannerValidator')) {

class BannerValidator
{
    /**
     * Validate banner payload
     * @param array $data
     * @return true|array
     */
    public static function validate(array $data)
    {
        $errors = [];

        /* ===============================
         * Title validation
         * =============================== */

        $hasMainTitle = !empty(trim((string)($data['title'] ?? '')));
        $hasTranslationTitle = false;

        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $tr) {
                if (is_array($tr) && !empty(trim((string)($tr['title'] ?? '')))) {
                    $hasTranslationTitle = true;
                }
            }
        }

        if (!$hasMainTitle && !$hasTranslationTitle) {
            $errors['title'] = 'Title is required (main or translation)';
        }

        /* ===============================
         * URLs validation
         * =============================== */

        foreach (['image_url', 'mobile_image_url', 'link_url'] as $urlField) {
            if (!empty($data[$urlField]) && !self::isValidUrl($data[$urlField])) {
                $errors[$urlField] = 'Invalid URL format';
            }
        }

        /* ===============================
         * Date validation
         * =============================== */

        if (!empty($data['start_date']) && !self::isValidDateTime($data['start_date'])) {
            $errors['start_date'] = 'Invalid start_date';
        }

        if (!empty($data['end_date']) && !self::isValidDateTime($data['end_date'])) {
            $errors['end_date'] = 'Invalid end_date';
        }

        /* ===============================
         * is_active validation
         * =============================== */

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'is_active must be 0 or 1';
        }

        /* ===============================
         * position enum validation
         * =============================== */

        if (!empty($data['position'])) {
            $allowed = [
                'homepage_main',
                'homepage_secondary',
                'category',
                'product',
                'custom'
            ];

            if (!in_array($data['position'], $allowed, true)) {
                $errors['position'] = 'Invalid banner position';
            }
        }

        /* ===============================
         * translations validation
         * =============================== */

        if (!empty($data['translations'])) {
            if (!is_array($data['translations'])) {
                $errors['translations'] = 'Translations must be an array';
            } else {
                foreach ($data['translations'] as $lang => $tr) {
                    if (!is_array($tr)) {
                        $errors["translations.$lang"] = 'Invalid translation format';
                        continue;
                    }

                    if (empty(trim((string)($tr['title'] ?? '')))) {
                        $errors["translations.$lang.title"] = 'Translation title is required';
                    }

                    if (!empty($tr['link_text']) && !is_string($tr['link_text'])) {
                        $errors["translations.$lang.link_text"] = 'Invalid link_text';
                    }
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    /* =====================================================
     * Helpers
     * ===================================================== */

    private static function isValidUrl($url)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        // data URI
        if (strpos($url, 'data:') === 0) {
            return true;
        }

        // absolute URL
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }

        // relative or root-relative
        return (bool)preg_match('#^(//|/|\./|\.\./)[^\s]+$#', $url);
    }

    private static function isValidDateTime($value)
    {
        if (!is_string($value)) {
            return false;
        }

        // Normalize ISO format
        $value = str_replace('T', ' ', $value);

        return strtotime($value) !== false;
    }
}

}
