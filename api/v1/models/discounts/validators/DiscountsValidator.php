<?php
declare(strict_types=1);

/**
 * Static validation methods for discount operations.
 * Each method returns ['valid' => bool, 'errors' => array].
 */
final class DiscountsValidator
{
    private const ALLOWED_TYPES = ['percentage', 'fixed', 'buy_x_get_y', 'free_shipping'];
    private const ALLOWED_STATUSES = ['active', 'inactive', 'expired', 'scheduled'];
    private const ALLOWED_LANGUAGES = [
        'ar', 'en', 'fr', 'de', 'es', 'it', 'pt', 'ru', 'zh', 'ja', 'ko',
        'tr', 'nl', 'sv', 'pl', 'uk', 'hi', 'bn', 'id', 'ms', 'th', 'vi',
        'cs', 'ro', 'hu', 'el',
    ];
    private const ALLOWED_SCOPE_TYPES = [
        'product', 'category', 'brand', 'collection', 'supplier', 'customer_group', 'all',
    ];
    private const ALLOWED_CONDITION_TYPES = [
        'min_cart_total', 'min_items_count', 'first_order_only', 'weekend_only',
        'specific_payment_method', 'customer_segment', 'geo_location',
        'time_window', 'custom_rule',
    ];
    private const ALLOWED_OPERATORS = [
        '=', '>', '<', '>=', '<=', '<>', 'in', 'not_in', 'between', 'contains',
    ];
    private const ALLOWED_ACTION_TYPES = [
        'percentage', 'fixed', 'free_shipping', 'buy_x_get_y', 'free_item',
    ];

