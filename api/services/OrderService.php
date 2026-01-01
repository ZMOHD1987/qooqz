<?php
// htdocs/api/services/OrderService.php
// Service layer for Orders - handles complex business logic (creation, totals, stock reservation,
// payment state transitions, refunds, cancellations, vendor-splitting, invoices, webhooks, stats)
//
// This service uses procedural mysqli helper connectDB() which should exist in the application.
// It is written to be defensive: uses DB transactions for multi-step operations, validates stock,
// supports idempotency via an optional client_provided_id, and returns structured results.
//
// Note: This file intentionally avoids direct HTTP output and works at the application layer.
// Controllers should call these methods and translate results to HTTP responses.

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Vendor.php';

class OrderService
{
    protected $db;
    protected $productModel;

    public function __construct($db = null)
    {
        $this->db = $db ?: connectDB();
        $this->productModel = new Product();
    }

    /**
     * Generate a readable unique order number.
     * Format: ORD-YYYYMMDD-<6chars>
     */
    public function generateOrderNumber()
    {
        $date = date('Ymd');
        $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return "ORD-{$date}-{$rand}";
    }

    /**
     * Calculate totals for a given items array.
     * items: [ ['product_id'=>int, 'quantity'=>int, 'variant_id'=>int|null], ... ]
     * Returns: ['subtotal'=>float, 'tax'=>float, 'shipping'=>float, 'discount'=>float, 'grand_total'=>float, 'lines'=>array]
     */
    public function calculateTotals(array $items, $shippingFee = 0.0, $discount = 0.0)
    {
        $lines = [];
        $subtotal = 0.0;
        $tax = 0.0;

        // collect product ids
        $pids = array_map(function ($i) { return (int)$i['product_id']; }, $items);
        $pids = array_values(array_unique($pids));

        // fetch product data in batch
        $products = $this->productModel->findByIds($pids); // expected: associative array id => product
        // fallback if model doesn't support it
        if (!is_array($products)) {
            // naive approach: individual queries
            $products = [];
            foreach ($pids as $pid) {
                $p = $this->productModel->findById($pid);
                if ($p) $products[$pid] = $p;
            }
        }

        foreach ($items as $it) {
            $pid = (int)$it['product_id'];
            $qty = max(0, (int)$it['quantity']);
            $prod = $products[$pid] ?? null;
            if (!$prod) {
                throw new Exception("Product not found: {$pid}");
            }

            // determine price (product model may have method getProductPrice)
            $price = isset($prod['price']) ? (float)$prod['price'] : (float)$this->productModel->getProductPrice($pid);
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;

            // tax calculation (basic): use product tax rate or default constant
            $taxRate = isset($prod['tax_rate']) ? (float)$prod['tax_rate'] : (defined('DEFAULT_TAX_RATE') ? DEFAULT_TAX_RATE : 0.0);
            $lineTax = ($price * $qty) * ($taxRate / 100.0);
            $tax += $lineTax;

            $lines[] = [
                'product_id' => $pid,
                'name' => $prod['name'] ?? ($prod['title'] ?? 'Product'),
                'sku' => $prod['sku'] ?? null,
                'price' => $price,
                'quantity' => $qty,
                'total' => $lineTotal,
                'tax' => $lineTax,
                'vendor_id' => $prod['vendor_id'] ?? null
            ];
        }

        $grand = max(0.0, $subtotal - (float)$discount + $tax + (float)$shippingFee);

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shippingFee, 2),
            'discount' => round($discount, 2),
            'grand_total' => round($grand, 2),
            'lines' => $lines
        ];
    }

    /**
     * Create an order (transactional).
     * $orderData: associative array for orders table (user_id, shipping_address_id, billing_address_id, shipping_method, currency, payment_method, etc.)
     * $items: array of items as in calculateTotals.
     * $options: ['client_provided_id' => string|null, 'reserve_stock' => bool]
     *
     * Returns array on success: ['success'=>true, 'order' => orderRow]
     * On failure: ['success'=>false, 'message'=>string]
     */
    public function create(array $orderData, array $items, array $options = [])
    {
        $clientProvidedId = $options['client_provided_id'] ?? null;
        $reserveStock = isset($options['reserve_stock']) ? (bool)$options['reserve_stock'] : true;

        // Idempotency check: if client_provided_id exists, try to find existing order
        if ($clientProvidedId) {
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE client_provided_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $clientProvidedId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) {
                    $existing = $res->fetch_assoc();
                    $stmt->close();
                    return ['success' => true, 'order' => $existing, 'message' => 'Order already exists (idempotent)'];
                }
                $stmt->close();
            }
        }

        // Validate items and optionally reserve/consume stock
        try {
            if ($reserveStock) {
                $this->db->begin_transaction();

                // Check availability and subtract stock atomically
                foreach ($items as $it) {
                    $pid = (int)$it['product_id'];
                    $qty = max(0, (int)$it['quantity']);
                    if ($qty <= 0) {
                        throw new Exception("Invalid quantity for product {$pid}");
                    }

                    // fetch product manage_stock & stock_quantity
                    $p = $this->productModel->findById($pid);
                    if (!$p) {
                        throw new Exception("Product not found: {$pid}");
                    }

                    $manage = (int)($p['manage_stock'] ?? 0);
                    $stockQty = (int)($p['stock_quantity'] ?? 0);

                    if ($manage && $stockQty < $qty) {
                        throw new Exception("Insufficient stock for product: " . ($p['sku'] ?? $pid));
                    }

                    // subtract stock if managed
                    if ($manage) {
                        $stmtUpd = $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
                        if (!$stmtUpd) throw new Exception("DB prepare failed for stock update");
                        $stmtUpd->bind_param('iii', $qty, $pid, $qty);
                        $stmtUpd->execute();
                        if ($this->db->affected_rows === 0) {
                            $stmtUpd->close();
                            throw new Exception("Failed to reserve stock for product: {$pid}");
                        }
                        $stmtUpd->close();
                    }
                }

                // if all stock reserved proceed to create order
                // fall through to insert order and items
            } else {
                // start transaction anyway to keep consistency when inserting order and items
                $this->db->begin_transaction();
            }

            // Prepare totals
            $shippingFee = $orderData['shipping_fee'] ?? 0.0;
            $discount = $orderData['discount_amount'] ?? 0.0;
            $totals = $this->calculateTotals($items, $shippingFee, $discount);

            // fill default order fields
            $orderNumber = $this->generateOrderNumber();
            $order_status = $orderData['order_status'] ?? (defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'pending');
            $payment_status = $orderData['payment_status'] ?? (defined('PAYMENT_STATUS_PENDING') ? PAYMENT_STATUS_PENDING : 'pending');

            $stmt = $this->db->prepare("INSERT INTO orders
                (client_provided_id, user_id, order_number, order_status, payment_status, shipping_address_id, billing_address_id,
                 shipping_method, shipping_fee, discount_amount, tax_amount, grand_total, currency, payment_method, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            if (!$stmt) {
                throw new Exception("Failed to prepare order insert");
            }

            $clientIdParam = $clientProvidedId;
            $userId = $orderData['user_id'] ?? null;
            $shippingAddr = $orderData['shipping_address_id'] ?? null;
            $billingAddr = $orderData['billing_address_id'] ?? null;
            $shippingMethod = $orderData['shipping_method'] ?? null;
            $taxAmount = $totals['tax'];
            $grandTotal = $totals['grand_total'];
            $currency = $orderData['currency'] ?? ($orderData['currency'] ?? (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD'));
            $paymentMethod = $orderData['payment_method'] ?? null;
            $notes = $orderData['notes'] ?? null;

            $stmt->bind_param(
                'sisssiisdidddss',
                $clientIdParam,
                $userId,
                $orderNumber,
                $order_status,
                $payment_status,
                $shippingAddr,
                $billingAddr,
                $shippingMethod,
                $shippingFee,
                $discount,
                $taxAmount,
                $grandTotal,
                $currency,
                $paymentMethod,
                $notes
            );

            // Note: binding types may require adjustment depending on DB schema; above is best-effort.
            $execOk = @$stmt->execute();
            if (!$execOk) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception("Failed to create order: {$err}");
            }
            $orderId = $this->db->insert_id;
            $stmt->close();

            // Insert order items
            $stmtItem = $this->db->prepare("INSERT INTO order_items
                (order_id, product_id, vendor_id, sku, name, quantity, price, total, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmtItem) throw new Exception("Failed to prepare order_items insert");

            foreach ($totals['lines'] as $line) {
                $vid = $line['vendor_id'] ?? null;
                $sku = $line['sku'] ?? null;
                $name = $this->db->real_escape_string($line['name'] ?? '');
                $qty = (int)$line['quantity'];
                $price = (float)$line['price'];
                $total = (float)$line['total'];

                $stmtItem->bind_param('iiissidd', $orderId, $line['product_id'], $vid, $sku, $name, $qty, $price, $total);
                $ok = @$stmtItem->execute();
                if (!$ok) {
                    $err = $stmtItem->error;
                    $stmtItem->close();
                    throw new Exception("Failed to insert order item: {$err}");
                }
            }
            $stmtItem->close();

            // Commit transaction
            $this->db->commit();

            // fetch order to return
            $order = $this->getById($orderId);

            return ['success' => true, 'order' => $order];

        } catch (Throwable $e) {
            // rollback and try to restore stock if reserved
            try { $this->db->rollback(); } catch (Throwable $_) {}
            // if reserveStock was true we attempted subtracting stock for some products; attempt to restore
            if ($reserveStock && !empty($items)) {
                foreach ($items as $it) {
                    $pid = (int)$it['product_id'];
                    $qty = max(0, (int)$it['quantity']);
                    if ($qty <= 0) continue;
                    // best-effort restore
                    @$this->db->query("UPDATE products SET stock_quantity = stock_quantity + {$qty} WHERE id = {$pid}");
                }
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get order by id with items
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $stmt->close(); return null; }
        $order = $res->fetch_assoc();
        $stmt->close();

        // fetch items
        $stmt2 = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        if ($stmt2) {
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $itemsRes = $stmt2->get_result();
            $items = $itemsRes ? $itemsRes->fetch_all(MYSQLI_ASSOC) : [];
            $stmt2->close();
            $order['items'] = $items;
        } else {
            $order['items'] = [];
        }

        return $order;
    }

    /**
     * Get order by order number
     */
    public function getByOrderNumber($number)
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $number);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { $stmt->close(); return null; }
        $order = $res->fetch_assoc();
        $stmt->close();
        $order['items'] = [];
        $stmt2 = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        if ($stmt2) {
            $stmt2->bind_param('i', $order['id']);
            $stmt2->execute();
            $r = $stmt2->get_result();
            $order['items'] = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
            $stmt2->close();
        }
        return $order;
    }

    /**
     * Update order status
     */
    public function updateStatus($orderId, $newStatus)
    {
        $stmt = $this->db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('si', $newStatus, $orderId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Update payment status and optionally trigger order confirmation when paid.
     */
    public function updatePaymentStatus($orderId, $paymentStatus)
    {
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed");
            $stmt->bind_param('si', $paymentStatus, $orderId);
            $stmt->execute();
            $stmt->close();

            // If paid -> set order status to confirmed (business rule)
            if (in_array($paymentStatus, ['paid', 'success', PAYMENT_STATUS_PAID ?? 'paid'])) {
                $this->updateStatus($orderId, ORDER_STATUS_CONFIRMED ?? 'confirmed');
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Cancel an order - restores stock for items if managed.
     * Returns boolean.
     */
    public function cancel($orderId, $reason = null)
    {
        $order = $this->getById($orderId);
        if (!$order) return false;

        // Only pending or confirmed orders may be cancelled according to business rules - leave to caller to check
        $this->db->begin_transaction();
        try {
            // update order status
            $stmt = $this->db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW(), cancelled_reason = ? WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed");
            $status = ORDER_STATUS_CANCELLED ?? 'cancelled';
            $stmt->bind_param('ssi', $status, $reason, $orderId);
            $stmt->execute();
            $stmt->close();

            // restore stock
            foreach ($order['items'] as $it) {
                $pid = (int)$it['product_id'];
                $qty = (int)$it['quantity'];

                // check if product manages stock
                $p = $this->productModel->findById($pid);
                if ($p && (int)($p['manage_stock'] ?? 0) === 1) {
                    $stmtR = $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    if ($stmtR) {
                        $stmtR->bind_param('ii', $qty, $pid);
                        $stmtR->execute();
                        $stmtR->close();
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Create a refund record for an order (simple wrapper).
     * This method only creates DB record; gateway interaction should be done by PaymentService.
     */
    public function createRefund($orderId, $amount, $reason = null)
    {
        $stmt = $this->db->prepare("INSERT INTO refunds (order_id, amount, reason, status, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) return ['success' => false, 'message' => 'DB prepare failed'];
        $status = 'pending';
        $stmt->bind_param('idss', $orderId, $amount, $reason, $status);
        $ok = $stmt->execute();
        if (!$ok) {
            $msg = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $msg];
        }
        $refundId = $this->db->insert_id;
        $stmt->close();
        return ['success' => true, 'refund_id' => $refundId];
    }

    /**
     * Split order items by vendor (returns vendor_id => items[])
     */
    public function splitByVendor(array $items)
    {
        $out = [];
        foreach ($items as $it) {
            $vendor = $it['vendor_id'] ?? null;
            if ($vendor === null) $vendor = 0;
            if (!isset($out[$vendor])) $out[$vendor] = [];
            $out[$vendor][] = $it;
        }
        return $out;
    }

    /**
     * Generate a simple HTML invoice placeholder for an order.
     */
    public function generateInvoiceHtml($orderId)
    {
        $order = $this->getById($orderId);
        if (!$order) return null;

        $html = "<h1>Invoice: {$order['order_number']}</h1>";
        $html .= "<p>Date: " . date('Y-m-d', strtotime($order['created_at'])) . "</p>";
        $html .= "<table border='1' cellpadding='6' cellspacing='0'><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>";
        foreach ($order['items'] as $it) {
            $html .= "<tr><td>" . htmlspecialchars($it['name']) . "</td><td>{$it['quantity']}</td><td>{$it['price']}</td><td>{$it['total']}</td></tr>";
        }
        $html .= "</tbody></table>";
        $html .= "<p>Subtotal: {$order['subtotal']}</p>";
        $html .= "<p>Tax: {$order['tax_amount']}</p>";
        $html .= "<p>Shipping: {$order['shipping_fee']}</p>";
        $html .= "<h3>Total: {$order['grand_total']} {$order['currency']}</h3>";

        return $html;
    }

    /**
     * Handle payment gateway webhook payload - updates payment/order statuses.
     * $gateway: string|null, $payload: associative array
     *
     * Returns array('success'=>bool, 'message'=>string)
     */
    public function handlePaymentWebhook($gateway = null, array $payload = [])
    {
        // This is highly gateway-specific. Provide a generic flow:
        // - validate signature if provided
        // - find payment or order by reference in payload
        // - update payment record & order payment status

        try {
            // naive detection
            $orderRef = $payload['order_number'] ?? $payload['order_id'] ?? $payload['reference'] ?? null;
            $paymentStatus = $payload['status'] ?? $payload['payment_status'] ?? null;
            $amount = isset($payload['amount']) ? (float)$payload['amount'] : null;

            if (!$orderRef) return ['success' => false, 'message' => 'No order reference in webhook'];

            // try find order
            $order = is_numeric($orderRef) ? $this->getById((int)$orderRef) : $this->getByOrderNumber($orderRef);

            if (!$order) return ['success' => false, 'message' => 'Order not found for webhook reference'];

            // map gateway status to internal
            $mapPaid = in_array(strtolower($paymentStatus), ['paid', 'success', 'succeeded', 'completed']);

            // update payment_status
            $newPaymentStatus = $mapPaid ? (defined('PAYMENT_STATUS_PAID') ? PAYMENT_STATUS_PAID : 'paid') : (strtolower($paymentStatus) === 'failed' ? 'failed' : $paymentStatus);

            $this->updatePaymentStatus($order['id'], $newPaymentStatus);

            return ['success' => true, 'message' => 'Webhook handled'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Basic statistics about orders
     */
    public function getStatistics()
    {
        $stats = [
            'total_orders' => 0,
            'total_revenue' => 0.0,
            'orders_by_status' => []
        ];

        $res = $this->db->query("SELECT COUNT(*) AS cnt, SUM(grand_total) AS revenue FROM orders");
        if ($res) {
            $row = $res->fetch_assoc();
            $stats['total_orders'] = (int)($row['cnt'] ?? 0);
            $stats['total_revenue'] = (float)($row['revenue'] ?? 0.0);
            $res->close();
        }

        $res2 = $this->db->query("SELECT order_status, COUNT(*) AS cnt FROM orders GROUP BY order_status");
        if ($res2) {
            $by = [];
            while ($r = $res2->fetch_assoc()) {
                $by[$r['order_status']] = (int)$r['cnt'];
            }
            $stats['orders_by_status'] = $by;
            $res2->close();
        }

        return $stats;
    }
}

?>