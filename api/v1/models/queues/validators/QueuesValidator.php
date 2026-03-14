<?php
declare(strict_types=1);

final class QueuesValidator
{
    private const ALLOWED_STATUSES = [0, 1, 2, 3];
    private const ALLOWED_PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public static function validatePush(array $data): array
    {
        $errors = [];

        if (empty($data['queue']) || !is_string($data['queue'])) {
            $errors[] = 'Queue name is required and must be a string.';
        } elseif (strlen($data['queue']) > 100) {
            $errors[] = 'Queue name must not exceed 100 characters.';
        }

        if (!isset($data['payload']) || !is_array($data['payload'])) {
            $errors[] = 'Payload is required and must be an array.';
        }

        if (isset($data['priority']) && !in_array($data['priority'], self::ALLOWED_PRIORITIES, true)) {
            $errors[] = 'Invalid priority level.';
        }

        if (isset($data['status']) && !in_array((int)$data['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'Invalid status code.';
        }

        if (isset($data['job_type']) && (!is_string($data['job_type']) || strlen($data['job_type']) > 100)) {
            $errors[] = 'Job type must be a string up to 100 characters.';
        }

        if (isset($data['available_at']) && !empty($data['available_at'])) {
            if (!strtotime($data['available_at'])) {
                $errors[] = 'Invalid available_at date format.';
            }
        }

        return $errors;
    }

    public static function validateFilters(array $filters): array
    {
        $errors = [];

        if (isset($filters['status']) && $filters['status'] !== '' && !in_array((int)$filters['status'], self::ALLOWED_STATUSES, true)) {
            $errors[] = 'Invalid status filter.';
        }

        if (isset($filters['priority']) && $filters['priority'] !== '' && !in_array($filters['priority'], self::ALLOWED_PRIORITIES, true)) {
            $errors[] = 'Invalid priority filter.';
        }

        if (isset($filters['limit']) && (int)$filters['limit'] < 1) {
            $errors[] = 'Limit must be at least 1.';
        }

        if (isset($filters['offset']) && (int)$filters['offset'] < 0) {
            $errors[] = 'Offset cannot be negative.';
        }

        return $errors;
    }
}
