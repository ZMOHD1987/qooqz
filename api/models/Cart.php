<?php
// htdocs/api/models/Cart.php
// Model لسلة التسوق (Cart Model)
// يدعم: سلة مستخدم، سلة زائر (session), إضافة/تحديث/حذف عناصر، تجميع السلة، حساب الإجماليات، كوبون

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/security.php';

class Cart
{
    private $mysqli;

    public function __construct()
    {
        $this->mysqli = connectDB();
    }

    /**
     * إنشاء سلة جديدة أو استرجاعها
     * @param int|null $userId
     * @param string|null $sessionId
     * @return array|false
     */
    public function getOrCreateCart($userId = null, $sessionId = null)
    {
        // حاول استرجاع حسب user
        if ($userId) {
            $cart = $this->findByUserId($userId);
            if ($cart) return $cart;
        }

        // حاول استرجاع حسب session
        if ($sessionId) {
            $cart = $this->findBySessionId($sessionId);
            if ($cart) return $cart;
        }

        // إنشاء سلة جديدة
        $sql = "INSERT INTO carts (user_id, session_id, coupon_code, coupon_amount, created_at, updated_at)
                VALUES (?, ?, NULL, 0, NOW(), NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            Utils::log("Cart create prepare failed: " . $this->mysqli->error, 'ERROR');
            return false;
        }

        // bind params allow nulls
        $uid = $userId ? $userId : null;
        $sid = $sessionId ? $sessionId : null;
        $stmt->bind_param('is', $uid, $sid);
        if ($stmt->execute()) {
            $cartId = $stmt->insert_id;
            $stmt->close();
            return $this->findById($cartId);
        }

        $error = $stmt->error;
        $stmt->close();
        Utils::log("Cart create failed: " . $error, 'ERROR');
        return false;
    }

    /**
     * إيجاد سلة حسب ID
     * @param int $id
     * @return array|null
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM carts WHERE id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $cart = $res->fetch_assoc();
        $stmt->close();

        $cart['items'] = $this->getItems($cart['id']);
        $cart['totals'] = $this->getTotals($cart['id']);
        return $cart;
    }

    /**
     * إيجاد سلة حسب user_id
     * @param int $userId
     * @return array|null
     */
    public function findByUserId($userId)
    {
        $sql = "SELECT * FROM carts WHERE user_id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $cart = $res->fetch_assoc();
        $stmt->close();
        return $cart;
    }

    /**
     * إيجاد سلة حسب session_id
     * @param string $sessionId
     * @return array|null
     */
    public function findBySessionId($sessionId)
    {
        $sql = "SELECT * FROM carts WHERE session_id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $cart = $res->fetch_assoc();
        $stmt->close();
        return $cart;
    }

    /**
     * الحصول على عناصر السلة
     * @param int $cartId
     * @return array
     */
    public function getItems($cartId)
    {
        $sql = "SELECT ci.*, p.sku, pt.name as product_name, pp.price
                FROM cart_items ci
                INNER JOIN products p ON ci.product_id = p.id
                LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = ?
                LEFT JOIN product_pricing pp ON p.id = pp.product_id
                WHERE ci.cart_id = ?
                ORDER BY ci.created_at ASC";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return [];
        $lang = DEFAULT_LANGUAGE;
        $stmt->bind_param('si', $lang, $cartId);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($r = $res->fetch_assoc()) $items[] = $r;
        $stmt->close();
        return $items;
    }

