<?php
declare(strict_types=1);
namespace App\Models\OrderStatusHistory\Validators;
use InvalidArgumentException;
final class OrderStatusHistoryValidator { public function validate(array $data, bool $isUpdate = false): void { if (!$isUpdate && !isset($data['order_id'])) throw new InvalidArgumentException("order_id is required."); if (!$isUpdate && !isset($data['status'])) throw new InvalidArgumentException("status is required."); } }
