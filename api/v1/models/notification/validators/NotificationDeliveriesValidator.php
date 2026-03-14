<?php
declare(strict_types=1);

final class NotificationDeliveriesValidator
{
    private const VALID_STATUSES = ['pending', 'sent', 'failed'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['notification_id']) || !is_numeric($data['notification_id'])) {
                throw new InvalidArgumentException('Field "notification_id" is required and must be numeric.');
            }
            if (empty($data['channel_id']) || !is_numeric($data['channel_id'])) {
                throw new InvalidArgumentException('Field "channel_id" is required and must be numeric.');
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
        }

        if (isset($data['delivery_status']) && $data['delivery_status'] !== '' &&
            !in_array($data['delivery_status'], self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                'Field "delivery_status" must be one of: ' . implode(', ', self::VALID_STATUSES) . '.'
            );
        }

        if (isset($data['attempts']) && $data['attempts'] !== '' && !is_numeric($data['attempts'])) {
            throw new InvalidArgumentException('Field "attempts" must be numeric.');
        }

        if (isset($data['sent_at']) && $data['sent_at'] !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $data['sent_at'])) {
                throw new InvalidArgumentException('Field "sent_at" must be a valid datetime.');
            }
        }
    }
}
