<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntityTypesValidator
{
    public function validate(array $data, bool $isUpdate=false): void
    {
        if (!$isUpdate) {
            foreach (['code','name'] as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("Field '{$field}' is required.");
                }
            }
        }

        if (isset($data['code']) && strlen($data['code']) > 50) {
            throw new InvalidArgumentException("Code max length is 50.");
        }

        if (isset($data['name']) && strlen($data['name']) > 150) {
            throw new InvalidArgumentException("Name max length is 150.");
        }
    }
}
