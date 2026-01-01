<?php
// htdocs/api/validators/OrderValidator.php
// Validation helpers for order creation and updates.
// Returns ['valid'=>bool,'errors'=>[],'data'=>sanitized_data]

class OrderValidator
{
    protected static function sanitizeString($v) { return trim(filter_var($v ?? '', FILTER_SANITIZE_STRING)); }
    protected static function sanitizeFloat($v) { return is_numeric($v) ? (float)$v : null; }
    protected static function sanitizeInt($v) { return is_numeric($v) ? (int)$v : null; }

    public static function validateCreate(array $input)
    {
        $errors = [];
        $data = [];

        // items: required, array
        if (empty($input['items']) || !is_array($input['items'])) {
            $errors['items'] = 'Order items are required';
        } else {
            $itemsOut = [];
            foreach ($input['items'] as $idx => $it) {
                $pid = $it['product_id'] ?? $it['id'] ?? null;
                $qty = $it['quantity'] ?? 1;
                if (!is_numeric($pid) || (int)$pid <= 0) {
                    $errors["items.{$idx}.product_id"] = 'Invalid product id';
                    continue;
                }
                if (!is_numeric($qty) || (int)$qty <= 0) {
                    $errors["items.{$idx}.quantity"] = 'Quantity must be at least 1';
                    continue;
                }
                $itemsOut[] = ['product_id' => (int)$pid, 'quantity' => (int)$qty, 'variant_id' => isset($it['variant_id']) ? (int)$it['variant_id'] : null];
            }
            $data['items'] = $itemsOut;
        }

        // user or guest email
        if (isset($input['user_id']) && is_numeric($input['user_id'])) {
            $data['user_id'] = (int)$input['user_id'];
        } else {
            $email = trim($input['email'] ?? $input['guest_email'] ?? '');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Customer user_id or valid guest email is required';
            } else {
                $data['guest_email'] = strtolower($email);
            }
        }

        // shipping & billing
        if (isset($input['shipping_address_id'])) {
            $data['shipping_address_id'] = self::sanitizeInt($input['shipping_address_id']);
        } else {
            // allow shipping object
            if (!empty($input['shipping_address']) && is_array($input['shipping_address'])) {
                $data['shipping_address'] = $input['shipping_address'];
            } else {
                // some systems allow orders without shipping (digital)
                $data['shipping_address_id'] = null;
            }
        }

        if (isset($input['billing_address_id'])) {
            $data['billing_address_id'] = self::sanitizeInt($input['billing_address_id']);
        } elseif (!empty($input['billing_address']) && is_array($input['billing_address'])) {
            $data['billing_address'] = $input['billing_address'];
        }

        // payment method
        $paymentMethod = trim($input['payment_method'] ?? '');
        if ($paymentMethod === '') $errors['payment_method'] = 'Payment method is required';
        $data['payment_method'] = $paymentMethod;

        // totals / optional overrides
        if (isset($input['shipping_fee'])) $data['shipping_fee'] = self::sanitizeFloat($input['shipping_fee']) ?? 0.0;
        if (isset($input['discount_amount'])) $data['discount_amount'] = self::sanitizeFloat($input['discount_amount']) ?? 0.0;
        if (isset($input['currency'])) $data['currency'] = strtoupper(self::sanitizeString($input['currency']));

        // optional client provided id for idempotency
        if (!empty($input['client_provided_id'])) $data['client_provided_id'] = self::sanitizeString($input['client_provided_id']);

        // optional notes
        if (isset($input['notes'])) $data['notes'] = trim($input['notes']);

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateUpdateStatus(array $input)
    {
        $errors = [];
        $data = [];

        $orderId = $input['order_id'] ?? $input['id'] ?? null;
        if (!is_numeric($orderId)) $errors['order_id'] = 'Order id is required';
        else $data['order_id'] = (int)$orderId;

        $status = trim($input['status'] ?? '');
        $allowed = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'failed'];
        if ($status === '' || !in_array(strtolower($status), $allowed)) {
            $errors['status'] = 'Invalid order status';
        } else {
            $data['status'] = strtolower($status);
        }

        if (isset($input['reason'])) $data['reason'] = trim($input['reason']);

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }

    public static function validateCancel(array $input)
    {
        $errors = [];
        $data = [];

        $orderId = $input['order_id'] ?? $input['id'] ?? null;
        if (!is_numeric($orderId)) $errors['order_id'] = 'Order id is required';
        else $data['order_id'] = (int)$orderId;

        $data['reason'] = trim($input['reason'] ?? 'Cancelled by user');
        $data['refund'] = !empty($input['refund']); // whether to refund automatically

        return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
    }
}
?>