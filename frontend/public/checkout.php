<?php
/**
 * frontend/public/checkout.php
 * QOOQZ — Checkout / Order Placement
 *
 * Reads cart from DB (user-specific via session user_id).
 * Creates order + order_items directly via PDO (no curl loopback).
 * Marks cart as 'converted' after successful order.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];
$user     = $ctx['user'] ?? null;
$userId   = (int)($user['id'] ?? 0);

// Require login
if (!$userId) {
    header('Location: /frontend/login.php?redirect=' . urlencode('/frontend/public/checkout.php'));
    exit;
}

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('checkout.title') . ' — QOOQZ';

/* -------------------------------------------------------
 * Load user's active cart from DB
 * ----------------------------------------------------- */
$pdo       = pub_get_pdo();
$cartItems = [];
$cartTotal = 0.0;
$cartId    = 0;
$entityId  = 1; // default entity

if ($pdo) {
    try {
        // Get user's active cart
        $cs = $pdo->prepare(
            "SELECT id, entity_id FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1"
        );
        $cs->execute([$userId]);
        $cartRow = $cs->fetch(PDO::FETCH_ASSOC);
        if ($cartRow) {
            $cartId   = (int)$cartRow['id'];
            $entityId = (int)($cartRow['entity_id'] ?: 1);
            // Get cart items
            $is = $pdo->prepare(
                "SELECT ci.id, ci.product_id, ci.product_name, ci.sku,
                        ci.quantity, ci.unit_price, ci.subtotal, ci.entity_id,
                        (SELECT i.url FROM images i WHERE i.owner_id = ci.product_id AND i.image_type_id = 2
                         ORDER BY i.sort_order ASC, i.id ASC LIMIT 1) AS image_url
                   FROM cart_items ci WHERE ci.cart_id = ? ORDER BY ci.added_at ASC"
            );
            $is->execute([$cartId]);
            $cartItems = $is->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cartItems as $ci) {
                $cartTotal += (float)$ci['unit_price'] * (int)$ci['quantity'];
            }
            $cartTotal = round($cartTotal, 2);
            if ($cartItems && $entityId <= 1) {
                $entityId = (int)($cartItems[0]['entity_id'] ?: 1);
            }
        }
    } catch (Throwable) {}
}

/* -------------------------------------------------------
 * Load payment methods
 * ----------------------------------------------------- */
