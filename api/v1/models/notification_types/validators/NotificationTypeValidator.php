<?php
declare(strict_types=1);

/**
 * NotificationTypeValidator
 *
 * Location: api/v1/models/notifications/notification_types/validators/NotificationTypeValidator.php
 */
final class NotificationTypeValidator
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

        if (isset($data['name']) && mb_strlen((string) $data['name']) > 150) {
            throw new InvalidArgumentException('name must not exceed 150 characters.');
        }

        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('is_active must be 0 or 1.');
        }

        if (
            isset($data['default_template'])
            && !is_string($data['default_template'])
            && !is_null($data['default_template'])
        ) {
            throw new InvalidArgumentException('default_template must be a string or null.');
        }
    }
}