    /**
     * إضافة عنصر للسلة أو تحديث الكمية إذا موجود
     * @param int $cartId
     * @param int $productId
     * @param int $quantity
     * @param int|null $variantId
     * @return array|false - العنصر أو false
     */
    public function addItem($cartId, $productId, $quantity = 1, $variantId = null)
    {
        // تحقق من المنتج ومخزونه وسعره
        $product = $this->getProductForCart($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        // تحقق من الكمية المطلوبة مقابل المخزون إذا تُدار المخزون
        if ((int)$product['manage_stock'] && $product['stock_quantity'] < $quantity) {
            return ['success' => false, 'message' => 'Insufficient stock'];
        }

        // تحقق إن كان نفس المنتج موجوداً في السلة
        $sql = "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL)) LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['success' => false, 'message' => 'DB error'];
        $v = $variantId;
        $stmt->bind_param('iiis', $cartId, $productId, $v, $v); // bind variant twice to allow NULL comparison
        $stmt->execute();
        $res = $stmt->get_result();
        $existing = $res->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // تحديث الكمية
            $newQty = (int)$existing['quantity'] + (int)$quantity;
            $this->updateItemQuantity($cartId, $existing['id'], $newQty);
            return ['success' => true, 'message' => 'Quantity updated', 'item_id' => $existing['id']];
        }

        // إدراج جديد
        $price = $product['price'] ?? 0;
        $total = $price * $quantity;

        $sql = "INSERT INTO cart_items (cart_id, product_id, variant_id, sku, name, quantity, price, total, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['success' => false, 'message' => 'DB error'];

        $sku = $product['sku'];
        $name = $product['product_name'] ?? ('Product #' . $productId);
        $vid = $variantId ? $variantId : null;
        $stmt->bind_param('iiissidd', $cartId, $productId, $vid, $sku, $name, $quantity, $price, $total);

        if ($stmt->execute()) {
            $itemId = $stmt->insert_id;
            $stmt->close();
            $this->touchCartUpdated($cartId);
            Utils::log("Cart item added: cart {$cartId}, product {$productId}, qty {$quantity}", 'INFO');
            return ['success' => true, 'item_id' => $itemId];
        }

        $err = $stmt->error;
        $stmt->close();
        Utils::log("Add cart item failed: " . $err, 'ERROR');
        return ['success' => false, 'message' => 'Insert failed'];
    }

    /**
     * تحديث كمية عنصر في السلة
     * @param int $cartId
     * @param int $itemId
     * @param int $quantity
     * @return bool
     */
    public function updateItemQuantity($cartId, $itemId, $quantity)
    {
        $quantity = max(0, (int)$quantity);

        // إذا الكمية 0 => حذف العنصر
        if ($quantity === 0) {
            return $this->removeItem($cartId, $itemId);
        }

        // جلب عنصر للتأكد من المنتج والمخزون
        $sql = "SELECT product_id FROM cart_items WHERE id = ? AND cart_id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('ii', $itemId, $cartId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return false; }
        $row = $res->fetch_assoc();
        $stmt->close();

        $product = $this->getProductForCart($row['product_id']);
        if (!$product) return false;

        if ((int)$product['manage_stock'] && $product['stock_quantity'] < $quantity) {
            return false; // insufficient stock
        }

        $price = $product['price'] ?? 0;
        $total = $price * $quantity;

        $sql = "UPDATE cart_items SET quantity = ?, price = ?, total = ?, updated_at = NOW() WHERE id = ? AND cart_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('iddii', $quantity, $price, $total, $itemId, $cartId);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $this->touchCartUpdated($cartId);
            Utils::log("Cart item updated: item {$itemId}, qty {$quantity}", 'INFO');
        }

