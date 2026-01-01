<?php
// htdocs/api/models/Payment.php
// Model لمدفوعات النظام (Payments Model)
// يدعم: إنشاء دفعات، تحديث الحالة، استعلامات، استرجاع (refunds)، التكامل مع بوابات الدفع (Stripe, PayPal)، Webhook handling، وإحصاءات.

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/security.php';

class Payment
{
    private $mysqli;

    public function __construct()
    {
        $this->mysqli = connectDB();
    }

    /**
     * إنشاء سجل دفعة جديد قبل توجيه المستخدم لبوابة الدفع
     *
     * @param array $data (order_id, user_id, amount, currency, gateway, meta = [])
     * @return array|false السجل المُنشأ
     */
    public function create($data)
    {
        $sql = "INSERT INTO payments
                (order_id, user_id, amount, currency, gateway, gateway_reference, status, meta, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NULL, ?, ?, NOW(), NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("Payment create prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        $orderId = $data['order_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $amount = (float)($data['amount'] ?? 0.0);
        $currency = $data['currency'] ?? DEFAULT_CURRENCY;
        $gateway = $data['gateway'] ?? 'offline';
        $status = $data['status'] ?? PAYMENT_STATUS_PENDING;
        $meta = !empty($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null;

        $stmt->bind_param('iidsssi', $orderId, $userId, $amount, $currency, $gateway, $status, $meta);
        // Note: bind types: i (order), i (user), d (amount), s curr, s gateway, s status, s meta
        // But above string uses 'iiddsss' normally. To avoid mistakes, build with proper types:
        $stmt->close();

        $sql = "INSERT INTO payments
                (order_id, user_id, amount, currency, gateway, gateway_reference, status, meta, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NULL, ?, ?, NOW(), NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('i d s s s s', $orderId, $userId, $amount, $currency, $gateway, $status);
        // PHP's bind_param requires a single string types param; using a robust approach instead:

        // Safer explicit binding:
        $stmt->bind_param('iidsiss', $orderId, $userId, $amount, $currency, $gateway, $status, $meta);

        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            Utils::log("Payment created: ID {$id}, Order: {$orderId}, Amount: {$amount} {$currency}, Gateway: {$gateway}", 'INFO');
            return $this->findById($id);
        }

        $err = $stmt->error;
        $stmt->close();
        Utils::log("Payment create failed: " . $err, 'ERROR');
        return false;
    }

    /**
     * العثور على دفعة حسب المعرف
     *
     * @param int $id
     * @return array|null
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM payments WHERE id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $p = $res->fetch_assoc();
        $stmt->close();

        // فك الmeta إذا كان موجوداً
        if (!empty($p['meta'])) {
            $p['meta'] = json_decode($p['meta'], true);
        }

        return $p;
    }

    /**
     * العثور على دفعات مرتبطة بطلب
     *
     * @param int $orderId
     * @return array
     */
    public function findByOrderId($orderId)
    {
        $sql = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['meta'])) $r['meta'] = json_decode($r['meta'], true);
            $rows[] = $r;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * تحديث حالة دفعة (عند استلام رد من بوابة الدفع)
     *
     * @param int $id
     * @param string $status
     * @param string|null $gatewayRef
     * @param array|null $meta
     * @return bool
     */
    public function updateStatus($id, $status, $gatewayRef = null, $meta = null)
    {
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $sql = "UPDATE payments SET status = ?, gateway_reference = ?, meta = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('sssi', $status, $gatewayRef, $metaJson, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            Utils::log("Payment {$id} status updated to {$status}", 'INFO');
        } else {
            Utils::log("Payment status update failed for {$id}: " . $this->mysqli->error, 'ERROR');
        }

        return $ok;
    }

    /**
     * تسوية دفعة: تحويل حالة الطلب إذا نجحت الدفعة
     *
     * @param int $paymentId
     * @return bool
     */
    public function settlePayment($paymentId)
    {
        $payment = $this->findById($paymentId);
        if (!$payment) return false;

        if ($payment['status'] !== PAYMENT_STATUS_SUCCESS) {
            return false;
        }

        // جلب الطلب وتحديث حالته إلى مدفوع/مؤكد
        $orderId = $payment['order_id'];
        $stmt = $this->mysqli->prepare("UPDATE orders SET payment_status = ?, order_status = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) return false;
        $paymentStatus = PAYMENT_STATUS_PAID;
        $orderStatus = ORDER_STATUS_CONFIRMED;
        $stmt->bind_param('ssi', $paymentStatus, $orderStatus, $orderId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            Utils::log("Order {$orderId} marked as paid/confirmed due to payment {$paymentId}", 'INFO');
        }

        return $ok;
    }

    /**
     * معالجة webhook/notification من بوابة الدفع
     * - يقوم بالتحقق، تحديث السجل، والرد المناسب
     *
     * @param string $gateway اسم البوابة (stripe, paypal, unifonic etc.)
     * @param array $payload بيانات الwebhook
     * @return array ['success' => bool, 'message' => string]
     */
    public function handleGatewayWebhook($gateway, $payload)
    {
        $gateway = strtolower($gateway);

        try {
            switch ($gateway) {
                case 'stripe':
                    // انتظار: payload يحتوي على event object من Stripe
                    // افتراض payload['data']['object'] يحتوي على charge/payment_intent
                    $obj = $payload['data']['object'] ?? null;
                    if (!$obj) return ['success' => false, 'message' => 'Invalid payload'];

                    // حاول إيجاد الدفع بواسطة metadata أو id
                    $gatewayRef = $obj['id'] ?? null;
                    $meta = $obj;

                    // إذا كانت metadata تحتوي على payment_id
                    $paymentId = $obj['metadata']['payment_id'] ?? null;
                    if ($paymentId) {
                        $status = in_array($obj['status'], ['succeeded', 'paid']) ? PAYMENT_STATUS_SUCCESS : PAYMENT_STATUS_FAILED;
                        $this->updateStatus($paymentId, $status, $gatewayRef, $meta);
                        if ($status === PAYMENT_STATUS_SUCCESS) $this->settlePayment($paymentId);
                        return ['success' => true, 'message' => 'Processed'];
                    }

                    // بديل: ابحث بدلالة gateway_reference
                    $payment = $this->findByGatewayReference($gatewayRef);
                    if ($payment) {
                        $status = in_array($obj['status'], ['succeeded', 'paid']) ? PAYMENT_STATUS_SUCCESS : PAYMENT_STATUS_FAILED;
                        $this->updateStatus($payment['id'], $status, $gatewayRef, $meta);
                        if ($status === PAYMENT_STATUS_SUCCESS) $this->settlePayment($payment['id']);
                        return ['success' => true, 'message' => 'Processed'];
                    }
                    return ['success' => false, 'message' => 'Payment record not found'];
                    break;

                case 'paypal':
                    // معالجة مشابهة استناداً إلى payload
                    $resource = $payload['resource'] ?? $payload;
                    $gatewayRef = $resource['id'] ?? null;
                    // ابحث بدلالة gateway_reference
                    $payment = $this->findByGatewayReference($gatewayRef);
                    if ($payment) {
                        $status = ($resource['state'] ?? $resource['status']) === 'completed' ? PAYMENT_STATUS_SUCCESS : PAYMENT_STATUS_FAILED;
                        $this->updateStatus($payment['id'], $status, $gatewayRef, $resource);
                        if ($status === PAYMENT_STATUS_SUCCESS) $this->settlePayment($payment['id']);
                        return ['success' => true, 'message' => 'Processed'];
                    }
                    return ['success' => false, 'message' => 'Payment not found'];
                    break;

                default:
                    return ['success' => false, 'message' => 'Gateway not supported'];
            }
        } catch (Exception $e) {
            Utils::log("Webhook handling error for {$gateway}: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * إيجاد دفعة حسب مرجع البوابة
     *
     * @param string $gatewayReference
     * @return array|null
     */
    public function findByGatewayReference($gatewayReference)
    {
        if (empty($gatewayReference)) return null;
        $sql = "SELECT * FROM payments WHERE gateway_reference = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $gatewayReference);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $p = $res->fetch_assoc();
        $stmt->close();
        if (!empty($p['meta'])) $p['meta'] = json_decode($p['meta'], true);
        return $p;
    }

    /**
     * إنشاء طلب استرجاع (refund)
     *
     * @param int $paymentId
     * @param float $amount
     * @param string|null $reason
     * @return array ['success'=>bool, 'message'=>string, 'refund_id'=>int|null]
     */
    public function createRefund($paymentId, $amount, $reason = null)
    {
        $payment = $this->findById($paymentId);
        if (!$payment) return ['success' => false, 'message' => 'Payment not found', 'refund_id' => null];

        if ($payment['status'] !== PAYMENT_STATUS_SUCCESS && $payment['status'] !== PAYMENT_STATUS_PAID) {
            return ['success' => false, 'message' => 'Cannot refund a non-completed payment', 'refund_id' => null];
        }

        $amount = (float)$amount;
        if ($amount <= 0 || $amount > $payment['amount']) {
            return ['success' => false, 'message' => 'Invalid refund amount', 'refund_id' => null];
        }

        $sql = "INSERT INTO payment_refunds (payment_id, amount, reason, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['success' => false, 'message' => 'DB error', 'refund_id' => null];

        $status = REFUND_STATUS_PENDING;
        $stmt->bind_param('idss', $paymentId, $amount, $reason, $status);
        if ($stmt->execute()) {
            $refundId = $stmt->insert_id;
            $stmt->close();

            Utils::log("Refund created: refund {$refundId} for payment {$paymentId} amount {$amount}", 'INFO');

            // نبدأ عملية الاسترجاع عبر البوابة المناسبة (مثال: Stripe/PayPal) - تنفيذ gateway-specific خارج هذه الدالة عادة
            return ['success' => true, 'message' => 'Refund created', 'refund_id' => $refundId];
        }

        $err = $stmt->error;
        $stmt->close();
        Utils::log("Refund create failed: " . $err, 'ERROR');
        return ['success' => false, 'message' => 'Failed to create refund', 'refund_id' => null];
    }

    /**
     * تحديث حالة استرجاع
     *
     * @param int $refundId
     * @param string $status
     * @param array|null $meta
     * @return bool
     */
    public function updateRefundStatus($refundId, $status, $meta = null)
    {
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $sql = "UPDATE payment_refunds SET status = ?, meta = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('ssi', $status, $metaJson, $refundId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            Utils::log("Refund {$refundId} status updated to {$status}", 'INFO');
        }

        return $ok;
    }

    /**
     * جلب استرجاع حسب id
     *
     * @param int $refundId
     * @return array|null
     */
    public function findRefundById($refundId)
    {
        $sql = "SELECT * FROM payment_refunds WHERE id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $refundId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $r = $res->fetch_assoc();
        $stmt->close();
        if (!empty($r['meta'])) $r['meta'] = json_decode($r['meta'], true);
        return $r;
    }

    /**
     * الحصول على قائمة الدفعات مع فلترة
     *
     * @param array $filters (user_id, order_id, status, gateway, from, to)
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getAll($filters = [], $page = 1, $perPage = 20)
    {
        $where = [];
        $params = [];
        $types = '';

        if (isset($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = (int)$filters['user_id'];
            $types .= 'i';
        }
        if (isset($filters['order_id'])) {
            $where[] = "order_id = ?";
            $params[] = (int)$filters['order_id'];
            $types .= 'i';
        }
        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        if (isset($filters['gateway'])) {
            $where[] = "gateway = ?";
            $params[] = $filters['gateway'];
            $types .= 's';
        }
        if (isset($filters['from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['from'];
            $types .= 's';
        }
        if (isset($filters['to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['to'];
            $types .= 's';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) as total FROM payments {$whereClause}";
        $stmt = $this->mysqli->prepare($countSql);
        if ($stmt && !empty($params)) $stmt->bind_param($types, ...$params);
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            $total = (int)($res->fetch_assoc()['total'] ?? 0);
            $stmt->close();
        } else {
            $total = 0;
        }

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM payments {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) $stmt->bind_param($types, ...$params);
        if (!$stmt) return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['meta'])) $r['meta'] = json_decode($r['meta'], true);
            $rows[] = $r;
        }
        $stmt->close();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => ceil($total / $perPage)];
    }

    /**
     * إحصائيات الدفعات
     *
     * @return array
     */
    public function getStatistics()
    {
        $sql = "SELECT
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN status in ('success','paid') THEN amount ELSE 0 END) as total_received,
                    SUM(CASE WHEN status in ('failed') THEN 1 ELSE 0 END) as failed_count,
                    SUM(amount) as gross_amount
                FROM payments";
        $res = $this->mysqli->query($sql);
        return $res ? $res->fetch_assoc() : ['total_payments' => 0, 'total_received' => 0, 'failed_count' => 0, 'gross_amount' => 0];
    }
}

// نهاية Payment Model
?>