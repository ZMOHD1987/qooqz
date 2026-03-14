<?php
declare(strict_types=1);

/**
 * Static validation for subscription plan operations.
 */
final class SubscriptionPlansValidator
{
    private const ALLOWED_PLAN_TYPES = ['free', 'basic', 'standard', 'premium', 'enterprise', 'custom'];
    private const ALLOWED_BILLING_PERIODS = ['monthly', 'quarterly', 'semi_annual', 'annual', 'lifetime'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['plan_name'])) {
            $errors[] = 'plan_name is required';
        }

        if (empty($data['plan_type']) || !in_array($data['plan_type'], self::ALLOWED_PLAN_TYPES, true)) {
            $errors[] = 'plan_type is required and must be one of: ' . implode(', ', self::ALLOWED_PLAN_TYPES);
        }

        if (empty($data['billing_period']) || !in_array($data['billing_period'], self::ALLOWED_BILLING_PERIODS, true)) {
            $errors[] = 'billing_period is required and must be one of: ' . implode(', ', self::ALLOWED_BILLING_PERIODS);
        }

        if (!isset($data['price']) || !is_numeric($data['price']) || (float)$data['price'] < 0) {
            $errors[] = 'price is required and must be >= 0';
        }

        if (isset($data['setup_fee']) && (!is_numeric($data['setup_fee']) || (float)$data['setup_fee'] < 0)) {
            $errors[] = 'setup_fee must be >= 0';
        }

        if (isset($data['commission_rate']) && (!is_numeric($data['commission_rate']) || (float)$data['commission_rate'] < 0 || (float)$data['commission_rate'] > 100)) {
            $errors[] = 'commission_rate must be between 0 and 100';
        }

        if (isset($data['trial_period_days']) && ((int)$data['trial_period_days'] < 0)) {
            $errors[] = 'trial_period_days must be >= 0';
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['plan_type']) && !in_array($data['plan_type'], self::ALLOWED_PLAN_TYPES, true)) {
            $errors[] = 'plan_type must be one of: ' . implode(', ', self::ALLOWED_PLAN_TYPES);
        }

        if (isset($data['billing_period']) && !in_array($data['billing_period'], self::ALLOWED_BILLING_PERIODS, true)) {
            $errors[] = 'billing_period must be one of: ' . implode(', ', self::ALLOWED_BILLING_PERIODS);
        }

        if (isset($data['price']) && (!is_numeric($data['price']) || (float)$data['price'] < 0)) {
            $errors[] = 'price must be >= 0';
        }

        if (isset($data['setup_fee']) && (!is_numeric($data['setup_fee']) || (float)$data['setup_fee'] < 0)) {
            $errors[] = 'setup_fee must be >= 0';
        }

        if (isset($data['commission_rate']) && (!is_numeric($data['commission_rate']) || (float)$data['commission_rate'] < 0 || (float)$data['commission_rate'] > 100)) {
            $errors[] = 'commission_rate must be between 0 and 100';
        }

        return $errors;
    }
}
