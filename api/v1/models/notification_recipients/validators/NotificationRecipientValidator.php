<?php
declare(strict_types=1);

/**
 * NotificationRecipientValidator
 *
 * Location: api/v1/models/notifications/notification_recipients/validators/NotificationRecipientValidator.php
 */
final class NotificationRecipientValidator
{
    private const ALLOWED_RECIPIENT_TYPES = ['user', 'entity', 'tenant'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            foreach (['notification_id', 'recipient_type', 'recipient_id'] as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("{$field} is required.");
                }
            }
        }

        if (
            isset($data['recipient_type'])
            && !in_array($data['recipient_type'], self::ALLOWED_RECIPIENT_TYPES, true)
        ) {
            throw new InvalidArgumentException(
                'Invalid recipient_type. Allowed: ' . implode(', ', self::ALLOWED_RECIPIENT_TYPES)
            );
        }

        if (isset($data['notification_id']) && !is_numeric($data['notification_id'])) {
            throw new InvalidArgumentException('notification_id must be numeric.');
        }

        if (isset($data['recipient_id']) && !is_numeric($data['recipient_id'])) {
            throw new InvalidArgumentException('recipient_id must be numeric.');
        }

        if (isset($data['is_read']) && !in_array($data['is_read'], [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('is_read must be 0 or 1.');
        }

        if (isset($data['read_at']) && strtotime((string) $data['read_at']) === false) {
            throw new InvalidArgumentException('read_at must be a valid datetime.');
        }
    }

    public function validateMarkAllRead(array $data): void
    {
        if (empty($data['recipient_type']) || empty($data['recipient_id'])) {
            throw new InvalidArgumentException('recipient_type and recipient_id are required.');
        }

        if (!in_array($data['recipient_type'], self::ALLOWED_RECIPIENT_TYPES, true)) {
            throw new InvalidArgumentException(
                'Invalid recipient_type. Allowed: ' . implode(', ', self::ALLOWED_RECIPIENT_TYPES)
            );
        }
    }
}
