<?php
declare(strict_types=1);

/**
 * Static validation for commission credit note operations.
 */
final class CommissionCreditNotesValidator
{
    private const ALLOWED_STATUSES = ['draft', 'issued', 'cancelled'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['tenant_id'])) {
            $errors[] = 'tenant_id is required';
        }

        if (empty($data['invoice_id'])) {
            $errors[] = 'invoice_id is required';
        }

        if (empty($data['related_transaction_id'])) {
            $errors[] = 'related_transaction_id is required';
        }

        if (!isset($data['credit_amount']) || !is_numeric($data['credit_amount']) || (float)$data['credit_amount'] < 0) {
            $errors[] = 'credit_amount is required and must be >= 0';
        }

        if (!isset($data['credit_commission']) || !is_numeric($data['credit_commission']) || (float)$data['credit_commission'] < 0) {
            $errors[] = 'credit_commission is required and must be >= 0';
        }

        if (!isset($data['credit_vat']) || !is_numeric($data['credit_vat']) || (float)$data['credit_vat'] < 0) {
            $errors[] = 'credit_vat is required and must be >= 0';
        }

        if (!isset($data['net_credit']) || !is_numeric($data['net_credit']) || (float)$data['net_credit'] < 0) {
            $errors[] = 'net_credit is required and must be >= 0';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        if (isset($data['reason']) && strlen($data['reason']) > 255) {
            $errors[] = 'reason must not exceed 255 characters';
        }

        return $errors;
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        if (isset($data['credit_amount']) && (!is_numeric($data['credit_amount']) || (float)$data['credit_amount'] < 0)) {
            $errors[] = 'credit_amount must be >= 0';
        }

        if (isset($data['credit_commission']) && (!is_numeric($data['credit_commission']) || (float)$data['credit_commission'] < 0)) {
            $errors[] = 'credit_commission must be >= 0';
        }

        if (isset($data['credit_vat']) && (!is_numeric($data['credit_vat']) || (float)$data['credit_vat'] < 0)) {
            $errors[] = 'credit_vat must be >= 0';
        }

        if (isset($data['net_credit']) && (!is_numeric($data['net_credit']) || (float)$data['net_credit'] < 0)) {
            $errors[] = 'net_credit must be >= 0';
        }

        if (isset($data['reason']) && strlen($data['reason']) > 255) {
            $errors[] = 'reason must not exceed 255 characters';
        }

        return $errors;
    }
}
