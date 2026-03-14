<?php
declare(strict_types=1);

final class ProductQuestionsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['product_id', 'user_id', 'question'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("$field is required.");
                }
            }
        }

        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException('product_id must be numeric.');
        }
        if (isset($data['user_id']) && !is_numeric($data['user_id'])) {
            throw new InvalidArgumentException('user_id must be numeric.');
        }
        if (isset($data['question']) && strlen($data['question']) > 65535) { // text field max length
            throw new InvalidArgumentException('question is too long.');
        }
        if (array_key_exists('is_approved', $data) && !in_array((int)$data['is_approved'], [0,1], true)) {
            throw new InvalidArgumentException('is_approved must be 0 or 1.');
        }
        if (array_key_exists('helpful_count', $data) && (!is_numeric($data['helpful_count']) || $data['helpful_count'] < 0)) {
            throw new InvalidArgumentException('helpful_count must be a non-negative integer.');
        }
    }
}