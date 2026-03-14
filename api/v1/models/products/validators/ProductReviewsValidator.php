<?php
declare(strict_types=1);

final class ProductReviewsValidator
{
    public function validate(array $data, bool $isUpdate = false): void {
        $required = ['product_id','user_id','rating'];
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field])) throw new InvalidArgumentException("$field is required.");
            }
        }

        if (isset($data['rating']) && ($data['rating'] < 1 || $data['rating'] > 5)) {
            throw new InvalidArgumentException("rating must be between 1 and 5.");
        }

        foreach (['product_id','user_id','is_verified_purchase','is_approved','helpful_count'] as $intField) {
            if (isset($data[$intField]) && !is_int($data[$intField])) {
                throw new InvalidArgumentException("$intField must be integer.");
            }
        }
    }
}