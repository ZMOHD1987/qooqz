<?php
// htdocs/api/controllers/OrderController.php
// Controller لإدارة الطلبات (إنشاء طلب، تحديث الحالة، قوائم للعميل/التاجر/المدير، إلغاء، إحصاءات، إصدار فاتورة)

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/notification.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';

class OrderController
{
    /**
     * إنشاء طلب جديد (Checkout)
     * POST /api/orders
     * يتوقع بنية request مع user_id (أو auth)، items (product_id, quantity, optional variant), shipping_method, payment_method, addresses...
     */
    public static function create()
    {
        $user = AuthMiddleware::authenticateOptional();

        // الحصول على البيانات من JSON body أو POST
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $rules = [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_method' => 'optional|string',
            'shipping_address_id' => 'optional|integer',
            'billing_address_id' => 'optional|integer',
            'payment_method' => 'required|string'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $orderModel = new Order();
        $productModel = new Product();

        // حساب الأسعار والتحقق من المخزون
        $subtotal = 0;
        $itemsProcessed = [];
        foreach ($validated['items'] as $it) {
            $prod = $productModel->findById((int)$it['product_id']);
            if (!$prod) {
                Response::error("Product not found: {$it['product_id']}", 404);
            }

            $price = $productModel->getProductPrice($prod['id']) ?? 0.0;
            $qty = (int)$it['quantity'];

            // تحقق من المخزون إن تُدار المخزون
            if ($prod['manage_stock'] && $prod['stock_quantity'] < $qty) {
                Response::error("Insufficient stock for product: {$prod['sku']}", 400);
            }

            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;

            $itemsProcessed[] = [
                'product_id' => $prod['id'],
                'vendor_id' => $prod['vendor_id'],
                'sku' => $prod['sku'],
                'name' => $productModel->getProductName($prod['id']),
                'quantity' => $qty,
                'price' => $price,
                'total' => $lineTotal
            ];
        }

        // خصومات/كوبونات - مبسطة: قبول coupon_code في input
        $discount = 0;
        if (!empty($input['coupon_code'])) {
            // يمكن التحقق من الكوبون عبر Cart/ Coupon model - هنا مجرد placeholder
            // $couponResult = (new Cart())->applyCoupon(...);
            // إذا نجح: $discount = $couponResult['discount'];
            $discount = 0; // placeholder
        }

        // ضريبة مبسطة
        $tax = 0;
        foreach ($itemsProcessed as $it) {
            $taxRate = $it['tax_rate'] ?? DEFAULT_TAX_RATE;
            $tax += ($it['price'] * $it['quantity']) * ($taxRate / 100);
        }

        // شحن مبسط
        $shippingFee = (float)($input['shipping_fee'] ?? 0);

        $grandTotal = max(0, $subtotal - $discount + $tax + $shippingFee);

        // تركيب بيانات الطلب
        $orderData = [
            'user_id' => $user['id'] ?? ($input['user_id'] ?? null),
            'order_number' => $orderModel->generateOrderNumber(),
            'order_status' => ORDER_STATUS_PENDING,
            'payment_status' => PAYMENT_STATUS_PENDING,
            'order_type' => $input['order_type'] ?? 'normal',
            'shipping_address_id' => $input['shipping_address_id'] ?? null,
            'billing_address_id' => $input['billing_address_id'] ?? null,
            'shipping_method' => $input['shipping_method'] ?? null,
            'shipping_fee' => $shippingFee,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'grand_total' => $grandTotal,
            'currency' => $input['currency'] ?? DEFAULT_CURRENCY,
            'payment_method' => $input['payment_method'],
            'notes' => $input['notes'] ?? null
        ];

        // إنشاء الطلب
        $newOrder = $orderModel->create($orderData);
        if (!$newOrder) {
            Response::error('Failed to create order', 500);
        }

        // إضافة عناصر الطلب
        foreach ($itemsProcessed as $it) {
            $orderModel->addOrderItem($newOrder['id'], $it);
            // خفض المخزون
            $productModel->updateStock($it['product_id'], $it['quantity'], 'subtract');
        }

        // إنشاء سجل دفعة أولية (Payment model) إذا لزم
        $paymentModel = new Payment();
        $payment = $paymentModel->create([
            'order_id' => $newOrder['id'],
            'user_id' => $orderData['user_id'],
            'amount' => $grandTotal,
            'currency' => $orderData['currency'],
            'gateway' => $orderData['payment_method'],
            'status' => PAYMENT_STATUS_PENDING,
            'meta' => ['created_from' => 'api']
        ]);

        // إرسال إشعارات
        Notification::orderCreated($orderData['user_id'], [
            'id' => $newOrder['id'],
            'order_number' => $newOrder['order_number'],
            'grand_total' => $newOrder['grand_total']
        ]);

        Response::created([
            'order' => $orderModel->findById($newOrder['id']),
            'payment' => $payment
        ], 'Order created successfully');
    }

    /**
     * جلب طلب حسب id أو order_number (العميل/التاجر/المدير)
     * GET /api/orders/{id_or_number}
     */
    public static function show($idOrNumber = null)
    {
        $user = AuthMiddleware::authenticateOptional();

        if (!$idOrNumber) Response::validationError(['id' => ['Order id or number is required']]);

        $orderModel = new Order();
        $order = is_numeric($idOrNumber) ? $orderModel->findById((int)$idOrNumber) : $orderModel->findByOrderNumber($idOrNumber);

        if (!$order) Response::error('Order not found', 404);

        // Authorization:
        // Admins can view all. Vendors can view orders that include their items. Customers can view their own orders.
        if ($user) {
            if (AuthMiddleware::isAdmin()) {
                // allowed
            } elseif (AuthMiddleware::isVendor()) {
                // check vendor owns at least one item in this order
                $vendorModel = new Vendor();
                $vendor = $vendorModel->findByUserId($user['id']);
                $owns = false;
                if ($vendor) {
                    foreach ($order['items'] as $it) {
                        if ($it['vendor_id'] == $vendor['id']) { $owns = true; break; }
                    }
                }
                if (!$owns) Response::forbidden('You do not have permission to view this order');
            } else {
                // customer: must be owner
                if ($order['user_id'] != $user['id']) Response::forbidden('You do not have permission to view this order');
            }
        } else {
            // unauthenticated: disallow
            Response::unauthorized('Authentication required to view order');
        }

        Response::success($order);
    }

    /**
     * قائمة الطلبات (قوائم حسب الدور)
     * GET /api/orders
     * Admin: جميع الطلبات. Vendor: طلبات تتعلق بالتاجر. Customer: طلباته.
     */
    public static function index()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        $q = $_GET;
        $page = isset($q['page']) ? (int)$q['page'] : 1;
        $perPage = isset($q['per_page']) ? (int)$q['per_page'] : 20;

        $filters = [];
        if (!empty($q['order_status'])) $filters['order_status'] = $q['order_status'];
        if (!empty($q['from'])) $filters['created_at_from'] = $q['from'];
        if (!empty($q['to'])) $filters['created_at_to'] = $q['to'];
        if (!empty($q['search'])) $filters['search'] = $q['search'];

        $orderModel = new Order();

        if (AuthMiddleware::isAdmin()) {
            $result = $orderModel->getAll($filters, $page, $perPage);
            Response::success($result);
        }

        if (AuthMiddleware::isVendor()) {
            // For vendors return orders that include their products
            // Simple approach: query all orders and filter; for large scale use dedicated vendor_orders table
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            if (!$vendor) Response::error('Vendor account not found', 404);

            // get all orders then filter (could be optimized)
            $all = $orderModel->getAll($filters, $page, $perPage);
            $filtered = [];
            foreach ($all['data'] as $ord) {
                foreach ($ord['items'] as $it) {
                    if ($it['vendor_id'] == $vendor['id']) { $filtered[] = $ord; break; }
                }
            }
            $all['data'] = $filtered;
            $all['total'] = count($filtered);
            Response::success($all);
        }

        // Customer
        $filters['user_id'] = $user['id'];
        $result = $orderModel->getAll($filters, $page, $perPage);
        Response::success($result);
    }

