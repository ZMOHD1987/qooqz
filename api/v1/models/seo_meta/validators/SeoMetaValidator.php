<?php
declare(strict_types=1);

final class SeoMetaValidator
{
    private const ALLOWED_ENTITY_TYPES = ['product', 'category', 'entity', 'page'];

    /**
     * Validate data for creating a SEO meta entry
     */
    public static function validateCreate(array $data): void
    {
        if (empty($data['entity_type']) || !is_string($data['entity_type'])) {
            throw new InvalidArgumentException("Field 'entity_type' is required and must be a string");
        }

        if (!in_array($data['entity_type'], self::ALLOWED_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException("Field 'entity_type' must be one of: " . implode(', ', self::ALLOWED_ENTITY_TYPES));
        }

        if (!isset($data['entity_id']) || !is_numeric($data['entity_id']) || (int)$data['entity_id'] <= 0) {
            throw new InvalidArgumentException("Field 'entity_id' is required and must be an integer greater than 0");
        }

        self::validateCommonFields($data);
    }

    /**
     * Validate data for updating a SEO meta entry
     */
    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for update");
        }

        if (!isset($data['id']) || !is_numeric($data['id']) || (int)$data['id'] <= 0) {
            throw new InvalidArgumentException("Field 'id' is required and must be an integer greater than 0");
        }

        if (isset($data['entity_type'])) {
            if (!is_string($data['entity_type']) || !in_array($data['entity_type'], self::ALLOWED_ENTITY_TYPES, true)) {
                throw new InvalidArgumentException("Field 'entity_type' must be one of: " . implode(', ', self::ALLOWED_ENTITY_TYPES));
            }
        }

        if (isset($data['entity_id']) && (!is_numeric($data['entity_id']) || (int)$data['entity_id'] <= 0)) {
            throw new InvalidArgumentException("Field 'entity_id' must be an integer greater than 0");
        }

        self::validateCommonFields($data);
    }

    /**
     * Validate a translation entry
     */
    public static function validateTranslation(array $data): void
    {
        if (empty($data['seo_meta_id']) || !is_numeric($data['seo_meta_id'])) {
            throw new InvalidArgumentException("Field 'seo_meta_id' is required and must be numeric");
        }

        if (empty($data['language_code']) || !is_string($data['language_code'])) {
            throw new InvalidArgumentException("Field 'language_code' is required");
        }

        if (mb_strlen($data['language_code']) > 8) {
            throw new InvalidArgumentException("Field 'language_code' must not exceed 8 characters");
        }

        $contentFields = ['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'og_image'];
        $hasContent = false;
        foreach ($contentFields as $field) {
            if (!empty($data[$field])) {
                $hasContent = true;
                break;
            }
        }

        if (!$hasContent) {
            throw new InvalidArgumentException("At least one of meta_title, meta_description, meta_keywords, og_title, og_description, og_image is required");
        }

        if (isset($data['meta_title']) && mb_strlen($data['meta_title']) > 255) {
            throw new InvalidArgumentException("Field 'meta_title' must not exceed 255 characters");
        }

        if (isset($data['meta_keywords']) && mb_strlen($data['meta_keywords']) > 255) {
            throw new InvalidArgumentException("Field 'meta_keywords' must not exceed 255 characters");
        }

        if (isset($data['og_title']) && mb_strlen($data['og_title']) > 255) {
            throw new InvalidArgumentException("Field 'og_title' must not exceed 255 characters");
        }

        if (isset($data['og_image']) && mb_strlen($data['og_image']) > 255) {
            throw new InvalidArgumentException("Field 'og_image' must not exceed 255 characters");
        }
    }

    /**
     * Common field validation
     */
    private static function validateCommonFields(array $data): void
    {
        if (isset($data['canonical_url']) && mb_strlen($data['canonical_url']) > 255) {
            throw new InvalidArgumentException("Field 'canonical_url' must not exceed 255 characters");
        }

        if (isset($data['robots']) && mb_strlen($data['robots']) > 255) {
            throw new InvalidArgumentException("Field 'robots' must not exceed 255 characters");
        }

        if (isset($data['tenant_id']) && (!is_numeric($data['tenant_id']) || (int)$data['tenant_id'] <= 0)) {
            throw new InvalidArgumentException("Field 'tenant_id' must be a positive integer");
        }
    }
}