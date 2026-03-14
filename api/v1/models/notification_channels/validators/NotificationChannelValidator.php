<?php
declare(strict_types=1);

/**
 * NotificationChannelValidator
 *
 * Location: api/v1/models/notifications/notification_channels/validators/NotificationChannelValidator.php
 */
final class NotificationChannelValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            foreach (['code', 'name'] as $field) {
                if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                    throw new InvalidArgumentException("{$field} is required.");
                }
            }
        }

        if (isset($data['code'])) {
            if (mb_strlen((string) $data['code']) > 50) {
                throw new InvalidArgumentException('code must not exceed 50 characters.');
            }
            if (!preg_match('/^[a-z0-9_\-]+$/i', (string) $data['code'])) {
                throw new InvalidArgumentException('code may only contain letters, numbers, underscores, and hyphens.');
            }
        }

        if (isset($data['name']) && mb_strlen((string) $data['name']) > 100) {
            throw new InvalidArgumentException('name must not exceed 100 characters.');
        }

        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('is_active must be 0 or 1.');
        }
    }
}
