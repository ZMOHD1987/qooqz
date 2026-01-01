<?php
// htdocs/api/controllers/CartController.php
// Controller لسلة التسوق (Cart) - إدارة السلة للعُملاء والزوار: إنشاء/جلب/إضافة/تحديث/حذف عناصر، كوبونات، دمج سلة الضيف عند تسجيل الدخول

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/rate_limit.php';

class CartController
{
    /**
     * Helper: الحصول على session id من هيدرز أو كوكي أو توليد
     * @return string
     */
    private static function getSessionId()
    {
        // تفضيل هيدر مخصص
        $sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? null;

        // ثم كوكي
        if (!$sessionId && isset($_COOKIE['session_id'])) {
            $sessionId = $_COOKIE['session_id'];
        }

        // إذا لم يوجد، أنشئ واحد مؤقت (لا تحفظ في DB هنا)
        if (!$sessionId) {
            $sessionId = bin2hex(random_bytes(16));
            // ضع الكوكي لتيسير الاستخدام (اختياري)
            setcookie('session_id', $sessionId, time() + 60 * 60 * 24 * 30, '/', '', is_https(), true);
        }

        return $sessionId;
    }

    /**
     * جلب أو إنشاء سلة
     * GET /api/cart or POST /api/cart/get
     */
    public static function getCart()
    {
        // مصادقة اختيارية — إذا مصادق، استخدم user_id وإلا session
        $user = AuthMiddleware::authenticateOptional();

        $sessionId = self::getSessionId();
        $cartModel = new Cart();

        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);

        if (!$cart) {
            Response::error('Failed to retrieve or create cart', 500);
        }

