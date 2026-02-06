<?php
declare(strict_types=1);

final class Product_categoriesValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if (!isset($data['product_id']) || !is_numeric($data['product_id'])) {
            $this->errors[] = 'Product ID is required and must be numeric';
        }

        if (!isset($data['category_id']) || !is_numeric($data['category_id'])) {
            $this->errors[] = 'Category ID is required and must be numeric';
        }

        if (isset($data['is_primary']) && !in_array((int)$data['is_primary'], [0,1], true)) {
            $this->errors[] = 'is_primary must be 0 or 1';
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
