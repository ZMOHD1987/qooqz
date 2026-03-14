<?php
declare(strict_types=1);

final class ProviderZoneValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['provider_id', 'zone_id'];
        
        foreach ($required as $field) {
            // For update, we need keys to exist, but for create we need them to be valid
            if (!$isUpdate || array_key_exists($field, $data)) {
                if (empty($data[$field]) || !is_numeric($data[$field])) {
                    throw new InvalidArgumentException("$field is required and must be numeric.");
                }
            }
        }

        if (isset($data['is_active']) && !in_array($data['is_active'], [0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('is_active must be 0 or 1.');
        }
    }
}