        Response::success($cart);
    }

    /**
     * إضافة عنصر إلى السلة
     * POST /api/cart/items
     * body: product_id, quantity, variant_id (optional)
     */
    public static function addItem()
    {
        RateLimitMiddleware::forAPI(Security::getRealIP());

        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $rules = [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'optional|integer|min:1',
            'variant_id' => 'optional|integer'
        ];

        $validated = Validator::make($input, $rules)->validated();

        $cartModel = new Cart();
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Failed to get cart', 500);

        $quantity = isset($validated['quantity']) ? (int)$validated['quantity'] : 1;
        $variantId = $validated['variant_id'] ?? null;

        $res = $cartModel->addItem($cart['id'], (int)$validated['product_id'], $quantity, $variantId);

        if (isset($res['success']) && $res['success']) {
            $updated = $cartModel->findById($cart['id']);
            Response::success($updated, $res['message'] ?? 'Item added to cart');
        }

        Response::error($res['message'] ?? 'Failed to add item', 400);
    }

    /**
     * تحديث كمية عنصر في السلة
     * PUT /api/cart/items/{item_id}
     * body: quantity
     */
    public static function updateItem($itemId = null)
    {
        RateLimitMiddleware::forAPI(Security::getRealIP());

        if (!$itemId) Response::validationError(['item_id' => ['Item id is required']]);

        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $rules = [
            'quantity' => 'required|integer|min:0'
        ];
        $validated = Validator::make($input, $rules)->validated();

        $cartModel = new Cart();
        // find cart by session or user
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Cart not found', 404);

        $ok = $cartModel->updateItemQuantity($cart['id'], (int)$itemId, (int)$validated['quantity']);

        if ($ok) {
            $updated = $cartModel->findById($cart['id']);
            Response::success($updated, 'Cart item updated');
        }

        Response::error('Failed to update cart item', 500);
    }

    /**
     * إزالة عنصر من السلة
     * DELETE /api/cart/items/{item_id}
     */
    public static function removeItem($itemId = null)
    {
        RateLimitMiddleware::forAPI(Security::getRealIP());

        if (!$itemId) Response::validationError(['item_id' => ['Item id is required']]);

        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $cartModel = new Cart();
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Cart not found', 404);

        $ok = $cartModel->removeItem($cart['id'], (int)$itemId);
        if ($ok) {
            $updated = $cartModel->findById($cart['id']);
            Response::success($updated, 'Cart item removed');
        }

        Response::error('Failed to remove cart item', 500);
    }

    /**
     * مسح السلة بالكامل
     * POST /api/cart/clear
     */
    public static function clear()
    {
        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $cartModel = new Cart();
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Cart not found', 404);

        $ok = $cartModel->clearCart($cart['id']);
        if ($ok) {
            Response::success(null, 'Cart cleared');
        }

        Response::error('Failed to clear cart', 500);
    }

    /**
     * تطبيق كوبون على السلة
     * POST /api/cart/apply-coupon
     * body: coupon_code
     */
    public static function applyCoupon()
    {
        RateLimitMiddleware::forAPI(Security::getRealIP());

        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $rules = [
            'coupon_code' => 'required|string'
        ];
        $validated = Validator::make($input, $rules)->validated();

        $cartModel = new Cart();
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Cart not found', 404);

        $res = $cartModel->applyCoupon($cart['id'], $validated['coupon_code']);
        if (isset($res['success']) && $res['success']) {
            $updated = $cartModel->findById($cart['id']);
            Response::success($updated, $res['message'] ?? 'Coupon applied');
        }

        Response::error($res['message'] ?? 'Failed to apply coupon', 400);
    }

    /**
     * إزالة الكوبون
     * POST /api/cart/remove-coupon
     */
    public static function removeCoupon()
    {
        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $cartModel = new Cart();
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Cart not found', 404);

        $ok = $cartModel->removeCoupon($cart['id']);
        if ($ok) {
            $updated = $cartModel->findById($cart['id']);
            Response::success($updated, 'Coupon removed');
        }

        Response::error('Failed to remove coupon', 500);
    }

    /**
     * دمج سلة الضيف مع سلة المستخدم بعد تسجيل الدخول
     * POST /api/cart/merge
     * body: session_id (optional) - if not provided controller will look in cookie/header
     */
    public static function merge()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) Response::unauthorized();

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $sessionId = $input['session_id'] ?? self::getSessionId();

        if (!$sessionId) Response::validationError(['session_id' => ['Session id required to merge']]);

        $cartModel = new Cart();
        $ok = $cartModel->mergeGuestCartToUser($sessionId, $user['id']);

        if ($ok) {
            $userCart = $cartModel->getOrCreateCart($user['id'], null);
            Response::success($userCart, 'Guest cart merged into user cart');
        }

        Response::error('Failed to merge carts', 500);
    }

    /**
     * عدد العناصر في السلة
     * GET /api/cart/count
     */
    public static function count()
    {
        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $cartModel = new Cart();
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Cart not found', 404);

        $count = $cartModel->countItems($cart['id']);
        Response::success(['items_count' => $count]);
    }

    /**
     * Checkout helper: returns totals and payment placeholder
     * POST /api/cart/checkout-info
     * body: shipping_address_id, billing_address_id, shipping_method, etc.
     *
     * Note: actual order creation/payment is handled in OrderController
     */
    public static function checkoutInfo()
    {
        $user = AuthMiddleware::authenticateOptional();
        $sessionId = self::getSessionId();

        $cartModel = new Cart();
        $cart = $cartModel->getOrCreateCart($user['id'] ?? null, $sessionId);
        if (!$cart) Response::error('Cart not found', 404);

        $totals = $cartModel->getTotals($cart['id']);

        // يُمكن إضافة حسابات شحن فعلية أو جمع عناوين هنا
        Response::success([
            'cart' => $cart,
            'totals' => $totals,
            'payment_methods' => ['cash_on_delivery', 'stripe', 'paypal'] // placeholder
        ]);
    }
}

// helper to detect https for cookie secure flag
if (!function_exists('is_https')) {
    function is_https() {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
        return false;
    }
}

// Router examples (handled elsewhere):
// POST /api/cart/items => CartController::addItem();
// PUT  /api/cart/items/{id} => CartController::updateItem($id);
// DELETE /api/cart/items/{id} => CartController::removeItem($id);
// GET /api/cart => CartController::getCart();

?>