<?php
// api/validators/BannerValidator.php
// Validator for banner data

class BannerValidator
{
    /**
     * Validate banner data
     * @param array $data
     * @return bool|array Returns true if valid, array of errors otherwise
     */
    public static function validate($data)
    {
        $errors = [];

        // Title: required (either main or in translations)
        if (empty($data['title']) && empty($data['translations'])) {
            $errors['title'] = 'Title is required';
        }

        // Validate URLs if provided
        if (!empty($data['image_url']) && !self::isValidUrl($data['image_url'])) {
            $errors['image_url'] = 'Invalid image URL';
        }

        if (!empty($data['mobile_image_url']) && !self::isValidUrl($data['mobile_image_url'])) {
            $errors['mobile_image_url'] = 'Invalid mobile image URL';
        }

        if (!empty($data['link_url']) && !self::isValidUrl($data['link_url'])) {
            $errors['link_url'] = 'Invalid link URL';
        }

        // Validate dates
        if (!empty($data['start_date']) && !self::isValidDateTime($data['start_date'])) {
            $errors['start_date'] = 'Invalid start date';
        }

        if (!empty($data['end_date']) && !self::isValidDateTime($data['end_date'])) {
            $errors['end_date'] = 'Invalid end date';
        }

        // Validate is_active
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'Invalid is_active value';
        }

        // Validate translations
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $tr) {
                if (!is_array($tr)) {
                    continue;
                }
                if (empty($tr['title'])) {
                    $errors["translations[$lang][title]"] = 'Translation title required';
                }
            }
        }

        return $errors ? $errors : true;
    }

    /**
     * Check if URL is valid
     * Accepts absolute URLs, root-relative, protocol-relative, and data URIs
     */
    private static function isValidUrl($url)
    {
        if ($url === null || $url === '') {
            return false;
        }

        // Allow data URIs
        if (strpos($url, 'data:') === 0) {
            return true;
        }

        // Absolute URL with scheme
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Root-relative or relative paths
        if (preg_match('#^(//|/|\.\/|\.\./)[ ^\s]+$#', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Check if date/time string is valid
     */
    private static function isValidDateTime($dt)
    {
        return (strtotime($dt) !== false);
    }
}
