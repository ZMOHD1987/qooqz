<?php
declare(strict_types=1);

/**
 * Static validation for escrow transaction operations.
 */
final class EscrowTransactionsValidator
{
    private const ALLOWED_STATUSES = ['pending', 'funded', 'in_transit', 'delivered', 'released', 'disputed', 'refunded', 'cancelled'];

    public static function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['order_id']) || (int)$data['order_id'] <= 0) {
            $errors[] = 'order_id is required';
        }

        if (empty($data['buyer_id']) || (int)$data['buyer_id'] <= 0) {
            $errors[] = 'buyer_id is required';
        }

        if (empty($data['seller_id']) || (int)$data['seller_id'] <= 0) {
            $errors[] = 'seller_id is required';
        }

        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            $errors[] = 'amount is required and must be > 0';
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
            $errors[] = 'amount must be > 0';
        }

        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: ' . implode(', ', self::ALLOWED_STATUSES);
        }

        return $errors;
    }
}
