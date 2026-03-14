<?php
/**
 * frontend/public/pay.php
 * QOOQZ — Pay for an existing order (auction win or any pending-payment order)
 * Reads directly from orders+order_items — does NOT use cart.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

if (!$_isLoggedIn) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/frontend/public/pay.php'));
    exit;
}

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = (int)($ctx['tenant_id'] ?? 1);
$userId   = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);

$GLOBALS['PUB_PAGE_TITLE'] = e(t('orders.pay_now')) . ' — QOOQZ';

$pdo     = pub_get_pdo();
$orderId = (int)($_GET['order_id'] ?? 0);

// Load order
$order     = null;
$orderItems = [];
$payError   = '';
$paySuccess = false;
$entityId   = 1;
$currencyCode = 'SAR';

if ($pdo && $orderId) {
    try {
        $st = $pdo->prepare(
            "SELECT o.id, o.order_number, o.user_id, o.entity_id, o.status,
                    o.payment_status, o.subtotal, o.tax_amount, o.shipping_cost,
                    o.discount_amount, o.total_amount, o.grand_total, o.currency_code,
                    o.auction_id, o.created_at,
                    (SELECT at2.title FROM auction_translations at2
                     WHERE at2.auction_id = o.auction_id AND at2.language_code = ? LIMIT 1) AS auction_title
               FROM orders o
              WHERE o.id = ? AND o.user_id = ?
              LIMIT 1"
        );
        $st->execute([$lang, $orderId, $userId]);
        $order = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

if (!$order) {
    http_response_code(404);
    include dirname(__DIR__) . '/partials/header.php';
    echo '<main class="pub-main"><div class="pub-container" style="padding:60px 20px;text-align:center">';
    echo '<h2>🚫 ' . e(t('products.not_found')) . '</h2>';
    echo '<a href="/frontend/public/orders.php" class="pub-btn pub-btn--outline" style="margin-top:16px">' . e(t('orders.page_title')) . '</a>';
    echo '</div></main>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

// Redirect if already paid
if (in_array($order['payment_status'], ['paid', 'refunded'])) {
    header('Location: /frontend/public/orders.php?view=' . $orderId);
    exit;
}

$entityId     = (int)($order['entity_id'] ?: 1);
$currencyCode = $order['currency_code'] ?: 'SAR';

// Load order items
if ($pdo) {
    try {
        $is = $pdo->prepare(
            "SELECT oi.product_id, oi.product_name, oi.sku, oi.quantity,
                    oi.unit_price, oi.subtotal, oi.total,
                    (SELECT i.url FROM images i WHERE i.owner_id = oi.product_id ORDER BY i.id ASC LIMIT 1) AS image_url
               FROM order_items oi WHERE oi.order_id = ? ORDER BY oi.id ASC"
        );
        $is->execute([$orderId]);
        $orderItems = $is->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// Load payment methods
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
               FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 10"
        );
        $ps->execute([]);
        $entityPMs = $ps->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}
// Always have COD fallback
if (empty($entityPMs)) {
    $entityPMs = [['code' => 'cod', 'name' => t('checkout.cash_on_delivery'), 'icon' => '']];
}

/* -------------------------------------------------------
 * Handle POST — confirm payment
 * ----------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $pmCode = trim($_POST['payment_method_code'] ?? 'cod');
    $notes  = trim($_POST['payment_notes'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Insert payment row
        $payNum = 'PAY-' . $tenantId . '-' . $orderId . '-' . time();
        $pst = $pdo->prepare(
            "INSERT INTO payments
               (entity_id, payment_number, order_id, user_id, payment_method,
                amount, currency_code, status, payment_type, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'full', ?)"
        );
        $pst->execute([
            $entityId, $payNum, $orderId, $userId, $pmCode,
            (float)$order['grand_total'], $currencyCode,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        // 2. Update order payment_status
        $newPayStatus = ($pmCode === 'cod' || $pmCode === 'cash') ? 'paid' : 'pending';
        $ost = $pdo->prepare(
            "UPDATE orders SET payment_status = ?, confirmed_at = NOW(), updated_at = NOW()
              WHERE id = ? AND user_id = ?"
        );
        $ost->execute([$newPayStatus, $orderId, $userId]);

        // 3. Log status history
        try {
            $hst = $pdo->prepare(
                "INSERT INTO order_status_history (order_id, status, notes, created_by)
                 VALUES (?, 'confirmed', ?, ?)"
            );
            $hst->execute([$orderId, 'Payment ' . $pmCode . ($notes ? ': ' . $notes : ''), $userId]);
        } catch (Throwable) {}

        $pdo->commit();
        $paySuccess = true;
        header('Location: /frontend/public/orders.php?view=' . $orderId . '&paid=1');
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $payError = t('checkout.error_try_again') . ' (' . $e->getMessage() . ')';
    }
}

include dirname(__DIR__) . '/partials/header.php';
$isRtl = ($dir === 'rtl');
?>

<style>
.pay-wrap { max-width: 960px; margin: 40px auto; padding: 0 16px; display: grid; grid-template-columns: 1fr 380px; gap: 24px; }
@media (max-width: 700px) { .pay-wrap { grid-template-columns: 1fr; } }
.pay-card { background: var(--pub-surface, #1a1a2e); border: 1px solid rgba(255,255,255,.1); border-radius: 12px; padding: 24px; }
.pay-title { font-size: 1.15rem; font-weight: 700; margin-bottom: 18px; color: var(--pub-text, #f0f0f0); }
.pay-order-num { font-size: .85rem; color: rgba(255,255,255,.5); margin-bottom: 4px; }
.pay-items-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
.pay-items-table th { text-align: <?= $isRtl ? 'right' : 'left' ?>; padding: 8px; border-bottom: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.5); font-weight: 500; }
.pay-items-table td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,.05); vertical-align: middle; }
.pay-item-img { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; }
.pay-totals { margin-top: 16px; }
.pay-totals tr td { padding: 5px 8px; font-size: .9rem; }
.pay-totals tr td:last-child { text-align: <?= $isRtl ? 'left' : 'right' ?>; font-weight: 600; }
.pay-totals .pay-grand td { font-size: 1.1rem; color: var(--pub-primary, #10B981); border-top: 1px solid rgba(255,255,255,.1); padding-top: 12px; }
.pay-pm-label { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid rgba(255,255,255,.15); border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: border-color .2s; }
.pay-pm-label:has(input:checked), .pay-pm-label:hover { border-color: var(--pub-primary, #10B981); }
.pay-pm-label input { accent-color: var(--pub-primary, #10B981); width: 16px; height: 16px; }
.pay-btn { width: 100%; padding: 14px; font-size: 1rem; font-weight: 700; background: var(--pub-primary, #10B981); color: #000; border: none; border-radius: 10px; cursor: pointer; margin-top: 8px; }
.pay-btn:hover { opacity: .85; }
.pay-err { background: rgba(239,68,68,.15); color: #f87171; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .9rem; }
.pay-auction-badge { display: inline-block; background: rgba(217,119,6,.2); color: #f59e0b; border-radius: 20px; padding: 3px 10px; font-size: .78rem; margin-bottom: 12px; }
</style>

<main class="pub-main">
<div class="pub-container">
<div style="margin:24px 0 16px">
  <a href="/frontend/public/orders.php?view=<?= $orderId ?>" style="color:var(--pub-primary,#10B981);text-decoration:none">← <?= e(t('orders.back')) ?></a>
</div>

<?php if ($payError): ?>
<div class="pay-err">⚠️ <?= e($payError) ?></div>
<?php endif; ?>

<form method="post" class="pay-wrap">
  <!-- Left: Order summary -->
  <div class="pay-card">
    <div class="pay-order-num"><?= e(t('orders.order_number') ?: 'Order') ?>: <strong>#<?= e($order['order_number']) ?></strong></div>
    <?php if ($order['auction_id']): ?>
    <span class="pay-auction-badge">🔨 <?= e($order['auction_title'] ?: 'Auction') ?></span>
    <?php endif; ?>
    <div class="pay-title">📋 <?= e(t('orders.items') ?: 'Order Items') ?></div>

    <?php if ($orderItems): ?>
    <table class="pay-items-table">
      <thead><tr>
        <th></th>
        <th><?= e(t('products.name') ?: 'Product') ?></th>
        <th><?= e(t('cart.qty') ?: 'Qty') ?></th>
        <th><?= e(t('orders.total') ?: 'Total') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($orderItems as $oi): ?>
      <tr>
        <td><?php if ($oi['image_url']): ?><img src="<?= e($oi['image_url']) ?>" class="pay-item-img" alt=""><?php endif; ?></td>
        <td>
          <div style="font-weight:600"><?= e($oi['product_name']) ?></div>
          <?php if ($oi['sku']): ?><div style="font-size:.77rem;color:rgba(255,255,255,.4)"><?= e($oi['sku']) ?></div><?php endif; ?>
        </td>
        <td><?= (int)$oi['quantity'] ?></td>
        <td><?= number_format((float)$oi['total'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <table class="pay-totals">
      <tr><td><?= e(t('cart.subtotal') ?: 'Subtotal') ?></td><td><?= number_format((float)$order['subtotal'], 2) ?> <?= e($currencyCode) ?></td></tr>
      <?php if ((float)$order['tax_amount'] > 0): ?>
      <tr><td><?= e(t('checkout.tax') ?: 'Tax') ?></td><td><?= number_format((float)$order['tax_amount'], 2) ?> <?= e($currencyCode) ?></td></tr>
      <?php endif; ?>
      <?php if ((float)$order['shipping_cost'] > 0): ?>
      <tr><td><?= e(t('cart.shipping') ?: 'Shipping') ?></td><td><?= number_format((float)$order['shipping_cost'], 2) ?> <?= e($currencyCode) ?></td></tr>
      <?php endif; ?>
      <?php if ((float)$order['discount_amount'] > 0): ?>
      <tr><td><?= e(t('cart.discount') ?: 'Discount') ?></td><td>-<?= number_format((float)$order['discount_amount'], 2) ?> <?= e($currencyCode) ?></td></tr>
      <?php endif; ?>
      <tr class="pay-grand"><td><strong><?= e(t('cart.total') ?: 'Total') ?></strong></td><td><strong><?= number_format((float)$order['grand_total'], 2) ?> <?= e($currencyCode) ?></strong></td></tr>
    </table>
  </div>

  <!-- Right: Payment method -->
  <div>
    <div class="pay-card">
      <div class="pay-title">💳 <?= e(t('checkout.payment_method') ?: 'Payment Method') ?></div>
      <?php foreach ($entityPMs as $i => $pm): ?>
      <label class="pay-pm-label">
        <input type="radio" name="payment_method_code" value="<?= e($pm['code']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
        <?php if ($pm['icon']): ?><img src="<?= e($pm['icon']) ?>" alt="" style="height:22px;width:auto"><?php else: ?><span style="font-size:1.1rem">💵</span><?php endif; ?>
        <span><?= e($pm['name']) ?></span>
      </label>
      <?php endforeach; ?>

      <div style="margin-top:12px">
        <label style="font-size:.85rem;color:rgba(255,255,255,.5);display:block;margin-bottom:6px"><?= e(t('orders.notes') ?: 'Notes (optional)') ?></label>
        <textarea name="payment_notes" rows="2" style="width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:10px;color:var(--pub-text,#f0f0f0);font-size:.9rem;resize:vertical"></textarea>
      </div>

      <button type="submit" class="pay-btn">
        💳 <?= e(t('orders.pay_now') ?: 'Pay Now') ?> — <?= number_format((float)$order['grand_total'], 2) ?> <?= e($currencyCode) ?>
      </button>
      <div style="text-align:center;margin-top:10px;font-size:.78rem;color:rgba(255,255,255,.35)">🔒 <?= e(t('checkout.secure') ?: 'Secure transaction') ?></div>
    </div>
  </div>
</form>
</div>
</main>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>