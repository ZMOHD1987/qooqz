<?php
declare(strict_types=1);

final class AddressesValidator
{
    private const OWNER_TYPES = ['user','entity'];

    // ================================
    // CREATE
    // ================================
    public static function validateCreate(array $data): void
    {
        $required = ['owner_type','owner_id','address_line1'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }

        if (!in_array($data['owner_type'], self::OWNER_TYPES, true)) {
            throw new InvalidArgumentException("Invalid owner_type. Must be 'user' or 'entity'");
        }

        if (!is_numeric($data['owner_id'])) {
            throw new InvalidArgumentException("owner_id must be numeric");
        }

        self::validateOptional($data);
    }

    // ================================
    // UPDATE
    // ================================
    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data to update");
        }

        if (isset($data['owner_type']) &&
            !in_array($data['owner_type'], self::OWNER_TYPES, true)
        ) {
            throw new InvalidArgumentException("Invalid owner_type. Must be 'user' or 'entity'");
        }

        if (isset($data['owner_id']) && !is_numeric($data['owner_id'])) {
            throw new InvalidArgumentException("owner_id must be numeric");
        }

        self::validateOptional($data);
    }

    // ================================
    // OPTIONAL FIELDS
    // ================================
    private static function validateOptional(array $data): void
    {
        foreach (['city_id','country_id','is_primary'] as $intField) {
            if (isset($data[$intField]) && $data[$intField] !== '' && !is_numeric($data[$intField])) {
                throw new InvalidArgumentException("$intField must be numeric");
            }
        }

        foreach (['latitude','longitude'] as $floatField) {
            if (isset($data[$floatField]) && $data[$floatField] !== '' && !is_numeric($data[$floatField])) {
                throw new InvalidArgumentException("$floatField must be numeric");
            }
        }

        if (isset($data['postal_code']) && strlen($data['postal_code']) > 20) {
            throw new InvalidArgumentException("postal_code too long (max 20 characters)");
        }

        if (isset($data['address_line1']) && strlen($data['address_line1']) > 255) {
            throw new InvalidArgumentException("address_line1 too long (max 255 characters)");
        }

        if (isset($data['address_line2']) && strlen($data['address_line2']) > 255) {
            throw new InvalidArgumentException("address_line2 too long (max 255 characters)");
        }
    }
}