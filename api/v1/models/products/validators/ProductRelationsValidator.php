<?php
declare(strict_types=1);

final class ProductRelationsValidator
{
    private const ALLOWED_TYPES = ['related', 'upsell', 'cross_sell', 'alternative', 'accessory'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['product_id', 'related_product_id', 'relation_type'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("$field is required.");
                }
            }
        }

        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException('product_id must be numeric.');
        }
        if (isset($data['related_product_id']) && !is_numeric($data['related_product_id'])) {
            throw new InvalidArgumentException('related_product_id must be numeric.');
        }

        if (isset($data['product_id']) && isset($data['related_product_id']) && $data['product_id'] == $data['related_product_id']) {
            throw new InvalidArgumentException('Product cannot be related to itself.');
        }

        if (isset($data['relation_type']) && !in_array($data['relation_type'], self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid relation_type. Allowed: ' . implode(', ', self::ALLOWED_TYPES));
        }

        if (array_key_exists('sort_order', $data) && !is_numeric($data['sort_order'])) {
            throw new InvalidArgumentException('sort_order must be numeric.');
        }
    }
}