    /**
     * تحديث حالة الطلب (Admin or vendor for their orders)
     * POST /api/orders/{id}/status
     */
    public static function updateStatus($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Order id is required']]);
        $id = (int)$id;

        $input = $_POST;
        $newStatus = $input['order_status'] ?? null;
        if (!$newStatus) Response::validationError(['order_status' => ['order_status is required']]);

        $orderModel = new Order();
        $order = $orderModel->findById($id);
        if (!$order) Response::error('Order not found', 404);

        // Authorization: admin or vendor-owner (if vendor owns all/part of order)
        $isAuthorized = false;
        if (AuthMiddleware::isAdmin()) $isAuthorized = true;
        elseif (AuthMiddleware::isVendor()) {
            $vendorModel = new Vendor();
            $vendor = $vendorModel->findByUserId($user['id']);
            if ($vendor) {
                foreach ($order['items'] as $it) {
                    if ($it['vendor_id'] == $vendor['id']) { $isAuthorized = true; break; }
                }
            }
        }

        if (!$isAuthorized) Response::forbidden('You do not have permission to change this order status');

        $ok = $orderModel->updateStatus($id, $newStatus);
        if ($ok) {
            // Notify customer
            Notification::orderStatusChanged($order['user_id'], $order['order_number'], $newStatus);
            Response::success(null, 'Order status updated');
        }

        Response::error('Failed to update order status', 500);
    }

