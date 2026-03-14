<?php
declare(strict_types=1);

/**
 * Static validation for escrow payment operations.
 */
final class EscrowPaymentsValidator
{
    private const ALLOWED_STATUSES = ['pending', 'success', 'failed', 'refunded'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['escrow_id']) || !is_numeric($data['escrow_id']) || (int)$data['escrow_id'] <= 0) {
            $errors[] = 'escrow_id is required and must be numeric';
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
}
