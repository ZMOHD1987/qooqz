<?php
declare(strict_types=1);

final class PaymentMethodsValidator
{
    public static function validateCreate(array $data): array
    {
        $errors = [];
        if (empty($data['method_key'])) {
            $errors[] = 'method_key is required';
        }
        if (empty($data['method_name'])) {
            $errors[] = 'method_name is required';
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public static function validateUpdate(array $data): array
    {
        $errors = [];
        if (empty($data['method_key'])) {
            $errors[] = 'method_key is required';
        }
        if (empty($data['method_name'])) {
            $errors[] = 'method_name is required';
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
