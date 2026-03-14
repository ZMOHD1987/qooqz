<?php
declare(strict_types=1);

/**
 * Static validation for commission transaction operations.
 */
final class CommissionTransactionsValidator
{
    private const ALLOWED_TRANSACTION_TYPES = ['sale', 'refund'];
    private const ALLOWED_STATUSES = ['pending', 'invoiced', 'paid', 'cancelled'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['tenant_id'])) {
            $errors[] = 'tenant_id is required';
        }

        if (empty($data['entity_id'])) {
            $errors[] = 'entity_id is required';
        }

        if (empty($data['order_id'])) {
            $errors[] = 'order_id is required';
        }

        if (empty($data['order_date'])) {
            $errors[] = 'order_date is required';
        }

        if (!isset($data['order_amount']) || !is_numeric($data['order_amount']) || (float)$data['order_amount'] < 0) {
            $errors[] = 'order_amount is required and must be >= 0';
        }

        if (!isset($data['commission_amount']) || !is_numeric($data['commission_amount']) || (float)$data['commission_amount'] < 0) {
            $errors[] = 'commission_amount is required and must be >= 0';
        }

        if (!isset($data['net_commission']) || !is_numeric($data['net_commission']) || (float)$data['net_commission'] < 0) {
            $errors[] = 'net_commission is required and must be >= 0';
        }

        if (isset($data['transaction_type']) && !in_array($data['transaction_type'], self::ALLOWED_TRANSACTION_TYPES, true)) {
            $errors[] = 'transaction_type must be one of: ' . implode(', ', self::ALLOWED_TRANSACTION_TYPES);
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        if (isset($data['vat_amount']) && (!is_numeric($data['vat_amount']) || (float)$data['vat_amount'] < 0)) {
            $errors[] = 'vat_amount must be >= 0';
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['transaction_type']) && !in_array($data['transaction_type'], self::ALLOWED_TRANSACTION_TYPES, true)) {
            $errors[] = 'transaction_type must be one of: ' . implode(', ', self::ALLOWED_TRANSACTION_TYPES);
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        if (isset($data['order_amount']) && (!is_numeric($data['order_amount']) || (float)$data['order_amount'] < 0)) {
            $errors[] = 'order_amount must be >= 0';
        }

        if (isset($data['commission_amount']) && (!is_numeric($data['commission_amount']) || (float)$data['commission_amount'] < 0)) {
            $errors[] = 'commission_amount must be >= 0';
        }

        if (isset($data['net_commission']) && (!is_numeric($data['net_commission']) || (float)$data['net_commission'] < 0)) {
            $errors[] = 'net_commission must be >= 0';
        }

        if (isset($data['vat_amount']) && (!is_numeric($data['vat_amount']) || (float)$data['vat_amount'] < 0)) {
            $errors[] = 'vat_amount must be >= 0';
        }

        return $errors;
    }
}