        return $success;
    }

    /**
     * حذف عنصر من السلة
     * @param int $cartId
     * @param int $itemId
     * @return bool
     */
    public function removeItem($cartId, $itemId)
    {
        $sql = "DELETE FROM cart_items WHERE id = ? AND cart_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('ii', $itemId, $cartId);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $this->touchCartUpdated($cartId);
            Utils::log("Cart item removed: item {$itemId}", 'INFO');
        }

        return $success;
    }

    /**
     * مسح السلة بالكامل
     * @param int $cartId
     * @return bool
     */
    public function clearCart($cartId)
    {
        $this->mysqli->begin_transaction();
        try {
            $stmt = $this->mysqli->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->mysqli->prepare("UPDATE carts SET coupon_code = NULL, coupon_amount = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();

            $this->mysqli->commit();
            Utils::log("Cart cleared: {$cartId}", 'INFO');
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            Utils::log("Clear cart failed: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * حساب الإجماليات للسلة
     * @param int $cartId
     * @return array ['subtotal', 'discount', 'tax', 'shipping', 'grand_total', 'items_count']
     */
    public function getTotals($cartId)
    {
        $items = $this->getItems($cartId);
        $subtotal = 0;
        $itemsCount = 0;
        foreach ($items as $it) {
            $subtotal += (float)$it['total'];
            $itemsCount += (int)$it['quantity'];
        }

        // تحميل كوبون مخزن إن وُجد
        $couponAmount = 0;
        $couponCode = null;
        $stmt = $this->mysqli->prepare("SELECT coupon_code, coupon_amount FROM carts WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows) {
                $c = $res->fetch_assoc();
                $couponCode = $c['coupon_code'];
                $couponAmount = (float)$c['coupon_amount'];
            }
            $stmt->close();
        }

        // الضريبة - يمكن تعديل حسب منطق ضريبي (هنا نستخدم ثابت تقريبي أو نسبة لكل منتج إن متوفرة)
        $tax = 0;
        foreach ($items as $it) {
            $taxRate = (float)($it['tax_rate'] ?? DEFAULT_TAX_RATE);
            $tax += ($it['price'] * $it['quantity']) * ($taxRate / 100);
        }

        // مصاريف الشحن - placeholder (يمكن ربط بطرق شحن)
        $shipping = 0; // لاحقاً حساب حسب الوجهة والوزن

        $grandTotal = max(0, $subtotal - $couponAmount + $tax + $shipping);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($couponAmount, 2),
            'tax' => round($tax, 2),
            'shipping' => round($shipping, 2),
            'grand_total' => round($grandTotal, 2),
            'items_count' => (int)$itemsCount,
            'coupon_code' => $couponCode
        ];
    }

    /**
     * تطبيق كوبون على السلة (يفترض وجود جدول coupons)
     * @param int $cartId
     * @param string $couponCode
     * @return array ['success'=>bool, 'message'=>string]
     */
    public function applyCoupon($cartId, $couponCode)
    {
        $couponCode = trim($couponCode);
        if ($couponCode === '') return ['success' => false, 'message' => 'Invalid coupon'];

        // جلب الكوبون من قاعدة البيانات
        $sql = "SELECT id, code, type, value, min_cart_amount, usage_limit, expires_at, times_used, is_active
                FROM coupons WHERE code = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return ['success' => false, 'message' => 'DB error'];
        $stmt->bind_param('s', $couponCode);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return ['success' => false, 'message' => 'Coupon not found']; }
        $coupon = $res->fetch_assoc();
        $stmt->close();

        if (!(int)$coupon['is_active']) return ['success' => false, 'message' => 'Coupon inactive'];
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) return ['success' => false, 'message' => 'Coupon expired'];
        if ($coupon['usage_limit'] && $coupon['times_used'] >= $coupon['usage_limit']) return ['success' => false, 'message' => 'Coupon usage limit reached'];

        $totals = $this->getTotals($cartId);
        if ($coupon['min_cart_amount'] && $totals['subtotal'] < $coupon['min_cart_amount']) {
            return ['success' => false, 'message' => 'Cart amount does not meet coupon minimum'];
        }

        // حساب قيمة الخصم
        $discount = 0;
        if ($coupon['type'] === 'percentage') {
            $discount = ($coupon['value'] / 100) * $totals['subtotal'];
        } else { // fixed
            $discount = (float)$coupon['value'];
        }

        $discount = min($discount, $totals['subtotal']); // لا يتجاوز المجموع

        // حفظ في carts
        $stmt = $this->mysqli->prepare("UPDATE carts SET coupon_code = ?, coupon_amount = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) return ['success' => false, 'message' => 'DB error'];
        $stmt->bind_param('sdi', $couponCode, $discount, $cartId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            // زيادة times_used في الكوبون (اختياري، قد تريد تسجيل مستخدم)
            $stmt = $this->mysqli->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $coupon['id']);
                $stmt->execute();
                $stmt->close();
            }

            $this->touchCartUpdated($cartId);
            Utils::log("Coupon applied: {$couponCode} on cart {$cartId}", 'INFO');
            return ['success' => true, 'message' => 'Coupon applied', 'discount' => round($discount, 2)];
        }

        return ['success' => false, 'message' => 'Failed to apply coupon'];
    }

    /**
     * إلغاء تطبيق الكوبون
     * @param int $cartId
     * @return bool
     */
    public function removeCoupon($cartId)
    {
        $stmt = $this->mysqli->prepare("UPDATE carts SET coupon_code = NULL, coupon_amount = 0, updated_at = NOW() WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('i', $cartId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) $this->touchCartUpdated($cartId);
        return $ok;
    }

    /**
     * دمج سلة زائر مع سلة المستخدم عند تسجيل الدخول
     * @param string $sessionId
     * @param int $userId
     * @return bool
     */
    public function mergeGuestCartToUser($sessionId, $userId)
    {
        $guestCart = $this->findBySessionId($sessionId);
        if (!$guestCart) return false;

        $userCart = $this->getOrCreateCart($userId, null);

        // نقل العناصر (إذا نفس المنتج موجود، نجمع الكميات)
        $items = $this->getItems($guestCart['id']);
        foreach ($items as $it) {
            // حاول العثور على عنصر مطابق في سلة المستخدم
            $sql = "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL)) LIMIT 1";
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) continue;
            $v = $it['variant_id'];
            $stmt->bind_param('iiis', $userCart['id'], $it['product_id'], $v, $v);
            $stmt->execute();
            $res = $stmt->get_result();
            $existing = $res->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $newQty = $existing['quantity'] + $it['quantity'];
                $this->updateItemQuantity($userCart['id'], $existing['id'], $newQty);
            } else {
                // إعادة تعيين cart_id إلى سلة المستخدم
                $stmt = $this->mysqli->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, sku, name, quantity, price, total, created_at, updated_at)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if (!$stmt) continue;
                $vid = $it['variant_id'] ? $it['variant_id'] : null;
                $stmt->bind_param('iiissidd', $userCart['id'], $it['product_id'], $vid, $it['sku'], $it['name'], $it['quantity'], $it['price'], $it['total']);
                $stmt->execute();
                $stmt->close();
            }
        }

        // حذف سلة الزائر
        $this->clearCart($guestCart['id']);
        $stmt = $this->mysqli->prepare("DELETE FROM carts WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $guestCart['id']);
            $stmt->execute();
            $stmt->close();
        }

        // ربط سلة المستخدم إلى user_id (تأكد)
        $this->touchCartUpdated($userCart['id']);
        return true;
    }

    /**
     * عدد العناصر في السلة
     * @param int $cartId
     * @return int
     */
    public function countItems($cartId)
    {
        $stmt = $this->mysqli->prepare("SELECT SUM(quantity) as cnt FROM cart_items WHERE cart_id = ?");
        if (!$stmt) return 0;
        $stmt->bind_param('i', $cartId);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt = $res->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();
        return (int)$cnt;
    }

    /**
     * تحديث حقل updated_at في carts
     * @param int $cartId
     */
    private function touchCartUpdated($cartId)
    {
        $stmt = $this->mysqli->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * جلب بيانات المنتج اللازمة للسلة (السعر، المخزون، manage_stock)
     * @param int $productId
     * @return array|null
     */
    private function getProductForCart($productId)
    {
        $sql = "SELECT p.id, p.sku, p.manage_stock, p.stock_quantity, pp.price, pt.name
                FROM products p
                LEFT JOIN product_pricing pp ON p.id = pp.product_id
                LEFT JOIN product_translations pt ON p.id = pt.product_id AND pt.language_code = ?
                WHERE p.id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) return null;
        $lang = DEFAULT_LANGUAGE;
        $stmt->bind_param('si', $lang, $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) { $stmt->close(); return null; }
        $p = $res->fetch_assoc();
        $stmt->close();
        return $p;
    }
}

// تم تحميل Cart Model بنجاح
?>