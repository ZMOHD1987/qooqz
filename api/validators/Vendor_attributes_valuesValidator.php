<?php
// api/validators/Vendor_attributes_valuesValidator.php

if (!class_exists('Vendor_attributes_valuesValidator')) {

class Vendor_attributes_valuesValidator
{
    public static function validate(array $data)
    {
        $errors = [];

        // vendor_id
        if (empty($data['vendor_id']) || !is_numeric($data['vendor_id']) || (int)$data['vendor_id'] <= 0) {
            $errors['vendor_id'] = 'Invalid vendor';
        }

        // attribute_id
        if (empty($data['attribute_id']) || !is_numeric($data['attribute_id']) || (int)$data['attribute_id'] <= 0) {
            $errors['attribute_id'] = 'Invalid attribute';
        }

        // value (مهم جداً)
        if (!isset($data['value']) || trim((string)$data['value']) === '') {
            $errors['value'] = 'Value is required';
        }

        // id (اختياري للتعديل فقط)
        if (isset($data['id']) && $data['id'] !== '') {
            if (!is_numeric($data['id']) || (int)$data['id'] <= 0) {
                $errors['id'] = 'Invalid ID';
            }
        }

        return empty($errors) ? true : $errors;
    }
}

}
