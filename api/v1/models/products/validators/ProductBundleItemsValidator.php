<?php
declare(strict_types=1);

final class ProductBundleItemsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['bundle_id'])) {
                throw new InvalidArgumentException('bundle_id is required.');
            }
            if (!is_numeric($data['bundle_id'])) {
                throw new InvalidArgumentException('bundle_id must be numeric.');
            }
        }

        if (isset($data['product_id'])) {
            if (!is_numeric($data['product_id'])) {
                throw new InvalidArgumentException('product_id must be numeric.');
            }
        } elseif (!$isUpdate) {
            throw new InvalidArgumentException('product_id is required.');
        }

        if (array_key_exists('quantity', $data)) {
            if (!is_numeric($data['quantity']) || $data['quantity'] < 1) {
                throw new InvalidArgumentException('quantity must be a positive integer.');
            }
        }

        if (array_key_exists('product_price', $data)) {
            if (!is_numeric($data['product_price']) || $data['product_price'] < 0) {
                throw new InvalidArgumentException('product_price must be a non-negative number.');
            }
        }
    }
}