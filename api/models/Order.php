<?php
// htdocs/api/models/Order.php
// Model للطلبات (Orders Model)
// يحتوي على جميع العمليات الخاصة بإدارة الطلبات

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/utils.php';

// ===========================================
// Order Model Class
// ===========================================

class Order
{
    private $mysqli;

    // ===========================================
    // 1️⃣ Constructor
    // ===========================================

    public function __construct()
    {
        $this->mysqli = connectDB();
    }

    // ===========================================
    // 2️⃣ إنشاء طلب جديد (Create)
    // ===========================================

    /**
     * إنشاء طلب جديد
     * @param array $data بيانات الطلب
     * @return array|false
     */
    public function create($data)
    {
        $sql = "INSERT INTO orders (
                    user_id, order_number, order_status, payment_status, order_type,
                    shipping_address_id, billing_address_id, shipping_method,
                    shipping_fee, discount_amount, tax_amount, grand_total, currency,
                    payment_method, notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            Utils::log("Order create prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        $orderNumber     = $data['order_number'] ?? $this->generateOrderNumber();
        $orderStatus     = $data['order_status'] ?? ORDER_STATUS_PENDING;
        $paymentStatus   = $data['payment_status'] ?? PAYMENT_STATUS_PENDING;
        $orderType       = $data['order_type'] ?? 'normal';
        $shippingAddress = $data['shipping_address_id'] ?? null;
        $billingAddress  = $data['billing_address_id'] ?? null;
        $shippingMethod  = $data['shipping_method'] ?? null;
        $shippingFee     = $data['shipping_fee'] ?? 0;
        $discountAmount  = $data['discount_amount'] ?? 0;
        $taxAmount       = $data['tax_amount'] ?? 0;
        $grandTotal      = $data['grand_total'];
        $currency        = $data['currency'] ?? DEFAULT_CURRENCY;
        $paymentMethod   = $data['payment_method'] ?? PAYMENT_METHOD_CASH_ON_DELIVERY;
        $notes           = $data['notes'] ?? null;

        $stmt->bind_param(
            'issssiisdddddsss',
            $data['user_id'], $orderNumber, $orderStatus, $paymentStatus, $orderType,
            $shippingAddress, $billingAddress, $shippingMethod,
            $shippingFee, $discountAmount, $taxAmount, $grandTotal, $currency,
            $paymentMethod, $notes
        );

        if ($stmt->execute()) {
            $orderId = $stmt->insert_id;
            $stmt->close();

            Utils::log("Order created: ID {$orderId}, Number: {$orderNumber}, User: {$data['user_id']}", 'INFO');

            return $this->findById($orderId);
        }

        $error = $stmt->error;
        $stmt->close();

        Utils::log("Order create failed: " . $error, 'ERROR');
        return false;
    }

    // ===========================================
    // 3️⃣ البحث والاستعلام (Read/Find)
    // ===========================================

    /**
     * البحث عن طلب بالـ ID
     * @param int $id
     * @return array|null
     */
    public function findById($id)
    {
        $sql = "SELECT o.*, u.username
                FROM orders o
                INNER JOIN users u ON o.user_id = u.id
                WHERE o.id = ? ";

        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $order = $result->fetch_assoc();
        $stmt->close();

        $order['items'] = $this->getOrderItems($id);

        return $order;
    }

    /**
     * البحث عن طلب بالرقم
     * @param string $orderNumber
     * @return array|null
     */
    public function findByOrderNumber($orderNumber)
    {
        $sql = "SELECT * FROM orders WHERE order_number = ? ";

        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $orderNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $order = $result->fetch_assoc();
        $stmt->close();

        return $this->findById($order['id']);
    }

    /**
     * جلب جميع الطلبات مع فلترة
     * @param array $filters
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
            $where[] = "o.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

        if (isset($filters['order_status'])) {
            $where[] = "o.order_status = ?";
            $params[] = $filters['order_status'];
            $types .= 's';
        }

        if (isset($filters['created_at_from'])) {
            $where[] = "o.created_at >= ?";
            $params[] = $filters['created_at_from'];
            $types .= 's';
        }

        if (isset($filters['created_at_to'])) {
            $where[] = "o.created_at <= ?";
            $params[] = $filters['created_at_to'];
            $types .= 's';
        }

        if (isset($filters['search'])) {
            $where[] = "(o.order_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $types .= 's';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // حساب الإجمالي
        $countSql = "SELECT COUNT(*) as total FROM orders o {$whereClause}";
        $stmt = $this->mysqli->prepare($countSql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $total = $result->fetch_assoc()['total'];
        $stmt->close();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT o.* FROM orders o 
                {$whereClause}
                ORDER BY o.created_at DESC 
                LIMIT ? OFFSET ? ";

        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $row['items'] = $this->getOrderItems($row['id']);
            $orders[] = $row;
        }

        $stmt->close();

        return [
            'data' => $orders,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * أوامر مستخدم محدد
     * @param int $userId
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getByUser($userId, $filters = [], $page = 1, $perPage = 20)
    {
        $filters['user_id'] = $userId;
        return $this->getAll($filters, $page, $perPage);
    }

    // ===========================================
    // 4️⃣ عناصر الطلب (Order Items)
    // ===========================================

    /**
     * جلب عناصر الطلب
     * @param int $orderId
     * @return array
     */
    public function getOrderItems($orderId)
    {
        $sql = "SELECT oi.*, p.sku, pt.name as product_name FROM order_items oi
                INNER JOIN products p ON oi.product_id = p.id
                LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = ?
                WHERE oi.order_id = ? ";

        $stmt = $this->mysqli->prepare($sql);
        $language = DEFAULT_LANGUAGE;
        $stmt->bind_param('si', $language, $orderId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        $stmt->close();
        return $items;
    }

    /**
     * إضافة عنصر طلب
     * @param int $orderId
     * @param array $item
     * @return bool
     */
    public function addOrderItem($orderId, $item)
    {
        $sql = "INSERT INTO order_items (
                    order_id, product_id, vendor_id, sku, name, quantity, price, total, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'iiissidd',
            $orderId,
            $item['product_id'],
            $item['vendor_id'],
            $item['sku'],
            $item['name'],
            $item['quantity'],
            $item['price'],
            $item['total']
        );

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    // ===========================================
    // 5️⃣ تحديث الطلب (Update)
    // ===========================================

    /**
     * تحديث بيانات الطلب
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        $fields = [];
        $params = [];
        $types = '';

        // الحقول المسموح بتحديثها
        $allowedFields = [
            'order_status' => 's',
            'payment_status' => 's',
            'shipping_method' => 's',
            'shipping_fee' => 'd',
            'discount_amount' => 'd',
            'tax_amount' => 'd',
            'grand_total' => 'd',
            'currency' => 's',
            'payment_method' => 's',
            'notes' => 's',
            'updated_at' => 'auto'
        ];

        foreach ($allowedFields as $field => $type) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
                $types .= $type;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            Utils::log("Order update prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            Utils::log("Order updated: ID {$id}", 'INFO');
        }

        return $success;
    }

    /**
     * تحديث حالة الطلب فقط
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status)
    {
        return $this->update($id, ['order_status' => $status]);
    }

    /**
     * تحديث حالة الدفع فقط
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updatePaymentStatus($id, $status)
    {
        return $this->update($id, ['payment_status' => $status]);
    }

    // ===========================================
    // 6️⃣ الحذف (Delete/Cancel)
    // ===========================================

    /**
     * إلغاء الطلب
     * @param int $id
     * @param string|null $reason
     * @return bool
     */
    public function cancel($id, $reason = null)
    {
        $success = $this->update($id, ['order_status' => ORDER_STATUS_CANCELLED]);
        if ($success && $reason) {
            // إضافة ملاحظة
            $this->update($id, ['notes' => $reason]);
        }
        return $success;
    }

    /**
     * حذف الطلب (Hard Delete)
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        // حذف عناصر الطلب
        $this->mysqli->query("DELETE FROM order_items WHERE order_id = {$id}");

        // حذف الطلب نفسه
        $sql = "DELETE FROM orders WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            Utils::log("Order deleted: ID {$id}", 'WARNING');
        }

        return $success;
    }

    // ===========================================
    // 7️⃣ أرقام الطلبات (Order Number)
    // ===========================================

    /**
     * إنشاء رقم طلب عشوائي
     * @return string
     */
    public function generateOrderNumber()
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    }

    // ===========================================
    // 8️⃣ الإحصائيات (Statistics)
    // ===========================================

    /**
     * إحصائيات الطلبات
     * @param array $filters
     * @return array
     */
    public function getStatistics($filters = [])
    {
        $where = [];
        $params = [];
        $types = '';

        if (isset($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(grand_total) as total_value,
                    SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
                FROM orders
                {$whereClause}";

        $stmt = $this->mysqli->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();

        return $stats;
    }
}

// ===========================================
// ✅ تم تحميل Order Model بنجاح
// ===========================================

?>