<?php
declare(strict_types=1);

/**
 * Static validation for subscription plan translation operations.
 */
final class SubscriptionPlanTranslationsValidator
{
    private const ALLOWED_LANGUAGES = [
        'ar', 'en', 'fr', 'de', 'es', 'it', 'pt', 'ru', 'zh', 'ja', 'ko',
        'tr', 'nl', 'sv', 'pl', 'uk', 'hi', 'bn', 'id', 'ms', 'th', 'vi',
        'cs', 'ro', 'hu', 'el',
    ];

    public static function validateUpsert(array $data): array
    {
        $errors = [];

        if (empty($data['plan_id']) || (int)$data['plan_id'] <= 0) {
            $errors[] = 'plan_id is required';
        }

        if (empty($data['language_code']) || !in_array($data['language_code'], self::ALLOWED_LANGUAGES, true)) {
            $errors[] = 'language_code is required and must be a valid language code';
        }

        if (empty($data['plan_name'])) {
            $errors[] = 'plan_name is required';
        }

        return $errors;
    }
}
