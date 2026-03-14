<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntitiesAttributesValidator
{
    /**
     * التحقق من صحة البيانات لإنشاء خاصية جديدة
     */
    public static function validateCreate(array $data): void
    {
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new InvalidArgumentException("Field 'name' is required and must be a string");
        }

        self::validateCommonFields($data);
        self::validateTranslations($data);
    }

    /**
     * التحقق من صحة البيانات لتحديث خاصية موجودة
     */
    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for update");
        }

        self::validateCommonFields($data);
        self::validateTranslations($data);
    }

    /**
     * التحقق من الحقول المشتركة بين الإنشاء والتحديث
     */
    private static function validateCommonFields(array $data): void
    {
        // التحقق من نوع الخاصية
        if (isset($data['attribute_type'])) {
            $allowedTypes = ['text', 'number', 'select', 'boolean'];
            if (!in_array($data['attribute_type'], $allowedTypes, true)) {
                throw new InvalidArgumentException("attribute_type must be one of: " . implode(', ', $allowedTypes));
            }
        }

        // التحقق من الحقل المنطقي (Boolean)
        if (isset($data['is_required']) && !in_array((int)$data['is_required'], [0, 1], true)) {
            throw new InvalidArgumentException("is_required must be 0 or 1");
        }

        // التحقق من الترتيب
        if (isset($data['sort_order']) && !is_numeric($data['sort_order'])) {
            throw new InvalidArgumentException("sort_order must be numeric");
        }

        // التحقق من طول slug
        if (isset($data['slug']) && strlen($data['slug']) > 100) {
            throw new InvalidArgumentException("slug must be at most 100 characters");
        }

        // التحقق من صيغة slug
        if (isset($data['slug']) && !preg_match('/^[a-z0-9\p{Arabic}\-]+$/u', $data['slug'])) {
            throw new InvalidArgumentException("slug can only contain lowercase letters, numbers, Arabic letters, and hyphens");
        }
    }

    /**
     * التحقق من الترجمات
     */
    private static function validateTranslations(array $data): void
    {
        // التحقق من الترجمة الرئيسية (الاسم)
        if (isset($data['name']) && (!is_string($data['name']) || strlen(trim($data['name'])) === 0)) {
            throw new InvalidArgumentException("name must be a non-empty string");
        }

        if (isset($data['name']) && strlen($data['name']) > 255) {
            throw new InvalidArgumentException("name must be at most 255 characters");
        }

        // التحقق من الوصف
        if (isset($data['description']) && !is_string($data['description'])) {
            throw new InvalidArgumentException("description must be a string");
        }

        // التحقق من الترجمات الإضافية
        if (isset($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $langCode => $translation) {
                if (!is_string($langCode) || strlen($langCode) === 0) {
                    throw new InvalidArgumentException("Language code must be a non-empty string");
                }

                if (!is_array($translation)) {
                    throw new InvalidArgumentException("Translation for language '$langCode' must be an array");
                }

                if (empty($translation['name']) || !is_string($translation['name'])) {
                    throw new InvalidArgumentException("Translation name for language '$langCode' is required and must be a string");
                }

                if (isset($translation['description']) && !is_string($translation['description'])) {
                    throw new InvalidArgumentException("Translation description for language '$langCode' must be a string");
                }
            }
        }
    }

    /**
     * التحقق من صحة كود اللغة
     */
    public static function validateLanguageCode(string $languageCode): void
    {
        if (!preg_match('/^[a-z]{2,3}(-[a-z]{2,3})?$/', $languageCode)) {
            throw new InvalidArgumentException("Invalid language code format");
        }
    }
}