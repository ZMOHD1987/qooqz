<?php
declare(strict_types=1);
namespace App\Models\OrderItems\Validators;
use InvalidArgumentException;

final class OrderItemsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate && !isset($data['order_id'])) throw new InvalidArgumentException("order_id is required.");
        if (!$isUpdate && !isset($data['product_id'])) throw new InvalidArgumentException("product_id is required.");
        if (!$isUpdate && !isset($data['quantity'])) throw new InvalidArgumentException("quantity is required.");
        if (isset($data['quantity']) && (int)$data['quantity'] < 1) throw new InvalidArgumentException("quantity must be at least 1.");
    }
}
