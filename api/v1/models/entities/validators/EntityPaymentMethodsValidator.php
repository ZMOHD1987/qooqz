<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntityPaymentMethodsValidator
{
    public function validate(array $data, bool $isUpdate=false): void
    {
        if (!$isUpdate && empty($data['gateway_name'])) {
            throw new InvalidArgumentException('gateway_name is required');
        }

        if (isset($data['gateway_name']) && strlen($data['gateway_name']) > 100) {
            throw new InvalidArgumentException('Invalid gateway_name');
        }

        if (isset($data['account_email']) && strlen($data['account_email']) > 191) {
            throw new InvalidArgumentException('Invalid account_email');
        }

        if (isset($data['account_id']) && strlen($data['account_id']) > 255) {
            throw new InvalidArgumentException('Invalid account_id');
        }
    }
}
