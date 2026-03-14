<?php
declare(strict_types=1);
namespace App\Models\Payments\Validators;
use InvalidArgumentException;
final class PaymentsValidator { public function validate(array $data, bool $isUpdate = false): void { if (!$isUpdate && !isset($data['order_id'])) throw new InvalidArgumentException("order_id is required."); if (!$isUpdate && !isset($data['user_id'])) throw new InvalidArgumentException("user_id is required."); if (!$isUpdate && !isset($data['amount'])) throw new InvalidArgumentException("amount is required."); if (isset($data['amount']) && (float)$data['amount'] <= 0) throw new InvalidArgumentException("amount must be positive."); } }
