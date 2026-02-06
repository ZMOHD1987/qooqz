<?php
declare(strict_types=1);

final class CategoriesValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        $isUpdate = !empty($data['id']);

        /* ============================================================
         * SLUG (required on create only)
         * ============================================================ */
        if (!$isUpdate || array_key_exists('slug', $data)) {
            if (empty($data['slug'])) {
                $errors['slug'] = 'Slug is required';
            } elseif (mb_strlen($data['slug']) > 255) {
                $errors['slug'] = 'Slug is too long';
            }
            // ملاحظة: لا نقيّد regex لأن المشروع يدعم العربي
        }

        /* ============================================================
         * NAME (required on create only)
         * ============================================================ */
        if (!$isUpdate || array_key_exists('name', $data)) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            } elseif (mb_strlen($data['name']) > 255) {
                $errors['name'] = 'Name is too long';
            }
        }

        /* ============================================================
         * DESCRIPTION
         * ============================================================ */
        if (isset($data['description']) && mb_strlen($data['description']) > 65535) {
            $errors['description'] = 'Description is too long';
        }

        /* ============================================================
         * parent_id
         * ============================================================ */
        if (array_key_exists('parent_id', $data)) {
            if ($data['parent_id'] !== null && (!is_numeric($data['parent_id']) || (int)$data['parent_id'] < 0)) {
                $errors['parent_id'] = 'Parent ID must be a non-negative integer or null';
            }
        }

        /* ============================================================
         * sort_order
         * ============================================================ */
        if (isset($data['sort_order']) && (!is_numeric($data['sort_order']) || (int)$data['sort_order'] < 0)) {
            $errors['sort_order'] = 'Sort order must be a non-negative integer';
        }

        /* ============================================================
         * is_active / is_featured
         * ============================================================ */
        foreach (['is_active', 'is_featured'] as $flag) {
            if (isset($data[$flag])) {
                $val = $data[$flag];
                if (
                    !in_array($val, [0, 1, '0', '1', true, false], true)
                ) {
                    $errors[$flag] = ucfirst(str_replace('_', ' ', $flag)) . ' must be 0 or 1';
                }
            }
        }

        /* ============================================================
         * TRANSLATIONS
         * ============================================================ */
        if (!empty($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $key => $trans) {

                // دعم array indexed أو keyed
                $lang = $trans['language_code'] ?? (is_string($key) ? $key : null);

                if (!$lang) {
                    $errors['translations'][$key]['language_code'] = 'Language code is required';
                    continue;
                }

                if (isset($trans['name']) && mb_strlen($trans['name']) > 255) {
                    $errors['translations'][$lang]['name'] = 'Translation name is too long';
                }

                if (isset($trans['slug']) && mb_strlen($trans['slug']) > 255) {
                    $errors['translations'][$lang]['slug'] = 'Translation slug is too long';
                }

                if (isset($trans['description']) && mb_strlen($trans['description']) > 65535) {
                    $errors['translations'][$lang]['description'] = 'Translation description is too long';
                }

                if (isset($trans['meta_title']) && mb_strlen($trans['meta_title']) > 255) {
                    $errors['translations'][$lang]['meta_title'] = 'Translation meta title is too long';
                }

                if (isset($trans['meta_description']) && mb_strlen($trans['meta_description']) > 65535) {
                    $errors['translations'][$lang]['meta_description'] = 'Translation meta description is too long';
                }

                if (isset($trans['meta_keywords']) && mb_strlen($trans['meta_keywords']) > 500) {
                    $errors['translations'][$lang]['meta_keywords'] = 'Translation meta keywords is too long';
                }
            }
        }

        return $errors;
    }
}