$entityPMs = [];
if ($pdo) {
    try {
        $ps = $pdo->prepare(
            "SELECT pm.method_key AS code, pm.method_name AS name, pm.icon_url AS icon
               FROM entity_payment_methods epm
               JOIN payment_methods pm ON pm.id = epm.payment_method_id
              WHERE epm.entity_id = ? AND epm.is_active = 1
              ORDER BY pm.sort_order ASC"
        );
        $ps->execute([$entityId]);
        $entityPMs = $ps->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}
if (empty($entityPMs) && $pdo) {
    try {
        $ps = $pdo->prepare(
            "SELECT method_key AS code, method_name AS name, icon_url AS icon
               FROM payment_methods WHERE tenant_id = ? AND is_active = 1
               ORDER BY sort_order ASC LIMIT 10"
        );
        $ps->execute([$tenantId]);
        $entityPMs = $ps->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

/* -------------------------------------------------------
 * Handle form submission — direct PDO (no curl)
 * ----------------------------------------------------- */
$checkoutError   = '';
$checkoutSuccess = false;
$orderNumber     = '';
$orderId         = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $pmCode   = trim($_POST['payment_method_code'] ?? '');
    $custName = trim($_POST['customer_name']  ?? '');
    $custPhone= trim($_POST['customer_phone'] ?? '');
    $address  = trim($_POST['delivery_address'] ?? '');
    $notes    = trim($_POST['order_notes'] ?? '');

    // Allow JS-submitted items (fallback if DB cart is empty — e.g. guest localStorage)
    $jsItems = [];
    if (empty($cartItems)) {
        $jsJson  = $_POST['cart_items_json'] ?? '[]';
        $jsItems = json_decode($jsJson, true);
        if (!is_array($jsItems)) $jsItems = [];
        foreach ($jsItems as $ji) {
            $cartTotal += (float)($ji['price'] ?? 0) * max(1, (int)($ji['qty'] ?? 1));
        }
        $cartTotal = round($cartTotal, 2);
    }

    $allItems = !empty($cartItems) ? $cartItems : $jsItems;

    if (!$custName || !$custPhone) {
        $checkoutError = t('checkout.error_fields_required');
    } elseif (empty($allItems)) {
        $checkoutError = t('cart.empty');
    } else {
        // Resolve entity if still default
        if ($entityId <= 1) {
            try {
                $eRow = $pdo->query(
                    "SELECT id FROM entities WHERE tenant_id = {$tenantId} AND status='approved' ORDER BY id ASC LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                if ($eRow) $entityId = (int)$eRow['id'];
            } catch (Throwable) {}
        }

        // Generate unique order number
        $orderNumber = 'ORD-' . $tenantId . '-' . time() . '-' . rand(100, 999);
        try {
            $ck = $pdo->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
            $ck->execute([$orderNumber]);
            if ($ck->fetch()) $orderNumber .= '-' . rand(10, 99);
        } catch (Throwable) {}

        try {
            $pdo->beginTransaction();

            // 1. Insert order
            $oSt = $pdo->prepare(
                "INSERT INTO orders
                   (tenant_id, entity_id, order_number, user_id, cart_id, status, payment_status,
                    subtotal, tax_amount, shipping_cost, discount_amount,
                    total_amount, grand_total, currency_code, customer_notes, ip_address)
                 VALUES (?, ?, ?, ?, ?, 'pending', 'pending',
                         ?, 0, 0, 0, ?, ?, 'SAR', ?, ?)"
            );
            $oSt->execute([
                $tenantId, $entityId, $orderNumber, $userId,
                $cartId ?: null,
                $cartTotal, $cartTotal, $cartTotal,
                $notes,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert order items
            $iSt = $pdo->prepare(
                "INSERT INTO order_items
                   (tenant_id, order_id, entity_id, product_id, product_name, sku,
                    quantity, unit_price, subtotal, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!empty($cartItems)) {
                // From DB cart
                foreach ($cartItems as $ci) {
                    $pId   = (int)$ci['product_id'];
                    $pName = (string)$ci['product_name'];
                    $pSku  = (string)($ci['sku'] ?? '');
                    $qty   = max(1, (int)$ci['quantity']);
                    $price = (float)$ci['unit_price'];
                    if (!$pId || !$pName) continue;
                    $iSt->execute([
                        $tenantId, $orderId, $entityId,
                        $pId, $pName, $pSku, $qty, $price,
                        round($price * $qty, 2), round($price * $qty, 2),
                    ]);
                }
                // Mark cart as converted
                if ($cartId) {
                    $pdo->prepare(
                        "UPDATE carts SET status = 'converted', converted_to_order_id = ?, updated_at = NOW()
                           WHERE id = ?"
                    )->execute([$orderId, $cartId]);
                }
            } else {
                // From localStorage JSON fallback
                foreach ($jsItems as $ji) {
                    $pId   = (int)($ji['id'] ?? 0);
                    $pName = (string)($ji['name'] ?? '');
                    $pSku  = (string)($ji['sku']  ?? '');
                    $qty   = max(1, (int)($ji['qty'] ?? 1));
                    $price = (float)($ji['price'] ?? 0);
                    if (!$pId || !$pName) continue;
                    $iSt->execute([
                        $tenantId, $orderId, $entityId,
                        $pId, $pName, $pSku, $qty, $price,
                        round($price * $qty, 2), round($price * $qty, 2),
                    ]);
                }
            }

            $pdo->commit();
            $checkoutSuccess = true;

            // 3. Insert payment record (pending — user hasn't paid yet)
            try {
                $pmNum = 'PAY-' . $tenantId . '-' . $orderId . '-' . time();
                $pdo->prepare(
                    "INSERT INTO payments
                       (entity_id, payment_number, order_id, user_id, payment_method,
                        amount, currency_code, status, payment_type, ip_address, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'SAR', 'pending', 'order', ?, NOW(), NOW())"
                )->execute([
                    $entityId, $pmNum, $orderId, $userId,
                    $pmCode ?: 'cod',
                    $cartTotal,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Throwable) {} // Non-fatal — order already committed

        } catch (Throwable $ex) {
            try { $pdo->rollBack(); } catch (Throwable) {}
            $checkoutError = t('common.error') . ': ' . $ex->getMessage();
        }
    }
}

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <a href="/frontend/public/cart.php">🛒 <?= e(t('cart.title')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span>💳 <?= e(t('checkout.title')) ?></span>
    </nav>

    <?php if ($checkoutSuccess): ?>
    <!-- ✅ Success -->
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:4rem;margin-bottom:16px;">✅</div>
        <h1 style="font-size:1.6rem;margin:0 0 10px;color:var(--pub-text);">
            <?= e(t('checkout.success_title')) ?>
        </h1>
        <p style="color:var(--pub-muted);margin:0 0 24px;">
            <?= e(t('checkout.success_msg')) ?>
        </p>
        <?php if ($orderNumber): ?>
        <p style="font-size:0.9rem;color:var(--pub-muted);">
            <?= e(t('checkout.order_number')) ?>
            <strong style="color:var(--pub-primary);">#<?= e($orderNumber) ?></strong>
        </p>
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
            <a href="/frontend/public/index.php" class="pub-btn pub-btn--primary">
                🏠 <?= e(t('common.home')) ?>
            </a>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost">
                🛍️ <?= e(t('hero.browse_products')) ?>
            </a>
        </div>
    </div>
    <script>try { localStorage.removeItem('pub_cart'); } catch(e){}</script>

    <?php else: ?>
    <!-- Checkout form -->
    <h1 style="font-size:1.4rem;margin:0 0 24px;">💳 <?= e(t('checkout.title')) ?></h1>

    <?php if ($checkoutError): ?>
    <div style="background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,0.3);color:#e74c3c;
                padding:12px 16px;border-radius:var(--pub-radius);margin-bottom:20px;">
        ⚠️ <?= e($checkoutError) ?>
    </div>
    <?php endif; ?>

    <form method="post" id="checkoutForm" class="pub-checkout-layout">
        <!-- Hidden: JS-provided items if DB cart empty -->
        <input type="hidden" name="cart_items_json" id="cartItemsJson" value="[]">

        <!-- Left: Customer Info + Payment -->
        <div>
            <div class="pub-checkout-card">
                <h2 class="pub-checkout-card-title">👤 <?= e(t('checkout.customer_info')) ?></h2>
                <div class="pub-form-grid">
                    <div class="pub-form-field">
                        <label class="pub-form-label"><?= e(t('checkout.name')) ?> *</label>
                        <input type="text" name="customer_name" class="pub-form-input" required
                               value="<?= e($user['name'] ?? $user['username'] ?? '') ?>"
                               placeholder="<?= e(t('checkout.name')) ?>">
                    </div>
                    <div class="pub-form-field">
                        <label class="pub-form-label">📞 <?= e(t('checkout.phone')) ?> *</label>
                        <input type="tel" name="customer_phone" class="pub-form-input" required
                               placeholder="<?= e(t('checkout.phone')) ?>">
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">📍 <?= e(t('checkout.address')) ?></label>
                        <textarea name="delivery_address" class="pub-form-input" rows="3"
                                  style="resize:vertical;"
                                  placeholder="<?= e(t('checkout.address')) ?>"></textarea>
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">📝 <?= e(t('checkout.notes')) ?></label>
                        <textarea name="order_notes" class="pub-form-input" rows="2"
                                  style="resize:vertical;"
                                  placeholder="<?= e(t('checkout.notes')) ?>"></textarea>
                    </div>
                </div>
            </div>

            <div class="pub-checkout-card" style="margin-top:16px;">
                <h2 class="pub-checkout-card-title">💳 <?= e(t('checkout.payment')) ?></h2>
                <div class="pub-pm-grid">
                    <?php if (empty($entityPMs)): ?>
                    <p style="padding:16px;color:var(--pub-muted);font-size:0.88rem;">
                        <?= e(t('checkout.no_payment_methods')) ?>
                    </p>
                    <!-- Fallback cash option -->
                    <label class="pub-pm-option">
                        <input type="radio" name="payment_method_code" value="cash" checked>
                        <span class="pub-pm-label">
                            <span class="pub-pm-icon">💵</span>
                            <span class="pub-pm-name"><?= e(t('checkout.cash_on_delivery')) ?></span>
                        </span>
                    </label>
                    <?php else: foreach ($entityPMs as $i => $pm): ?>
                    <?php
                        $pmCode = $pm['code'] ?? 'pm_' . $i;
                        $pmName = $pm['name'] ?? $pmCode;
                        $pmIcon = $pm['icon'] ?: '💳';
                    ?>
                    <label class="pub-pm-option">
                        <input type="radio" name="payment_method_code"
                               value="<?= e($pmCode) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        <span class="pub-pm-label">
                            <span class="pub-pm-icon"><?= e($pmIcon) ?></span>
                            <span class="pub-pm-name"><?= e($pmName) ?></span>
                        </span>
                    </label>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Order summary -->
        <div class="pub-checkout-summary">
            <div class="pub-cart-summary-inner">
                <h2 class="pub-cart-summary-title">📋 <?= e(t('cart.order_summary')) ?></h2>

                <!-- DB cart items (server-rendered) -->
                <?php if (!empty($cartItems)): ?>
                <div id="checkoutItemsList"
                     style="margin-bottom:14px;display:grid;gap:8px;max-height:280px;overflow-y:auto;">
                    <?php foreach ($cartItems as $ci): ?>
                    <div style="display:flex;gap:10px;align-items:center;font-size:0.85rem;">
                        <?php if (!empty($ci['image_url'])): ?>
                        <img src="<?= e($ci['image_url']) ?>" alt=""
                             style="width:40px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                        <?php endif; ?>
                        <div style="flex:1;overflow:hidden;">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                                        font-weight:600;color:var(--pub-text);">
                                <?= e($ci['product_name']) ?>
                            </div>
                            <div style="color:var(--pub-muted);font-size:0.8rem;">
                                ×<?= (int)$ci['quantity'] ?>
                            </div>
                        </div>
                        <strong style="flex-shrink:0;color:var(--pub-primary);">
                            <?= number_format((float)$ci['unit_price'] * (int)$ci['quantity'], 2) ?>
                        </strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <hr style="border:none;border-top:1px solid var(--pub-border);margin:10px 0;">
                <?php else: ?>
                <!-- No DB items: JS will render from localStorage -->
                <div id="checkoutItemsList" style="margin-bottom:14px;display:grid;gap:8px;min-height:60px;"></div>
                <div id="checkoutEmptyMsg" style="display:none;color:var(--pub-muted);font-size:0.88rem;padding:8px 0;">
                    <?= e(t('cart.empty')) ?>
                </div>
                <?php endif; ?>

                <div class="pub-summary-row">
                    <span><?= e(t('cart.subtotal')) ?></span>
                    <strong id="checkoutSubtotal">
                        <?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?>
                    </strong>
                </div>
                <div class="pub-summary-row" style="color:var(--pub-muted);font-size:0.84rem;">
                    <span><?= e(t('cart.shipping')) ?></span>
                    <span>—</span>
                </div>
                <div class="pub-summary-row pub-summary-total">
                    <span><?= e(t('cart.total')) ?></span>
                    <strong id="checkoutTotal">
                        <?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?>
                    </strong>
                </div>

                <button type="submit" id="checkoutSubmitBtn"
                        class="pub-btn pub-btn--primary"
                        style="width:100%;margin-top:16px;font-size:1rem;padding:13px;"
                        <?= empty($cartItems) ? '' /* JS will disable if localStorage also empty */ : '' ?>>
                    ✅ <?= e(t('checkout.place_order')) ?>
                </button>
                <p style="font-size:0.75rem;text-align:center;color:var(--pub-muted);margin-top:10px;">
                    🔒 <?= e(t('checkout.secure_transaction')) ?>
                </p>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<style>
.pub-checkout-layout { display:grid; gap:24px; }
@media(min-width:900px){ .pub-checkout-layout { grid-template-columns:1fr 360px; align-items:start; } }
.pub-checkout-card { background:var(--pub-bg);border:1px solid var(--pub-border);border-radius:var(--pub-radius);overflow:hidden; }
.pub-checkout-card-title { font-size:1rem;font-weight:700;margin:0;padding:12px 16px;border-bottom:1px solid var(--pub-border);color:var(--pub-text); }
.pub-form-grid { display:grid;gap:14px;padding:16px; }
@media(min-width:600px){ .pub-form-grid { grid-template-columns:1fr 1fr; } }
.pub-form-field { display:grid; }
.pub-form-label { font-size:0.82rem;font-weight:600;color:var(--pub-muted);margin-bottom:4px; }
.pub-form-input { width:100%;padding:9px 12px;border:1px solid var(--pub-border);border-radius:var(--pub-radius-sm);background:var(--pub-bg);color:var(--pub-text);font-size:0.9rem;transition:border-color var(--pub-transition);font-family:inherit;box-sizing:border-box; }
.pub-form-input:focus { outline:none;border-color:var(--pub-primary);box-shadow:0 0 0 2px rgba(3,135,78,0.12); }
.pub-pm-grid { display:grid;gap:10px;padding:16px; }
@media(min-width:500px){ .pub-pm-grid { grid-template-columns:1fr 1fr; } }
.pub-pm-option { border:2px solid var(--pub-border);border-radius:var(--pub-radius);padding:12px;cursor:pointer;transition:border-color var(--pub-transition),background var(--pub-transition);display:block; }
.pub-pm-option:has(input:checked) { border-color:var(--pub-primary);background:rgba(3,135,78,0.06); }
.pub-pm-option input[type=radio] { position:absolute;opacity:0;width:0;height:0; }
.pub-pm-label { display:flex;align-items:center;gap:10px; }
.pub-pm-icon { font-size:1.4rem; }
.pub-pm-name { font-size:0.88rem;font-weight:600;color:var(--pub-text); }
.pub-checkout-summary { position:sticky;top:calc(var(--pub-header-h,64px)+16px); }
</style>

<?php
// If no DB cart items, JS enriches the summary from localStorage
$hasDbCart = !empty($cartItems);
?>
<script>
(function () {
    'use strict';
    var HAS_DB_CART = <?= $hasDbCart ? 'true' : 'false' ?>;
    var CURRENCY    = <?= json_encode(t('common.currency')) ?>;

    function getLocalCart() {
        try { return JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch(e) { return []; }
    }

    function formatPrice(n) { return parseFloat(n||0).toFixed(2); }

    function renderLocalSummary() {
        if (HAS_DB_CART) return; // DB cart is shown server-side
        var cart    = getLocalCart();
        var list    = document.getElementById('checkoutItemsList');
        var empty   = document.getElementById('checkoutEmptyMsg');
        var subEl   = document.getElementById('checkoutSubtotal');
        var totEl   = document.getElementById('checkoutTotal');
        var submit  = document.getElementById('checkoutSubmitBtn');
        var hidden  = document.getElementById('cartItemsJson');
        if (!list) return;

        if (!cart.length) {
            if (empty)  empty.style.display  = '';
            if (submit) submit.disabled = true;
            return;
        }
        if (empty)  empty.style.display = 'none';
        if (submit) submit.disabled = false;

        var subtotal = 0;
        var html = '';
        cart.forEach(function(item) {
            var price = parseFloat(item.price||0), qty = parseInt(item.qty||1,10);
            subtotal += price * qty;
            html += '<div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;color:var(--pub-text);">'
                  + '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-inline-end:8px;">'
                  + (item.name||'') + ' <span style="color:var(--pub-muted);">×' + qty + '</span></span>'
                  + '<strong>' + formatPrice(price*qty) + ' ' + CURRENCY + '</strong></div>';
        });
        list.innerHTML = html;
        if (subEl) subEl.textContent = formatPrice(subtotal) + ' ' + CURRENCY;
        if (totEl) totEl.textContent = formatPrice(subtotal) + ' ' + CURRENCY;
        if (hidden) hidden.value = JSON.stringify(cart);
    }

    // Pre-submit: always re-serialize cart (if localStorage based)
    var form = document.getElementById('checkoutForm');
    if (form) {
        form.addEventListener('submit', function() {
            if (!HAS_DB_CART) {
                var hidden = document.getElementById('cartItemsJson');
                if (hidden) hidden.value = JSON.stringify(getLocalCart());
            }
        });
    }

    document.addEventListener('DOMContentLoaded', renderLocalSummary);
}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>