<?php
declare(strict_types=1);

final class EntitiesWorkingHoursValidator
{
    public static function validateCreate(array $data): void
    {
        $required = ['entity_id','day_of_week'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("$field is required");
            }
        }

        if (!is_numeric($data['entity_id']) || $data['entity_id'] <= 0) {
            throw new InvalidArgumentException("Invalid entity_id");
        }

        if (!is_numeric($data['day_of_week']) || $data['day_of_week'] < 0 || $data['day_of_week'] > 6) {
            throw new InvalidArgumentException("day_of_week must be 0-6");
        }

        if (isset($data['is_open']) && !in_array((int)$data['is_open'], [0,1], true)) {
            throw new InvalidArgumentException("is_open must be 0 or 1");
        }
    }

    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data to update");
        }

        if (isset($data['day_of_week']) && (!is_numeric($data['day_of_week']) || $data['day_of_week'] < 0 || $data['day_of_week'] > 6)) {
            throw new InvalidArgumentException("day_of_week must be 0-6");
        }

        if (isset($data['is_open']) && !in_array((int)$data['is_open'], [0,1], true)) {
            throw new InvalidArgumentException("is_open must be 0 or 1");
        }
    }
}
