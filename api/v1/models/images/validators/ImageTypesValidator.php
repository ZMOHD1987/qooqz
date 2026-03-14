<?php
declare(strict_types=1);

final class ImageTypesValidator
{
    /**
     * Validate image type data
     */
    public static function validate(array $data, ?int $id = null): array
    {
        $errors = [];

        // ===== code =====
        if (empty($data['code'])) {
            $errors['code'] = 'Code is required';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['code'])) {
            $errors['code'] = 'Code must contain only lowercase letters, numbers, and underscores';
        }

        // ===== name =====
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (mb_strlen($data['name']) > 50) {
            $errors['name'] = 'Name is too long';
        }

        // ===== description =====
        if (!empty($data['description']) && mb_strlen($data['description']) > 255) {
            $errors['description'] = 'Description is too long';
        }

        // ===== width =====
        if (!isset($data['width']) || !is_numeric($data['width']) || (int)$data['width'] < 0) {
    $errors['width'] = 'Width must be zero or positive integer';
}


        // ===== height =====
        if (!isset($data['height']) || !is_numeric($data['height']) || (int)$data['height'] <= 0) {
            $errors['height'] = 'Height must be a positive integer';
        }

        // ===== crop =====
        $allowedCrops = ['fit', 'fill', 'cover'];
        if (!empty($data['crop']) && !in_array($data['crop'], $allowedCrops, true)) {
            $errors['crop'] = 'Invalid crop mode';
        }

        // ===== quality =====
        if (isset($data['quality'])) {
            if (!is_numeric($data['quality']) || (int)$data['quality'] < 1 || (int)$data['quality'] > 100) {
                $errors['quality'] = 'Quality must be between 1 and 100';
            }
        }

        // ===== format =====
        $allowedFormats = ['jpg', 'png', 'webp'];
        if (!empty($data['format']) && !in_array($data['format'], $allowedFormats, true)) {
            $errors['format'] = 'Invalid image format';
        }

        // ===== is_thumbnail =====
        if (isset($data['is_thumbnail']) && !in_array((int)$data['is_thumbnail'], [0, 1], true)) {
            $errors['is_thumbnail'] = 'Invalid thumbnail flag';
        }

        return $errors;
    }
}
