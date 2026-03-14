<?php
declare(strict_types=1);

final class ProductStockAlertsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['product_id','user_id','email'];
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email is not valid.");
        }

        foreach (['is_notified','user_id','product_id','variant_id'] as $intField) {
            if (isset($data[$intField]) && !is_int($data[$intField])) {
                throw new InvalidArgumentException("$intField must be integer.");
            }
        }
    }
}