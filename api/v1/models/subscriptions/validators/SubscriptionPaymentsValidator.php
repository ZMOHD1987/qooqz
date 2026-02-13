<?php
declare(strict_types=1);

/**
 * Static validation for subscription payment operations.
 */
final class SubscriptionPaymentsValidator
{
    private const ALLOWED_STATUSES = ['pending', 'success', 'failed', 'refunded'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['invoice_id']) || !is_numeric($data['invoice_id']) || (int)$data['invoice_id'] <= 0) {
            $errors[] = 'invoice_id is required and must be numeric';
        }

        if (empty($data['subscription_id']) || !is_numeric($data['subscription_id']) || (int)$data['subscription_id'] <= 0) {
            $errors[] = 'subscription_id is required and must be numeric';
        }

        if (empty($data['tenant_id']) || !is_numeric($data['tenant_id']) || (int)$data['tenant_id'] <= 0) {
            $errors[] = 'tenant_id is required and must be numeric';
        }

        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            $errors[] = 'amount is required and must be greater than 0';
        }

        if (empty($data['payment_gateway']) || !is_string($data['payment_gateway'])) {
            $errors[] = 'payment_gateway is required and must be a string';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['amount']) && (!is_numeric($data['amount']) || (float)$data['amount'] <= 0)) {
            $errors[] = 'amount must be greater than 0';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        return $errors;
    }
}
