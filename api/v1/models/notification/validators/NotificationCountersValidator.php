<?php
declare(strict_types=1);

final class NotificationCountersValidator
{
    private const VALID_RECIPIENT_TYPES = ['user', 'entity', 'tenant'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['tenant_id']) || !is_numeric($data['tenant_id'])) {
                throw new InvalidArgumentException('Field "tenant_id" is required and must be numeric.');
            }
            if (empty($data['recipient_type'])) {
                throw new InvalidArgumentException('Field "recipient_type" is required.');
            }
            if (empty($data['recipient_id']) || !is_numeric($data['recipient_id'])) {
                throw new InvalidArgumentException('Field "recipient_id" is required and must be numeric.');
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
        }

        if (isset($data['recipient_type']) && $data['recipient_type'] !== '' &&
            !in_array($data['recipient_type'], self::VALID_RECIPIENT_TYPES, true)) {
            throw new InvalidArgumentException(
                'Field "recipient_type" must be one of: ' . implode(', ', self::VALID_RECIPIENT_TYPES) . '.'
            );
        }

        if (isset($data['unread_count']) && $data['unread_count'] !== '' && !is_numeric($data['unread_count'])) {
            throw new InvalidArgumentException('Field "unread_count" must be numeric.');
        }
    }
}
