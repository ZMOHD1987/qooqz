<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntityPaymentMethodsValidator
{
    public function validate(array $data, bool $isUpdate=false): void
    {
        if (!$isUpdate && empty($data['payment_method_id'])) {
            throw new InvalidArgumentException('payment_method_id is required');
        }

        if (!empty($data['payment_method_id']) && !is_numeric($data['payment_method_id'])) {
            throw new InvalidArgumentException('Invalid payment_method_id');
        }

        if (isset($data['account_email']) && strlen($data['account_email']) > 191) {
            throw new InvalidArgumentException('Invalid account_email');
        }

        if (isset($data['account_id']) && strlen($data['account_id']) > 255) {
            throw new InvalidArgumentException('Invalid account_id');
        }
    }
}