    /**
     * تحديث حالة الدفع (Admin or payment gateway webhook)
     * POST /api/orders/{id}/payment-status
     */
    public static function updatePaymentStatus($id = null)
    {
        $user = AuthMiddleware::authenticateOptional();
        if (!$id) Response::validationError(['id' => ['Order id is required']]);
        $id = (int)$id;

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $newStatus = $input['payment_status'] ?? null;
        if (!$newStatus) Response::validationError(['payment_status' => ['payment_status is required']]);

        $orderModel = new Order();
        $order = $orderModel->findById($id);
        if (!$order) Response::error('Order not found', 404);

        // Only admin or gateway handlers should set payment status (simplified)
        if (!AuthMiddleware::isAdmin() && !empty($input['gateway']) && !empty($input['gateway_secret'])) {
            // optionally validate gateway secret
        } elseif (!AuthMiddleware::isAdmin()) {
            Response::forbidden('Only admin or gateway may update payment status');
        }

        $ok = $orderModel->updatePaymentStatus($id, $newStatus);
        if ($ok) {
            if ($newStatus === PAYMENT_STATUS_PAID || $newStatus === PAYMENT_STATUS_SUCCESS) {
                // mark order confirmed
                $orderModel->updateStatus($id, ORDER_STATUS_CONFIRMED);
                Notification::paymentSuccess($order['user_id'], $order['order_number'], $order['grand_total']);
            }
            Response::success(null, 'Payment status updated');
        }

        Response::error('Failed to update payment status', 500);
    }

    /**
     * إلغاء الطلب من قبل العميل (إن أمكن) أو Admin
     * POST /api/orders/{id}/cancel
     */
    public static function cancel($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Order id is required']]);
        $id = (int)$id;

        $orderModel = new Order();
        $order = $orderModel->findById($id);
        if (!$order) Response::error('Order not found', 404);

        // Client can cancel only their own pending orders, admin can cancel any
        if (!AuthMiddleware::isAdmin() && $order['user_id'] != $user['id']) {
            Response::forbidden('You do not have permission to cancel this order');
        }

        if (!AuthMiddleware::isAdmin() && $order['order_status'] !== ORDER_STATUS_PENDING) {
            Response::error('Only pending orders can be cancelled by customer', 400);
        }

        $reason = $_POST['reason'] ?? null;
        $ok = $orderModel->cancel($id, $reason);
        if ($ok) {
            // restore stock for items
            $productModel = new Product();
            foreach ($order['items'] as $it) {
                $productModel->updateStock($it['product_id'], $it['quantity'], 'add');
            }

            Notification::orderStatusChanged($order['user_id'], $order['order_number'], ORDER_STATUS_CANCELLED);
            Response::success(null, 'Order cancelled successfully');
        }

        Response::error('Failed to cancel order', 500);
    }

    /**
     * إصدار فاتورة مبسطة (PDF generation placeholder)
     * GET /api/orders/{id}/invoice
     */
    public static function invoice($id = null)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();
        if (!$id) Response::validationError(['id' => ['Order id is required']]);
        $id = (int)$id;

        $orderModel = new Order();
        $order = $orderModel->findById($id);
        if (!$order) Response::error('Order not found', 404);

        // Authorization: same as show
        if (!AuthMiddleware::isAdmin() && $order['user_id'] != $user['id']) {
            // vendor may also request invoice for their part - omitted for brevity
            Response::forbidden('You do not have permission to view invoice');
        }

        // Generate invoice PDF (placeholder)
        $invoiceHtml = "<h1>Invoice for Order {$order['order_number']}</h1>";
        $invoiceHtml .= "<p>Total: {$order['grand_total']} {$order['currency']}</p>";
        // In real implementation convert HTML to PDF and output with appropriate headers

        Response::success([
            'invoice_html' => $invoiceHtml,
            'message' => 'Invoice generation not implemented (HTML returned as placeholder)'
        ]);
    }

    /**
     * إحصائيات الطلبات
     * GET /api/orders/stats
     */
    public static function stats()
    {
        RoleMiddleware::canRead('orders');

        $orderModel = new Order();
        $stats = $orderModel->getStatistics();
        Response::success($stats);
    }
}

// Router examples:
// POST /api/orders => OrderController::create()
// GET  /api/orders => OrderController::index()
// GET  /api/orders/{id} => OrderController::show()

?>