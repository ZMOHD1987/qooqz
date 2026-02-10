<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntitiesValidator
{
    public function validate(array $data, bool $update=false): void
    {
        if (!$update) {
            foreach (['user_id','store_name','slug','vendor_type','store_type','phone','email'] as $f) {
                if (empty($data[$f])) {
                    throw new InvalidArgumentException("Field '{$f}' is required");
                }
            }
        }
    }
}