    /**
     * Validate data for creating a discount.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['entity_id'])) {
            $errors[] = "Field 'entity_id' is required";
        }

        if (empty($data['type']) || !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors[] = "Field 'type' is required and must be one of: " . implode(', ', self::ALLOWED_TYPES);
        }

        if (empty($data['currency_code']) || !is_string($data['currency_code'])) {
            $errors[] = "Field 'currency_code' is required";
        } elseif (mb_strlen($data['currency_code']) > 3) {
            $errors[] = "Field 'currency_code' must not exceed 3 characters";
        }

        if (isset($data['code']) && mb_strlen((string)$data['code']) > 50) {
            $errors[] = "Field 'code' must not exceed 50 characters";
        }

        if (isset($data['auto_apply']) && !in_array((int)$data['auto_apply'], [0, 1], true)) {
            $errors[] = "Field 'auto_apply' must be 0 or 1";
        }

        if (isset($data['is_stackable']) && !in_array((int)$data['is_stackable'], [0, 1], true)) {
            $errors[] = "Field 'is_stackable' must be 0 or 1";
        }

        if (isset($data['priority']) && (int)$data['priority'] < 0) {
            $errors[] = "Field 'priority' must be a non-negative integer";
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = "Field 'status' must be one of: " . implode(', ', self::ALLOWED_STATUSES);
        }

        self::validateDates($data, $errors);

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate data for updating a discount.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (empty($data)) {
            $errors[] = "No data provided for update";
            return ['valid' => false, 'errors' => $errors];
        }

        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors[] = "Field 'type' must be one of: " . implode(', ', self::ALLOWED_TYPES);
        }

        if (isset($data['currency_code']) && mb_strlen((string)$data['currency_code']) > 3) {
            $errors[] = "Field 'currency_code' must not exceed 3 characters";
        }

        if (isset($data['code']) && mb_strlen((string)$data['code']) > 50) {
            $errors[] = "Field 'code' must not exceed 50 characters";
        }

        if (isset($data['auto_apply']) && !in_array((int)$data['auto_apply'], [0, 1], true)) {
            $errors[] = "Field 'auto_apply' must be 0 or 1";
        }

        if (isset($data['is_stackable']) && !in_array((int)$data['is_stackable'], [0, 1], true)) {
            $errors[] = "Field 'is_stackable' must be 0 or 1";
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = "Field 'status' must be one of: " . implode(', ', self::ALLOWED_STATUSES);
        }

        self::validateDates($data, $errors);

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a translation entry.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateTranslation(array $data): array
    {
        $errors = [];

        if (empty($data['discount_id']) || !is_numeric($data['discount_id'])) {
            $errors[] = "Field 'discount_id' is required and must be numeric";
        }

        if (empty($data['language_code']) || !is_string($data['language_code'])) {
            $errors[] = "Field 'language_code' is required";
        } elseif (!in_array($data['language_code'], self::ALLOWED_LANGUAGES, true)) {
            $errors[] = "Invalid language_code. Allowed: " . implode(', ', self::ALLOWED_LANGUAGES);
        }

        if (empty($data['name']) || !is_string($data['name'])) {
            $errors[] = "Field 'name' is required for translation";
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = "Field 'name' must not exceed 255 characters";
        }

        if (isset($data['description']) && mb_strlen((string)$data['description']) > 2000) {
            $errors[] = "Field 'description' must not exceed 2000 characters";
        }

        if (isset($data['marketing_badge']) && mb_strlen((string)$data['marketing_badge']) > 100) {
            $errors[] = "Field 'marketing_badge' must not exceed 100 characters";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a scope entry.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateScope(array $data): array
    {
        $errors = [];

        if (empty($data['discount_id']) || !is_numeric($data['discount_id'])) {
            $errors[] = "Field 'discount_id' is required and must be numeric";
        }

        if (empty($data['scope_type']) || !in_array($data['scope_type'], self::ALLOWED_SCOPE_TYPES, true)) {
            $errors[] = "Field 'scope_type' is required and must be one of: " . implode(', ', self::ALLOWED_SCOPE_TYPES);
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a condition entry.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateCondition(array $data): array
    {
        $errors = [];

        if (empty($data['discount_id']) || !is_numeric($data['discount_id'])) {
            $errors[] = "Field 'discount_id' is required and must be numeric";
        }

        if (empty($data['condition_type']) || !in_array($data['condition_type'], self::ALLOWED_CONDITION_TYPES, true)) {
            $errors[] = "Field 'condition_type' is required and must be one of: " . implode(', ', self::ALLOWED_CONDITION_TYPES);
        }

        if (!isset($data['operator']) || !in_array($data['operator'], self::ALLOWED_OPERATORS, true)) {
            $errors[] = "Field 'operator' is required and must be one of: " . implode(', ', self::ALLOWED_OPERATORS);
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate an action entry.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateAction(array $data): array
    {
        $errors = [];

        if (empty($data['discount_id']) || !is_numeric($data['discount_id'])) {
            $errors[] = "Field 'discount_id' is required and must be numeric";
        }

        if (empty($data['action_type']) || !in_array($data['action_type'], self::ALLOWED_ACTION_TYPES, true)) {
            $errors[] = "Field 'action_type' is required and must be one of: " . implode(', ', self::ALLOWED_ACTION_TYPES);
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a redemption entry.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateRedemption(array $data): array
    {
        $errors = [];

        if (empty($data['discount_id']) || !is_numeric($data['discount_id'])) {
            $errors[] = "Field 'discount_id' is required and must be numeric";
        }

        if (isset($data['amount_discounted']) && !is_numeric($data['amount_discounted'])) {
            $errors[] = "Field 'amount_discounted' must be numeric";
        }

        if (isset($data['currency_code']) && mb_strlen((string)$data['currency_code']) > 3) {
            $errors[] = "Field 'currency_code' must not exceed 3 characters";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate an exclusion entry.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateExclusion(array $data): array
    {
        $errors = [];

        if (empty($data['discount_id']) || !is_numeric($data['discount_id'])) {
            $errors[] = "Field 'discount_id' is required and must be numeric";
        }

        if (empty($data['excluded_discount_id']) || !is_numeric($data['excluded_discount_id'])) {
            $errors[] = "Field 'excluded_discount_id' is required and must be numeric";
        }

        if (
            !empty($data['discount_id']) && !empty($data['excluded_discount_id'])
            && (int)$data['discount_id'] === (int)$data['excluded_discount_id']
        ) {
            $errors[] = "A discount cannot exclude itself";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate date fields.
     */
    private static function validateDates(array $data, array &$errors): void
    {
        if (isset($data['starts_at']) && $data['starts_at'] !== '' && strtotime($data['starts_at']) === false) {
            $errors[] = "Field 'starts_at' must be a valid date/time";
        }

        if (isset($data['ends_at']) && $data['ends_at'] !== '' && strtotime($data['ends_at']) === false) {
            $errors[] = "Field 'ends_at' must be a valid date/time";
        }

        if (
            !empty($data['starts_at']) && !empty($data['ends_at'])
            && strtotime($data['starts_at']) !== false && strtotime($data['ends_at']) !== false
            && strtotime($data['ends_at']) <= strtotime($data['starts_at'])
        ) {
            $errors[] = "Field 'ends_at' must be after 'starts_at'";
        }
    }
}
