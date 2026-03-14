<?php
declare(strict_types=1);

/**
 * Static validation for commission invoice operations.
 */
final class CommissionInvoicesValidator
{
    private const ALLOWED_INVOICE_TYPES = ['monthly', 'quarterly', 'custom'];
    private const ALLOWED_STATUSES = ['draft', 'issued', 'partially_paid', 'paid', 'cancelled'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['tenant_id'])) {
            $errors[] = 'tenant_id is required';
        }

        if (empty($data['entity_id'])) {
            $errors[] = 'entity_id is required';
        }

        if (empty($data['invoice_type'])) {
            $errors[] = 'invoice_type is required';
        } elseif (!in_array($data['invoice_type'], self::ALLOWED_INVOICE_TYPES, true)) {
            $errors[] = 'invoice_type must be one of: ' . implode(', ', self::ALLOWED_INVOICE_TYPES);
        }

        if (empty($data['period_start'])) {
            $errors[] = 'period_start is required';
        }

        if (empty($data['period_end'])) {
            $errors[] = 'period_end is required';
        }

        if (!isset($data['grand_total']) || !is_numeric($data['grand_total']) || (float)$data['grand_total'] < 0) {
            $errors[] = 'grand_total is required and must be >= 0';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        if (isset($data['total_orders']) && (!is_numeric($data['total_orders']) || (int)$data['total_orders'] < 0)) {
            $errors[] = 'total_orders must be >= 0';
        }

        if (isset($data['total_orders_amount']) && (!is_numeric($data['total_orders_amount']) || (float)$data['total_orders_amount'] < 0)) {
            $errors[] = 'total_orders_amount must be >= 0';
        }

        if (isset($data['total_commission']) && (!is_numeric($data['total_commission']) || (float)$data['total_commission'] < 0)) {
            $errors[] = 'total_commission must be >= 0';
        }

        if (isset($data['total_vat']) && (!is_numeric($data['total_vat']) || (float)$data['total_vat'] < 0)) {
            $errors[] = 'total_vat must be >= 0';
        }

        if (isset($data['amount_paid']) && (!is_numeric($data['amount_paid']) || (float)$data['amount_paid'] < 0)) {
            $errors[] = 'amount_paid must be >= 0';
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['invoice_type']) && !in_array($data['invoice_type'], self::ALLOWED_INVOICE_TYPES, true)) {
            $errors[] = 'invoice_type must be one of: ' . implode(', ', self::ALLOWED_INVOICE_TYPES);
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        if (isset($data['grand_total']) && !is_numeric($data['grand_total'])) {
            $errors[] = 'grand_total must be numeric';
        }

        if (isset($data['total_orders']) && (!is_numeric($data['total_orders']) || (int)$data['total_orders'] < 0)) {
            $errors[] = 'total_orders must be >= 0';
        }

        if (isset($data['total_orders_amount']) && (!is_numeric($data['total_orders_amount']) || (float)$data['total_orders_amount'] < 0)) {
            $errors[] = 'total_orders_amount must be >= 0';
        }

        if (isset($data['total_commission']) && (!is_numeric($data['total_commission']) || (float)$data['total_commission'] < 0)) {
            $errors[] = 'total_commission must be >= 0';
        }

        if (isset($data['total_vat']) && (!is_numeric($data['total_vat']) || (float)$data['total_vat'] < 0)) {
            $errors[] = 'total_vat must be >= 0';
        }

        if (isset($data['amount_paid']) && (!is_numeric($data['amount_paid']) || (float)$data['amount_paid'] < 0)) {
            $errors[] = 'amount_paid must be >= 0';
        }

        return $errors;
    }
}
