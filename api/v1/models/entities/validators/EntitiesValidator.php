<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntitiesValidator
{
    public function validate(array $data, bool $update = false): void
    {
        // الحقول الأساسية المطلوبة عند الإنشاء
        $requiredFields = [
            'user_id',
            'store_name',
            'slug',
            'vendor_type',
            'store_type',
            'phone',
            'email'
        ];

        if (!$update) {
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("Field '{$field}' is required");
                }
            }
        }

        // تحقق إضافي من الصلاحية للقيم
        if (isset($data['vendor_type']) && !in_array($data['vendor_type'], ['product_seller','service_provider','both'], true)) {
            throw new InvalidArgumentException("Field 'vendor_type' has invalid value");
        }

        if (isset($data['store_type']) && !in_array($data['store_type'], ['individual','company','brand'], true)) {
            throw new InvalidArgumentException("Field 'store_type' has invalid value");
        }

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Field 'email' is not a valid email");
        }

        if (isset($data['phone']) && !preg_match('/^[0-9+\-\s]{5,20}$/', $data['phone'])) {
            throw new InvalidArgumentException("Field 'phone' has invalid format");
        }

        if (isset($data['mobile']) && !empty($data['mobile']) && !preg_match('/^[0-9+\-\s]{5,20}$/', $data['mobile'])) {
            throw new InvalidArgumentException("Field 'mobile' has invalid format");
        }
    }
}
