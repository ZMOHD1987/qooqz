<?php
declare(strict_types=1);

final class NotificationChannelsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['code'])) {
                throw new InvalidArgumentException('Field "code" is required.');
            }
            if (empty($data['name'])) {
                throw new InvalidArgumentException('Field "name" is required.');
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
        }

        if (isset($data['code'])) {
            $code = trim($data['code']);
            if (strlen($code) > 50) {
                throw new InvalidArgumentException('Field "code" must not exceed 50 characters.');
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $code)) {
                throw new InvalidArgumentException('Field "code" may only contain letters, numbers, and underscores.');
            }
        }

        if (isset($data['name']) && strlen($data['name']) > 100) {
            throw new InvalidArgumentException('Field "name" must not exceed 100 characters.');
        }

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            throw new InvalidArgumentException('Field "is_active" must be 0 or 1.');
        }
    }
}
