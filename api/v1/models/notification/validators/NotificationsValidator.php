<?php
declare(strict_types=1);

final class NotificationsValidator
{
    private const VALID_PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['tenant_id']) || !is_numeric($data['tenant_id'])) {
                throw new InvalidArgumentException('Field "tenant_id" is required and must be numeric.');
            }
            if (empty($data['title'])) {
                throw new InvalidArgumentException('Field "title" is required.');
            }
            if (empty($data['message'])) {
                throw new InvalidArgumentException('Field "message" is required.');
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
        }

        if (isset($data['title']) && strlen($data['title']) > 500) {
            throw new InvalidArgumentException('Field "title" must not exceed 500 characters.');
        }

        if (isset($data['priority']) && $data['priority'] !== '' &&
            !in_array($data['priority'], self::VALID_PRIORITIES, true)) {
            throw new InvalidArgumentException(
                'Field "priority" must be one of: ' . implode(', ', self::VALID_PRIORITIES) . '.'
            );
        }

        if (isset($data['expires_at']) && $data['expires_at'] !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $data['expires_at'])) {
                throw new InvalidArgumentException('Field "expires_at" must be a valid datetime (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).');
            }
        }

        if (isset($data['data']) && $data['data'] !== '' && json_decode($data['data']) === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Field "data" must be valid JSON if provided.');
        }
    }
}