<?php
declare(strict_types=1);

/**
 * NotificationCounterValidator
 *
 * Location: api/v1/models/notifications/notification_counters/validators/NotificationCounterValidator.php
 */
final class NotificationCounterValidator
{
    private const ALLOWED_RECIPIENT_TYPES = ['user', 'entity', 'tenant'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            foreach (['recipient_type', 'recipient_id'] as $field) {
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

        if (isset($data['recipient_id']) && !is_numeric($data['recipient_id'])) {
            throw new InvalidArgumentException('recipient_id must be numeric.');
        }

        if (isset($data['unread_count']) && (!is_numeric($data['unread_count']) || (int) $data['unread_count'] < 0)) {
            throw new InvalidArgumentException('unread_count must be a non-negative integer.');
        }
    }

    public function validateRecipientPayload(array $data): void
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
