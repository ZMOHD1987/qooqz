<?php
declare(strict_types=1);

final class ProductBundlesValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (empty($data['entity_id'])) {
            throw new InvalidArgumentException('entity_id is required.');
        }
        if (!is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException('entity_id must be numeric.');
        }

        if (empty($data['bundle_name']) && empty($data['bundle_name_ar'])) {
            throw new InvalidArgumentException('Bundle name (English or Arabic) is required.');
        }

        if (isset($data['bundle_name']) && strlen($data['bundle_name']) > 255) {
            throw new InvalidArgumentException('bundle_name too long (max 255).');
        }
        if (isset($data['bundle_name_ar']) && strlen($data['bundle_name_ar']) > 255) {
            throw new InvalidArgumentException('bundle_name_ar too long (max 255).');
        }

        $numeric = ['original_total_price', 'bundle_price', 'discount_amount', 'stock_quantity', 'sold_count'];
        foreach ($numeric as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("$field must be numeric.");
            }
        }

        if (isset($data['discount_percentage']) && $data['discount_percentage'] !== '') {
            if (!is_numeric($data['discount_percentage']) || $data['discount_percentage'] < 0 || $data['discount_percentage'] > 100) {
                throw new InvalidArgumentException('discount_percentage must be between 0 and 100.');
            }
        }

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0,1], true)) {
            throw new InvalidArgumentException('is_active must be 0 or 1.');
        }

        if (isset($data['start_date']) && !strtotime($data['start_date'])) {
            throw new InvalidArgumentException('Invalid start_date.');
        }
        if (isset($data['end_date']) && !strtotime($data['end_date'])) {
            throw new InvalidArgumentException('Invalid end_date.');
        }
    }
}