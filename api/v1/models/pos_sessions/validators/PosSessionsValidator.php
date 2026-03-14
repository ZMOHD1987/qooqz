<?php
declare(strict_types=1);

final class PosSessionsValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'open'): bool
    {
        $this->errors = [];

        switch ($scenario) {
            case 'open':
                if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                    $this->errors[] = 'entity_id is required and must be numeric';
                }
                if (isset($data['opening_balance']) && !is_numeric($data['opening_balance'])) {
                    $this->errors[] = 'opening_balance must be numeric';
                }
                if (isset($data['cashier_user_id']) && !is_numeric($data['cashier_user_id'])) {
                    $this->errors[] = 'cashier_user_id must be numeric';
                }
                break;

            case 'close':
                if (empty($data['session_id']) || !is_numeric($data['session_id'])) {
                    $this->errors[] = 'session_id is required and must be numeric';
                }
                if (isset($data['closing_balance']) && !is_numeric($data['closing_balance'])) {
                    $this->errors[] = 'closing_balance must be numeric';
                }
                break;

            case 'create_order':
                if (empty($data['session_id']) || !is_numeric($data['session_id'])) {
                    $this->errors[] = 'session_id is required and must be numeric';
                }
                if (empty($data['entity_id']) || !is_numeric($data['entity_id'])) {
                    $this->errors[] = 'entity_id is required and must be numeric';
                }
                if (empty($data['items']) || !is_array($data['items'])) {
                    $this->errors[] = 'items array is required and must not be empty';
                } else {
                    foreach ($data['items'] as $i => $item) {
                        if (empty($item['product_id']) || !is_numeric($item['product_id'])) {
                            $this->errors[] = "items[$i].product_id is required and must be numeric";
                        }
                        if (!isset($item['unit_price']) || !is_numeric($item['unit_price'])) {
                            $this->errors[] = "items[$i].unit_price is required and must be numeric";
                        }
                        if (isset($item['quantity']) && ((int)$item['quantity'] < 1)) {
                            $this->errors[] = "items[$i].quantity must be at least 1";
                        }
                        // quantity is optional; the repository defaults to 1 when omitted
                    }
                }
                $allowedMethods = ['cash', 'card', 'wallet', 'mixed'];
                if (!empty($data['payment_method']) && !in_array($data['payment_method'], $allowedMethods, true)) {
                    $this->errors[] = 'payment_method must be one of: ' . implode(', ', $allowedMethods);
                }
                break;
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
