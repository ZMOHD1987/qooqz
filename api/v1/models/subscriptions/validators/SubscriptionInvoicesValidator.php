<?php
declare(strict_types=1);

/**
 * Static validation for subscription invoice operations.
 */
final class SubscriptionInvoicesValidator
{
    private const ALLOWED_STATUSES = ['pending', 'paid', 'overdue', 'cancelled', 'refunded'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['subscription_id']) || (int)$data['subscription_id'] <= 0) {
            $errors[] = 'subscription_id is required';
        }

        if (empty($data['tenant_id']) || (int)$data['tenant_id'] <= 0) {
            $errors[] = 'tenant_id is required';
        }

        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] < 0) {
            $errors[] = 'amount is required and must be >= 0';
        }

        if (!isset($data['total_amount']) || !is_numeric($data['total_amount']) || (float)$data['total_amount'] < 0) {
            $errors[] = 'total_amount is required and must be >= 0';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['amount']) && (!is_numeric($data['amount']) || (float)$data['amount'] < 0)) {
            $errors[] = 'amount must be >= 0';
        }

        if (isset($data['total_amount']) && (!is_numeric($data['total_amount']) || (float)$data['total_amount'] < 0)) {
            $errors[] = 'total_amount must be >= 0';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        return $errors;
    }
}
