<?php
declare(strict_types=1);
namespace App\Models\OrderReviews\Validators;
use InvalidArgumentException;
final class OrderReviewsValidator { public function validate(array $data, bool $isUpdate = false): void { if (!$isUpdate && !isset($data['order_id'])) throw new InvalidArgumentException("order_id is required."); if (!$isUpdate && !isset($data['user_id'])) throw new InvalidArgumentException("user_id is required."); if (!$isUpdate && !isset($data['vendor_id'])) throw new InvalidArgumentException("vendor_id is required."); if (!$isUpdate && !isset($data['overall_rating'])) throw new InvalidArgumentException("overall_rating is required."); if (isset($data['overall_rating']) && ((int)$data['overall_rating'] < 1 || (int)$data['overall_rating'] > 5)) throw new InvalidArgumentException("overall_rating must be between 1 and 5."); } }
