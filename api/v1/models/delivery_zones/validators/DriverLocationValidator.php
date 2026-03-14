<?php
declare(strict_types=1);

final class DriverLocationValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['provider_id'])) {
                throw new InvalidArgumentException('provider_id is required.');
            }
        }

        if (isset($data['latitude'])) {
            if (!is_numeric($data['latitude']) || $data['latitude'] < -90 || $data['latitude'] > 90) {
                throw new InvalidArgumentException('Invalid latitude value.');
            }
        }

        if (isset($data['longitude'])) {
            if (!is_numeric($data['longitude']) || $data['longitude'] < -180 || $data['longitude'] > 180) {
                throw new InvalidArgumentException('Invalid longitude value.');
            }
        }
    }
}