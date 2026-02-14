<?php
declare(strict_types=1);

/**
 * Static validation for commission payment operations.
 */
final class CommissionPaymentsValidator
{
    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['tenant_id'])) {
            $errors[] = 'tenant_id is required';
        }

        if (empty($data['entity_id'])) {
            $errors[] = 'entity_id is required';
        }

        if (empty($data['commission_invoice_id'])) {
            $errors[] = 'commission_invoice_id is required';
        }

        if (!isset($data['amount_paid']) || !is_numeric($data['amount_paid']) || (float)$data['amount_paid'] < 0) {
            $errors[] = 'amount_paid is required and must be >= 0';
        }

        if (empty($data['paid_at'])) {
            $errors[] = 'paid_at is required';
        }

        if (isset($data['payment_method']) && strlen($data['payment_method']) > 50) {
            $errors[] = 'payment_method must not exceed 50 characters';
        }

        if (isset($data['cancellation_reason']) && strlen($data['cancellation_reason']) > 255) {
            $errors[] = 'cancellation_reason must not exceed 255 characters';
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['amount_paid']) && (!is_numeric($data['amount_paid']) || (float)$data['amount_paid'] < 0)) {
            $errors[] = 'amount_paid must be >= 0';
        }

        if (isset($data['payment_method']) && strlen($data['payment_method']) > 50) {
            $errors[] = 'payment_method must not exceed 50 characters';
        }

        if (isset($data['cancellation_reason']) && strlen($data['cancellation_reason']) > 255) {
            $errors[] = 'cancellation_reason must not exceed 255 characters';
        }

        return $errors;
    }
}
