<?php
declare(strict_types=1);

final class UnitsValidator
{
    public static function validate(array $data): array
    {
        $errors=[];

        if(empty($data['code'])){
            $errors['code']='Unit code required';
        }
        elseif(strlen($data['code'])>20){
            $errors['code']='Code too long';
        }

        if(!empty($data['translations'])){
            foreach($data['translations'] as $lang=>$name){
                if(empty($name) || strlen($name)>50){
                    $errors['translations'][$lang]='Invalid name';
                }
            }
        }

        return $errors;
    }
}
