<?php
declare(strict_types=1);

/**
 * Static validation for subscription operations.
 */
final class SubscriptionsValidator
{
    private const ALLOWED_STATUSES = ['trial', 'active', 'paused', 'cancelled', 'expired', 'suspended'];
    private const ALLOWED_BILLING_PERIODS = ['monthly', 'quarterly', 'semi_annual', 'annual', 'lifetime'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['tenant_id']) || (int)$data['tenant_id'] <= 0) {
            $errors[] = 'tenant_id is required';
        }

        if (empty($data['plan_id']) || (int)$data['plan_id'] <= 0) {
            $errors[] = 'plan_id is required';
        }

        if (empty($data['billing_period']) || !in_array($data['billing_period'], self::ALLOWED_BILLING_PERIODS, true)) {
            $errors[] = 'billing_period is required and must be one of: ' . implode(', ', self::ALLOWED_BILLING_PERIODS);
        }

        if (!isset($data['price']) || !is_numeric($data['price']) || (float)$data['price'] < 0) {
            $errors[] = 'price is required and must be >= 0';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        if (isset($data['billing_period']) && !in_array($data['billing_period'], self::ALLOWED_BILLING_PERIODS, true)) {
            $errors[] = 'billing_period must be one of: ' . implode(', ', self::ALLOWED_BILLING_PERIODS);
        }

        if (isset($data['price']) && (!is_numeric($data['price']) || (float)$data['price'] < 0)) {
            $errors[] = 'price must be >= 0';
        }

        return $errors;
    }
}
