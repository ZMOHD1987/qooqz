<?php
// htdocs/api/services/PaymentService.php
// Service layer for Payments - handles creation of payment records, refunds, gateway webhook handling,
// simple gateway placeholders (capture/refund), idempotency, and payment-related statistics.
//
// This file uses connectDB() and models in the application. It returns structured arrays and does not
// emit HTTP responses directly — controllers should translate results to HTTP responses.

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../services/OrderService.php';

class PaymentService
{
    protected $db;
    protected $paymentModel;
    protected $orderService;

    public function __construct($db = null)
    {
        $this->db = $db ?: connectDB();
        $this->paymentModel = new Payment();
        $this->orderService = new OrderService($this->db);
    }

    /**
     * Create a payment record (idempotent when client_reference provided).
     * $data: [
     *   'order_id' => int,
     *   'user_id' => int|null,
     *   'amount' => float,
     *   'currency' => 'USD',
     *   'gateway' => 'stripe'|'paypal'|...,
     *   'status' => PAYMENT_STATUS_PENDING|...,
     *   'client_reference' => string|null,
     *   'meta' => array|null
     * ]
     *
     * Returns: ['success'=>bool, 'payment'=>array, 'message'=>string]
     */
    public function create(array $data)
    {
        $orderId = isset($data['order_id']) ? (int)$data['order_id'] : null;
        $clientRef = $data['client_reference'] ?? null;

        // Idempotency: if client_reference provided and exists return existing
        if ($clientRef) {
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE client_reference = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $clientRef);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) {
                    $row = $res->fetch_assoc();
                    $stmt->close();
                    return ['success' => true, 'payment' => $row, 'message' => 'Existing payment (idempotent)'];
                }
                $stmt->close();
            }
        }

        $amount = (float)($data['amount'] ?? 0.0);
        $currency = $data['currency'] ?? (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD');
        $gateway = $data['gateway'] ?? null;
        $status = $data['status'] ?? (defined('PAYMENT_STATUS_PENDING') ? PAYMENT_STATUS_PENDING : 'pending');
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $meta = isset($data['meta']) ? json_encode($data['meta']) : null;

        $stmt = $this->db->prepare("INSERT INTO payments
            (client_reference, order_id, user_id, amount, currency, gateway, status, meta, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            return ['success' => false, 'message' => 'DB prepare failed (payments insert)'];
        }

        $stmt->bind_param('siidsssd', $clientRef, $orderId, $userId, $amount, $currency, $gateway, $status, $meta);
        // Note: binding types are best-effort; some drivers may need different types for meta (string)
        // We'll coerce meta to string earlier.

        // If bind_param types cause issues, fall back to a safer approach
        try {
            $execOk = $stmt->execute();
        } catch (Throwable $e) {
            // Attempt a manual query fallback
            $stmt->close();
            $clientRefEsc = $this->db->real_escape_string($clientRef);
            $metaEsc = $this->db->real_escape_string((string)$meta);
            $gatewayEsc = $this->db->real_escape_string($gateway);
            $currencyEsc = $this->db->real_escape_string($currency);
            $sql = "INSERT INTO payments (client_reference, order_id, user_id, amount, currency, gateway, status, meta, created_at)
                    VALUES ('{$clientRefEsc}', {$orderId}, {$userId}, {$amount}, '{$currencyEsc}', '{$gatewayEsc}', '{$status}', '{$metaEsc}', NOW())";
            $execOk = $this->db->query($sql);
        }

        if (!$execOk) {
            $err = $this->db->error;
            return ['success' => false, 'message' => "Failed to create payment record: {$err}"];
        }

        $paymentId = $this->db->insert_id;
        $payment = $this->findById($paymentId);
        return ['success' => true, 'payment' => $payment, 'message' => 'Payment record created'];
    }

    /**
     * Find payment by id
     */
    public function findById($id)
    {
        return $this->paymentModel->findById((int)$id);
    }

    /**
     * Find payments by order id
     */
    public function findByOrder($orderId)
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    /**
     * Capture/confirm a payment (gateway placeholder).
     * In real implementation this should call gateway SDK/API.
     * Returns ['success'=>bool, 'message'=>string, 'payment'=>array|null]
     */
    public function capturePayment($paymentId, $gatewayResponse = null)
    {
        $payment = $this->findById($paymentId);
        if (!$payment) return ['success' => false, 'message' => 'Payment not found'];

        // Placeholder: assume gatewayResponse contains 'status' and maybe 'transaction_id'
        $newStatus = PAYMENT_STATUS_SUCCESS ?? 'success';
        if (!empty($gatewayResponse['status'])) {
            $s = strtolower($gatewayResponse['status']);
            if (in_array($s, ['paid', 'success', 'succeeded', 'completed'])) {
                $newStatus = PAYMENT_STATUS_PAID ?? 'paid';
            } elseif (in_array($s, ['failed', 'cancelled'])) {
                $newStatus = 'failed';
            } else {
                $newStatus = $s;
            }
        }

        $txId = $gatewayResponse['transaction_id'] ?? null;
        $meta = $payment['meta'] ? json_decode($payment['meta'], true) : [];
        if ($txId) $meta['gateway_transaction_id'] = $txId;
        if (!empty($gatewayResponse)) $meta['gateway_response'] = $gatewayResponse;

        $metaJson = json_encode($meta);

        $stmt = $this->db->prepare("UPDATE payments SET status = ?, meta = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) return ['success' => false, 'message' => 'DB prepare failed (update payment)'];

        $stmt->bind_param('ssi', $newStatus, $metaJson, $paymentId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to update payment status'];
        }

        // If payment is paid, update order payment status via OrderService
        if ($newStatus === (defined('PAYMENT_STATUS_PAID') ? PAYMENT_STATUS_PAID : 'paid') ||
            $newStatus === (defined('PAYMENT_STATUS_SUCCESS') ? PAYMENT_STATUS_SUCCESS : 'success')) {
            if (!empty($payment['order_id'])) {
                $this->orderService->updatePaymentStatus((int)$payment['order_id'], PAYMENT_STATUS_PAID ?? 'paid');
            }
        }

        $updated = $this->findById($paymentId);
        return ['success' => true, 'payment' => $updated, 'message' => 'Payment captured/updated'];
    }

    /**
     * Create a refund request for a payment.
     * This creates a refund record and optionally attempts gateway refund via placeholder.
     * Returns ['success'=>bool, 'refund_id'=>int|null, 'message'=>string]
     */
    public function createRefund($paymentId, $amount, $reason = null)
    {
        $payment = $this->findById($paymentId);
        if (!$payment) return ['success' => false, 'message' => 'Payment not found'];

        $amount = (float)$amount;
        if ($amount <= 0) return ['success' => false, 'message' => 'Invalid refund amount'];

        $stmt = $this->db->prepare("INSERT INTO refunds (payment_id, order_id, amount, reason, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) return ['success' => false, 'message' => 'DB prepare failed (refund insert)'];

        $status = 'pending';
        $orderId = $payment['order_id'] ? (int)$payment['order_id'] : null;
        $stmt->bind_param('iidsi', $paymentId, $orderId, $amount, $reason, $status);
        // bind types: note reason may be null - driver may need 's' type; adapt by casting to string
        try {
            $ok = $stmt->execute();
        } catch (Throwable $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to create refund: ' . $e->getMessage()];
        }
        if (!$ok) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => "Failed to create refund: {$err}"];
        }
        $refundId = $this->db->insert_id;
        $stmt->close();

        // Placeholder: attempt to perform gateway refund synchronously (in real world use async job)
        $gateway = $payment['gateway'] ?? null;
        $gatewayResult = $this->attemptGatewayRefund($payment, $amount, $reason);

        if ($gatewayResult['success']) {
            // mark refund as completed
            $stmt2 = $this->db->prepare("UPDATE refunds SET status = ?, gateway_response = ?, processed_at = NOW() WHERE id = ?");
            if ($stmt2) {
                $respJson = json_encode($gatewayResult['response'] ?? $gatewayResult);
                $completed = 'completed';
                $stmt2->bind_param('ssi', $completed, $respJson, $refundId);
                $stmt2->execute();
                $stmt2->close();
            }
            // Optionally update payments table meta/status
            $this->updatePaymentMetaWithRefund($paymentId, $refundId, $amount);
            return ['success' => true, 'refund_id' => $refundId, 'message' => 'Refund completed'];
        } else {
            // leave as pending/failed depending on gatewayResult
            $stmt3 = $this->db->prepare("UPDATE refunds SET status = ?, gateway_response = ? WHERE id = ?");
            if ($stmt3) {
                $respJson = json_encode($gatewayResult['response'] ?? $gatewayResult);
                $failed = $gatewayResult['message'] ?? 'failed';
                $stmt3->bind_param('ssi', $failed, $respJson, $refundId);
                $stmt3->execute();
                $stmt3->close();
            }
            return ['success' => false, 'refund_id' => $refundId, 'message' => $gatewayResult['message'] ?? 'Refund initiation failed'];
        }
    }

    /**
     * Attempt to refund via gateway (placeholder).
     * In real use, call gateway SDK and handle auth/errors.
     * Returns ['success'=>bool, 'response'=>array, 'message'=>string]
     */
    protected function attemptGatewayRefund($payment, $amount, $reason = null)
    {
        // This is a simple simulation: pretend popular gateways succeed, otherwise fail.
        $gateway = strtolower($payment['gateway'] ?? '');
        // simulate network/gateway call
        try {
            if (in_array($gateway, ['stripe', 'paypal', 'gateway_sim'])) {
                $response = [
                    'gateway' => $gateway,
                    'status' => 'succeeded',
                    'refunded_amount' => $amount,
                    'gateway_ref' => 'REF' . strtoupper(bin2hex(random_bytes(3)))
                ];
                return ['success' => true, 'response' => $response, 'message' => 'Refund succeeded'];
            }
            // unknown gateway -> fail
            return ['success' => false, 'response' => null, 'message' => 'Unsupported gateway for automatic refund'];
        } catch (Throwable $e) {
            return ['success' => false, 'response' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper: update payment meta to include refund info
     */
    protected function updatePaymentMetaWithRefund($paymentId, $refundId, $amount)
    {
        $payment = $this->findById($paymentId);
        $meta = $payment && !empty($payment['meta']) ? json_decode($payment['meta'], true) : [];
        if (!isset($meta['refunds'])) $meta['refunds'] = [];
        $meta['refunds'][] = ['refund_id' => $refundId, 'amount' => $amount, 'created_at' => date('c')];
        $metaJson = json_encode($meta);
        $stmt = $this->db->prepare("UPDATE payments SET meta = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $metaJson, $paymentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Handle gateway webhook payload. Generic mapping:
     * - validate signature if possible
     * - detect payment/order reference
     * - update payment record and order via OrderService
     *
     * $gateway: string|null
     * $payload: array
     * $headers: associative array of headers for signature validation
     *
     * Returns ['success'=>bool, 'message'=>string]
     */
    public function handleWebhook($gateway = null, array $payload = [], array $headers = [])
    {
        // Optionally validate signature - placeholder
        $valid = $this->validateWebhookSignature($gateway, $payload, $headers);
        if ($valid === false) {
            return ['success' => false, 'message' => 'Invalid webhook signature'];
        }

        // find payment or order reference in payload
        $orderRef = $payload['order_number'] ?? $payload['order_id'] ?? $payload['reference'] ?? null;
        $paymentRef = $payload['payment_reference'] ?? $payload['payment_id'] ?? null;
        $status = $payload['status'] ?? $payload['payment_status'] ?? null;
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : null;

        // Try locate payment by payment reference if provided
        $payment = null;
        if ($paymentRef) {
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE client_reference = ? OR id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('si', $paymentRef, $paymentRef);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows) $payment = $res->fetch_assoc();
                $stmt->close();
            }
        }

        // If not found and orderRef provided, try find payments for that order and pick most recent
        if (!$payment && $orderRef) {
            $order = is_numeric($orderRef) ? $this->orderService->getById((int)$orderRef) : $this->orderService->getByOrderNumber($orderRef);
            if ($order) {
                $payments = $this->findByOrder($order['id']);
                if (!empty($payments)) $payment = $payments[0];
            }
        }

        // If still not found and orderRef provided, we may create a payment record (optional)
        if (!$payment && $orderRef && $amount !== null) {
            // create a payment record with webhook data (idempotency: client_reference from payload if present)
            $createData = [
                'order_id' => is_numeric($orderRef) ? (int)$orderRef : null,
                'amount' => $amount,
                'currency' => $payload['currency'] ?? (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD'),
                'gateway' => $gateway ?? ($payload['gateway'] ?? null),
                'status' => $status ?? (defined('PAYMENT_STATUS_PENDING') ? PAYMENT_STATUS_PENDING : 'pending'),
                'client_reference' => $paymentRef ?? null,
                'meta' => $payload
            ];
            $resCreate = $this->create($createData);
            if ($resCreate['success']) {
                $payment = $resCreate['payment'];
            }
        }

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment record not found and could not be created from webhook'];
        }

        // Map gateway status to internal
        $lower = is_string($status) ? strtolower($status) : null;
        $paidStates = ['paid', 'succeeded', 'success', 'completed'];
        $failedStates = ['failed', 'refused', 'canceled', 'cancelled'];

        if (in_array($lower, $paidStates)) {
            // mark payment paid and update order
            $this->capturePayment($payment['id'], ['status' => $lower, 'payload' => $payload]);
            return ['success' => true, 'message' => 'Payment marked as paid'];
        }

        if (in_array($lower, $failedStates)) {
            // update payment to failed
            $stmt = $this->db->prepare("UPDATE payments SET status = ?, meta = CONCAT(IFNULL(meta, '{}'), ?), updated_at = NOW() WHERE id = ?");
            if ($stmt) {
                $metaJson = json_encode(['webhook' => $payload]);
                $stmt->bind_param('ssi', $lower, $metaJson, $payment['id']);
                $stmt->execute();
                $stmt->close();
            } else {
                // fallback simple update
                $metaEsc = $this->db->real_escape_string(json_encode(['webhook' => $payload]));
                $this->db->query("UPDATE payments SET status = '{$lower}', meta = '{$metaEsc}', updated_at = NOW() WHERE id = {$payment['id']}");
            }
            return ['success' => true, 'message' => 'Payment marked as failed'];
        }

        // If webhook indicates a refund
        if (isset($payload['event']) && strpos(strtolower($payload['event']), 'refund') !== false) {
            // create or update refund record
            $refundAmount = $payload['refund_amount'] ?? $payload['amount'] ?? null;
            if ($refundAmount) {
                $r = $this->createRefund($payment['id'], (float)$refundAmount, 'Refund via webhook');
                return $r;
            }
        }

        return ['success' => true, 'message' => 'Webhook processed (no actionable status change)'];
    }

    /**
     * Validate webhook signature - placeholder.
     * Return true if valid, false if invalid, null to skip validation.
     */
    protected function validateWebhookSignature($gateway = null, array $payload = [], array $headers = [])
    {
        // Try to validate using gateway-specific secret from config/constants (placeholder)
        // For unknown gateways return null to indicate skip (not recommended in prod).
        if (!$gateway) return null;

        $gateway = strtolower($gateway);
        // Example: if gateway is stripe, check stripe-signature header using configured secret
        if ($gateway === 'stripe') {
            // NOTE: real stripe validation needs SDK; here we just check presence of header
            if (!empty($headers['stripe-signature'])) return true;
            return false;
        }

        // For other gateways allow skipping validation
        return null;
    }

    /**
     * Basic statistics
     */
    public function getStatistics()
    {
        $stats = [
            'total_payments' => 0,
            'total_volume' => 0.0,
            'by_gateway' => [],
            'by_status' => []
        ];

        $res = $this->db->query("SELECT COUNT(*) as cnt, SUM(amount) as volume FROM payments");
        if ($res) {
            $row = $res->fetch_assoc();
            $stats['total_payments'] = (int)($row['cnt'] ?? 0);
            $stats['total_volume'] = (float)($row['volume'] ?? 0.0);
            $res->close();
        }

        $r2 = $this->db->query("SELECT gateway, COUNT(*) as cnt, SUM(amount) as vol FROM payments GROUP BY gateway");
        if ($r2) {
            while ($row = $r2->fetch_assoc()) {
                $stats['by_gateway'][$row['gateway']] = ['count' => (int)$row['cnt'], 'volume' => (float)$row['vol']];
            }
            $r2->close();
        }

        $r3 = $this->db->query("SELECT status, COUNT(*) as cnt FROM payments GROUP BY status");
        if ($r3) {
            while ($row = $r3->fetch_assoc()) {
                $stats['by_status'][$row['status']] = (int)$row['cnt'];
            }
            $r3->close();
        }

        return $stats;
    }
}

?>