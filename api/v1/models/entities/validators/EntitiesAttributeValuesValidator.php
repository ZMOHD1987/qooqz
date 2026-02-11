<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntitiesAttributeValuesValidator
{
    /**
     * التحقق من صحة البيانات لإنشاء قيمة جديدة
     */
    public static function validateCreate(array $data): void
    {
        if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("Field 'entity_id' is required and must be numeric");
        }

        if (empty($data['attribute_id']) || !is_numeric($data['attribute_id'])) {
            throw new InvalidArgumentException("Field 'attribute_id' is required and must be numeric");
        }

        if (!isset($data['value']) || $data['value'] === '') {
            throw new InvalidArgumentException("Field 'value' is required");
        }

        self::validateCommonFields($data);
    }

    /**
     * التحقق من صحة البيانات لتحديث قيمة موجودة
     */
    public static function validateUpdate(array $data): void
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for update");
        }

        if (isset($data['id']) && !is_numeric($data['id'])) {
            throw new InvalidArgumentException("Field 'id' must be numeric");
        }

        self::validateCommonFields($data);
    }

    /**
     * التحقق من صحة البيانات للحفظ الجماعي
     */
    public static function validateBulkSave(int $entityId, array $values): void
    {
        if ($entityId <= 0) {
            throw new InvalidArgumentException("Entity ID must be positive");
        }

        if (empty($values)) {
            throw new InvalidArgumentException("No values provided for bulk save");
        }

        foreach ($values as $index => $valueData) {
            if (!is_array($valueData)) {
                throw new InvalidArgumentException("Value at index $index must be an array");
            }

            $tempData = array_merge(['entity_id' => $entityId], $valueData);
            self::validateCreate($tempData);
        }
    }

    /**
     * التحقق من الحقول المشتركة بين الإنشاء والتحديث
     */
    private static function validateCommonFields(array $data): void
    {
        // التحقق من entity_id
        if (isset($data['entity_id']) && !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("entity_id must be numeric");
        }

        // التحقق من attribute_id
        if (isset($data['attribute_id']) && !is_numeric($data['attribute_id'])) {
            throw new InvalidArgumentException("attribute_id must be numeric");
        }

        // التحقق من القيمة
        if (isset($data['value'])) {
            if (!is_string($data['value']) && !is_numeric($data['value']) && !is_bool($data['value'])) {
                throw new InvalidArgumentException("value must be a string, number, or boolean");
            }
        }
    }

    /**
     * التحقق من صحة القيمة حسب نوع الخاصية
     */
    public static function validateValueByType(string $value, string $attributeType): void
    {
        switch ($attributeType) {
            case 'text':
                // النصوص مسموحة بأي قيمة
                break;
            
            case 'number':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Value must be a number for attribute type 'number'");
                }
                break;
            
            case 'boolean':
                if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                    throw new InvalidArgumentException("Value must be boolean for attribute type 'boolean'");
                }
                break;
            
            case 'select':
                // القيم المحددة يمكن أن تكون أي نص
                if (empty(trim($value))) {
                    throw new InvalidArgumentException("Value cannot be empty for attribute type 'select'");
                }
                break;
            
            default:
                throw new InvalidArgumentException("Unknown attribute type: $attributeType");
        }
    }

    /**
     * تحويل القيمة حسب نوع الخاصية
     */
    public static function convertValueByType($value, string $attributeType)
    {
        switch ($attributeType) {
            case 'number':
                return is_numeric($value) ? (float)$value : $value;
            
            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                if (is_numeric($value)) {
                    return (bool)$value;
                }
                if (is_string($value)) {
                    return in_array(strtolower($value), ['true', '1'], true);
                }
                return (bool)$value;
            
            default:
                return is_string($value) ? trim($value) : $value;
        }
    }
}