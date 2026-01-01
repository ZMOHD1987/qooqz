<?php
// htdocs/api/validators/BannerValidator.php
class BannerValidator
{
    public static function validate(&$data)
    {
        $errors = [];

        // Title: require either main title or translations with title
        if (isset($data['title']) && trim($data['title']) === '') $data['title'] = '';
        if (empty($data['title']) && empty($data['translations'])) {
            $errors['title'] = 'Title is required';
        }

        // Validate image_url and mobile_image_url and link_url if provided
        if (!empty($data['image_url']) && !self::isValidUrl($data['image_url'])) {
            $errors['image_url'] = 'Invalid image URL';
        }
        if (!empty($data['mobile_image_url']) && !self::isValidUrl($data['mobile_image_url'])) {
            $errors['mobile_image_url'] = 'Invalid mobile image URL';
        }
        if (!empty($data['link_url']) && !self::isValidUrl($data['link_url'])) {
            $errors['link_url'] = 'Invalid link URL';
        }

        if (!empty($data['start_date']) && !self::isValidDateTime($data['start_date'])) $errors['start_date'] = 'Invalid datetime';
        if (!empty($data['end_date']) && !self::isValidDateTime($data['end_date'])) $errors['end_date'] = 'Invalid datetime';
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0,1], true)) $errors['is_active'] = 'Invalid is_active';

        // translations check
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $lang => $tr) {
                if (empty($tr['title'])) $errors['translations'][$lang]['title'] = 'Title required';
            }
        }

        return $errors ? $errors : true;
    }

    // Accept absolute URLs, root-relative (/path...), protocol-relative (//domain/...), relative (./../),
    // and data URIs (data:image/...)
    private static function isValidUrl($u) {
        if ($u === null || $u === '') return false;

        // Allow data URIs
        if (strpos($u, 'data:') === 0) return true;

        // Absolute URL with scheme
        if (filter_var($u, FILTER_VALIDATE_URL)) return true;

        // Root-relative or relative paths: /images/..., ./img/..., ../img/..., //cdn.example.com/...
        if (preg_match('#^(//|/|\.\/|\.\./)[^\s]+$#', $u)) return true;

        return false;
    }

    private static function isValidDateTime($d) { return (strtotime($d) !== false); }
}