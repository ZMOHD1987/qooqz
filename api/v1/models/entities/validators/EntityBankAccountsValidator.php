<?php
declare(strict_types=1);

use InvalidArgumentException;

final class EntityBankAccountsValidator
{
    public function validate(array $data, bool $isUpdate=false): void
    {
        foreach (['bank_name','account_holder_name','account_number'] as $f) {
            if (!$isUpdate && empty($data[$f])) {
                throw new InvalidArgumentException("$f is required");
            }
        }

        if (isset($data['bank_name']) && strlen($data['bank_name']) > 255) {
            throw new InvalidArgumentException('Invalid bank_name');
        }

        if (isset($data['iban']) && strlen($data['iban']) > 50) {
            throw new InvalidArgumentException('Invalid IBAN');
        }

        if (isset($data['swift_code']) && strlen($data['swift_code']) > 50) {
            throw new InvalidArgumentException('Invalid SWIFT');
        }
    }
}
