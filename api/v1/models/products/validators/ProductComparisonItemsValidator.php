<?php
declare(strict_types=1);

final class ProductComparisonItemsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['comparison_id', 'product_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("$field is required.");
                }
            }
        }

        if (isset($data['comparison_id']) && !is_numeric($data['comparison_id'])) {
            throw new InvalidArgumentException('comparison_id must be numeric.');
        }
        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException('product_id must be numeric.');
        }
    }
}