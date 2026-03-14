<?php
declare(strict_types=1);

use InvalidArgumentException;

final class ProductVariantAttributesValidator
{
    public function validate(array $data, bool $isUpdate=false): void
    {
        $required = ['variant_id','attribute_id','attribute_value_id'];
        if(!$isUpdate){
            foreach($required as $field){
                if(!isset($data[$field])){
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        }

        foreach(['variant_id','attribute_id','attribute_value_id'] as $field){
            if(isset($data[$field]) && !is_numeric($data[$field])){
                throw new InvalidArgumentException("$field must be numeric.");
            }
        }
    }
}
