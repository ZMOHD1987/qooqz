<?php
declare(strict_types=1);

final class BadWordsValidator
{
    /**
     * Validate data for creating a bad word
     */
    public static function validateCreate(array $data): void
    {
        if (empty($data['word']) || !is_string($data['word'])) {
            throw new InvalidArgumentException("Field 'word' is required and must be a string");
        }

        if (mb_strlen($data['word']) > 255) {
            throw new InvalidArgumentException("Field 'word' must not exceed 255 characters");
        }

        self::validateCommonFields($data);
    }

    /**
     * Validate data for updating a bad word
     */
    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for update");
        }

        if (isset($data['word']) && (!is_string($data['word']) || trim($data['word']) === '')) {
            throw new InvalidArgumentException("Field 'word' must be a non-empty string");
        }

        if (isset($data['word']) && mb_strlen($data['word']) > 255) {
            throw new InvalidArgumentException("Field 'word' must not exceed 255 characters");
        }

        self::validateCommonFields($data);
    }

    /**
     * Validate a translation entry
     */
    public static function validateTranslation(array $data): void
    {
        if (empty($data['bad_word_id']) || !is_numeric($data['bad_word_id'])) {
            throw new InvalidArgumentException("Field 'bad_word_id' is required and must be numeric");
        }

        if (empty($data['language_code']) || !is_string($data['language_code'])) {
            throw new InvalidArgumentException("Field 'language_code' is required");
        }

        $allowedLangs = ['ar', 'en', 'fr', 'de', 'es', 'tr', 'fa', 'ur', 'he', 'zh', 'ja', 'ko', 'hi', 'ru', 'pt', 'it'];
        if (!in_array($data['language_code'], $allowedLangs, true)) {
            throw new InvalidArgumentException("Invalid language_code. Allowed: " . implode(', ', $allowedLangs));
        }

        if (empty($data['word']) || !is_string($data['word'])) {
            throw new InvalidArgumentException("Field 'word' is required for translation");
        }

        if (mb_strlen($data['word']) > 255) {
            throw new InvalidArgumentException("Field 'word' must not exceed 255 characters");
        }
    }

    /**
     * Validate text check request
     */
    public static function validateCheck(array $data): void
    {
        if (!isset($data['text']) || !is_string($data['text'])) {
            throw new InvalidArgumentException("Field 'text' is required and must be a string");
        }

        if (mb_strlen($data['text']) > 10000) {
            throw new InvalidArgumentException("Field 'text' must not exceed 10000 characters");
        }
    }

    /**
     * Common field validation
     */
    private static function validateCommonFields(array $data): void
    {
        if (isset($data['severity'])) {
            $allowed = ['low', 'medium', 'high'];
            if (!in_array($data['severity'], $allowed, true)) {
                throw new InvalidArgumentException("Field 'severity' must be one of: " . implode(', ', $allowed));
            }
        }

        if (isset($data['is_regex']) && !in_array((int)$data['is_regex'], [0, 1], true)) {
            throw new InvalidArgumentException("Field 'is_regex' must be 0 or 1");
        }

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            throw new InvalidArgumentException("Field 'is_active' must be 0 or 1");
        }

        // Validate regex pattern if is_regex = 1
        if (!empty($data['is_regex']) && isset($data['word'])) {
            $prevHandler = set_error_handler(fn() => true);
            $result = preg_match('/' . $data['word'] . '/u', '');
            set_error_handler($prevHandler);
            if ($result === false) {
                throw new InvalidArgumentException("Invalid regex pattern in 'word' field");
            }
        }
    }
}