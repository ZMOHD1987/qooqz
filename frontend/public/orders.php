<?php
require_once dirname(__DIR__) . '/includes/public_context.php';

// Require login
if (!$_isLoggedIn) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/frontend/public/orders.php'));
    exit;
}

$GLOBALS['PUB_PAGE_TITLE'] = e(t('orders.page_title')) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
$pdo    = pub_get_pdo();

// --- Load orders summary + list ---
$orders    = [];
$summary   = ['total' => 0, 'pending' => 0, 'processing' => 0, 'delivered' => 0, 'cancelled' => 0];
$filter    = in_array($_GET['status'] ?? '', ['pending','confirmed','processing','shipped','out_for_delivery','delivered','completed','cancelled','refunded','failed'])
             ? ($_GET['status'] ?? '') : '';

if ($pdo && $userId) {
    try {
        // Summary counts
        $stSum = $pdo->prepare(
            "SELECT status, COUNT(*) AS cnt FROM orders WHERE user_id = ? GROUP BY status"
        );
        $stSum->execute([$userId]);
        while ($row = $stSum->fetch(PDO::FETCH_ASSOC)) {
            $s = $row['status'];
            $summary['total'] += (int)$row['cnt'];
            if (isset($summary[$s])) $summary[$s] = (int)$row['cnt'];
            elseif (in_array($s, ['confirmed','processing','shipped','out_for_delivery'])) $summary['processing'] += (int)$row['cnt'];
            elseif (in_array($s, ['delivered','completed'])) $summary['delivered'] += (int)$row['cnt'];
        }

        // Order list
        $where  = 'WHERE o.user_id = ?';
        $params = [$userId];
        if ($filter === 'processing') {
            $where .= " AND o.status IN ('confirmed','processing','shipped','out_for_delivery')";
        } elseif ($filter) {
            $where .= ' AND o.status = ?'; $params[] = $filter;
        }

        $stOrders = $pdo->prepare(
            "SELECT o.id, o.order_number, o.status, o.payment_status, o.grand_total,
                    o.currency_code, o.created_at, o.estimated_delivery_date, o.delivered_at,
                    o.auction_id,
                    COUNT(oi.id) AS item_count
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             $where
             GROUP BY o.id
             ORDER BY o.created_at DESC
             LIMIT 50"
        );
        $stOrders->execute($params);
        $orders = $stOrders->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[orders.php] ' . $e->getMessage());
    }
}

// Inline detail: if ?view=ID requested, load that order
$viewOrder  = null;
$orderItems = [];
$statusHistory = [];
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId && $pdo && $userId) {
    try {
        $stV = $pdo->prepare(
            "SELECT o.id, o.order_number, o.status, o.payment_status, o.fulfillment_status,
                    o.subtotal, o.tax_amount, o.shipping_cost, o.discount_amount,
                    o.grand_total, o.currency_code, o.coupon_code, o.customer_notes,
                    o.created_at, o.confirmed_at, o.shipped_at, o.delivered_at,
                    o.estimated_delivery_date, o.cancellation_reason, o.auction_id
             FROM orders o
             WHERE o.id = ? AND o.user_id = ?
             LIMIT 1"
        );
        $stV->execute([$viewId, $userId]);
        $viewOrder = $stV->fetch(PDO::FETCH_ASSOC);

        if ($viewOrder) {
            $stItems = $pdo->prepare(
                "SELECT oi.product_name, oi.sku, oi.quantity, oi.unit_price, oi.total,
                        oi.currency_code, oi.is_refunded, oi.product_id,
                        (SELECT i.url FROM images i WHERE i.owner_id = oi.product_id
                         ORDER BY i.id ASC LIMIT 1) AS image_url
                 FROM order_items oi
                 WHERE oi.order_id = ?
                 ORDER BY oi.id ASC"
            );
            $stItems->execute([$viewId]);
            $orderItems = $stItems->fetchAll(PDO::FETCH_ASSOC);

            $stHist = $pdo->prepare(
                "SELECT status, notes, created_at
                 FROM order_status_history
                 WHERE order_id = ?
                 ORDER BY created_at ASC"
            );
            $stHist->execute([$viewId]);
            $statusHistory = $stHist->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('[orders.php view] ' . $e->getMessage());
    }
}

// Status label & color helper (PHP)
function orderStatusClass(string $s): string {
    return match($s) {
        'pending'          => 'status-pending',
        'confirmed'        => 'status-confirmed',
        'processing'       => 'status-processing',
        'shipped'          => 'status-shipped',
        'out_for_delivery' => 'status-otd',
        'delivered','completed' => 'status-done',
        'cancelled','failed','refunded' => 'status-bad',
        default => 'status-default',
    };
}

// Full tracking timeline steps in order
$trackingSteps = ['pending','confirmed','processing','shipped','out_for_delivery','delivered'];
$passedStatuses = $viewOrder ? array_map(fn($h) => $h['status'], $statusHistory) : [];
?>
<style>
.pub-orders-wrap{max-width:900px;margin:32px auto;padding:0 16px 60px}
.pub-orders-summary{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:28px}
@media(max-width:600px){.pub-orders-summary{grid-template-columns:repeat(2,1fr)}}
.pub-stat-card{background:var(--pub-card,#1a2332);border-radius:12px;padding:16px;text-align:center}
.pub-stat-card .stat-num{font-size:2rem;font-weight:800;color:var(--pub-primary,#10B981)}
.pub-stat-card .stat-lbl{font-size:0.78rem;color:var(--pub-muted,#94a3b8);margin-top:4px}
.pub-filter-btns{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.pub-filter-btn{padding:6px 16px;border-radius:20px;border:1px solid var(--pub-border,rgba(255,255,255,.12));background:transparent;color:inherit;cursor:pointer;font-size:0.85rem;text-decoration:none}
.pub-filter-btn.active,.pub-filter-btn:hover{background:var(--pub-primary,#10B981);border-color:var(--pub-primary,#10B981);color:#000}
.pub-order-row{background:var(--pub-card,#1a2332);border-radius:12px;padding:16px 20px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;cursor:pointer;transition:box-shadow .2s}
.pub-order-row:hover{box-shadow:0 0 0 2px var(--pub-primary,#10B981)}
.pub-order-num{font-weight:700;font-size:1rem}
.pub-order-date{font-size:0.78rem;color:var(--pub-muted,#94a3b8);margin-top:2px}
.pub-order-total{font-size:1.1rem;font-weight:700;color:var(--pub-accent,#F59E0B)}
.pub-order-badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:600}
.status-pending{background:rgba(245,158,11,.18);color:#F59E0B}
.status-confirmed{background:rgba(59,130,246,.18);color:#60a5fa}
.status-processing{background:rgba(139,92,246,.18);color:#a78bfa}
.status-shipped{background:rgba(16,185,129,.18);color:#34d399}
.status-otd{background:rgba(6,182,212,.18);color:#22d3ee}
.status-done{background:rgba(16,185,129,.3);color:#10B981}
.status-bad{background:rgba(239,68,68,.18);color:#f87171}
.status-default{background:rgba(148,163,184,.18);color:#94a3b8}
/* Detail panel */
.pub-order-detail{background:var(--pub-card,#1a2332);border-radius:12px;padding:24px;margin-bottom:24px}
.pub-order-detail h2{font-size:1.2rem;font-weight:700;margin:0 0 20px}
.pub-items-table{width:100%;border-collapse:collapse;margin-bottom:20px}
.pub-items-table th{font-size:0.78rem;color:var(--pub-muted,#94a3b8);padding:6px 8px;text-align:start;font-weight:500}
.pub-items-table td{padding:10px 8px;border-top:1px solid rgba(255,255,255,.06);vertical-align:middle}
.pub-items-table .item-img{width:48px;height:48px;object-fit:cover;border-radius:8px;margin-inline-end:10px}
/* Tracking timeline */
.pub-track-wrap{display:flex;align-items:flex-start;justify-content:space-between;gap:0;margin:24px 0 8px;position:relative}
.pub-track-wrap::before{content:'';position:absolute;top:24px;inset-inline-start:0;inset-inline-end:0;height:2px;background:rgba(255,255,255,.1);z-index:0}
.pub-track-step{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;position:relative;z-index:1;text-align:center;min-width:0}
.pub-track-dot{width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.08);border:2px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:all .3s}
.pub-track-step.done .pub-track-dot{background:var(--pub-primary,#10B981);border-color:var(--pub-primary,#10B981)}
.pub-track-step.current .pub-track-dot{background:var(--pub-accent,#F59E0B);border-color:var(--pub-accent,#F59E0B);box-shadow:0 0 12px rgba(245,158,11,.5)}
.pub-track-lbl{font-size:0.7rem;color:var(--pub-muted,#94a3b8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70px}
.pub-track-step.done .pub-track-lbl,.pub-track-step.current .pub-track-lbl{color:inherit}
.pub-track-line{flex:1;height:2px;background:rgba(255,255,255,.1);align-self:center;margin-top:-22px}
.pub-track-step.done ~ .pub-track-line{background:var(--pub-primary,#10B981)}
.pub-detail-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:16px;color:var(--pub-muted,#94a3b8);text-decoration:none;font-size:0.9rem}
.pub-detail-back:hover{color:inherit}
.pub-totals-box{background:rgba(255,255,255,.04);border-radius:10px;padding:16px;max-width:320px;margin-inline-start:auto}
.pub-totals-row{display:flex;justify-content:space-between;padding:4px 0;font-size:0.9rem}
.pub-totals-row.grand{border-top:1px solid rgba(255,255,255,.12);margin-top:6px;padding-top:8px;font-weight:700;font-size:1rem;color:var(--pub-accent,#F59E0B)}
.pub-hist-list{list-style:none;padding:0;margin:0}
.pub-hist-list li{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:0.85rem}
.pub-hist-list .hist-dot{width:10px;height:10px;border-radius:50%;background:var(--pub-primary,#10B981);flex-shrink:0;margin-top:4px}
.pub-hist-notes{color:var(--pub-muted,#94a3b8);font-size:0.8rem;margin-top:2px}
</style>

<div class="pub-orders-wrap">

<?php if ($viewOrder): ?>
    <!-- ========= ORDER DETAIL + TRACKING VIEW ========= -->
    <a href="?<?= $filter ? 'status='.e($filter) : '' ?>" class="pub-detail-back">
        ← <?= e(t('orders.back_to_orders')) ?>
    </a>

    <div class="pub-order-detail">
        <h2>
            <?= e(t('orders.order_number')) ?>: <?= e($viewOrder['order_number']) ?>
            <?php if (!empty($viewOrder['auction_id'])): ?>
                <span style="background:#f59e0b;color:#000;font-size:0.7rem;padding:2px 8px;border-radius:4px;margin-inline-start:8px">🔨 <?= e(t('nav.auctions')) ?> #<?= (int)$viewOrder['auction_id'] ?></span>
            <?php endif; ?>
            <span class="pub-order-badge <?= orderStatusClass($viewOrder['status']) ?>" style="margin-inline-start:10px">
                <?= e(t('orders.status_' . $viewOrder['status'])) ?>
            </span>
            <?php if (in_array($viewOrder['payment_status'], ['pending','failed']) && !in_array($viewOrder['status'], ['cancelled','refunded','failed'])): ?>
                <a href="/frontend/public/pay.php?order_id=<?= (int)$viewOrder['id'] ?>"
                   style="background:var(--pub-primary,#10B981);color:#000;font-size:0.78rem;padding:5px 14px;border-radius:20px;text-decoration:none;font-weight:700;margin-inline-start:10px;display:inline-block">
                    💳 <?= e(t('orders.pay_now')) ?>
                </a>
            <?php endif; ?>
        </h2>
        <div style="font-size:0.85rem;color:var(--pub-muted,#94a3b8);margin-bottom:20px">
            <?= e(t('orders.placed_on')) ?>: <?= date('Y-m-d H:i', strtotime($viewOrder['created_at'])) ?>
            <?php if ($viewOrder['estimated_delivery_date']): ?>
                &nbsp;|&nbsp; <?= e(t('orders.estimated_delivery')) ?>: <?= e($viewOrder['estimated_delivery_date']) ?>
            <?php endif; ?>
        </div>

        <!-- Tracking Timeline -->
        <?php
        $cancelledOrFailed = in_array($viewOrder['status'], ['cancelled','refunded','failed']);
        if (!$cancelledOrFailed):
            $currentIdx = array_search($viewOrder['status'], $trackingSteps);
            $stepIcons  = ['⏳','✅','⚙️','📦','🚚','🎉'];
        ?>
        <div style="margin-bottom:24px">
            <div style="font-weight:600;margin-bottom:12px"><?= e(t('orders.track_title')) ?></div>
            <div class="pub-track-wrap">
                <?php foreach ($trackingSteps as $si => $step):
                    $isDone    = in_array($step, $passedStatuses) || ($currentIdx !== false && $si < $currentIdx);
                    $isCurrent = $viewOrder['status'] === $step;
                    $cls = $isDone ? 'done' : ($isCurrent ? 'current' : '');
                    $stepHistEntry = null;
                    foreach ($statusHistory as $h) { if ($h['status'] === $step) { $stepHistEntry = $h; break; } }
                ?>
                    <div class="pub-track-step <?= $cls ?>">
                        <div class="pub-track-dot"><?= $stepIcons[$si] ?></div>
                        <div class="pub-track-lbl"><?= e(t('orders.status_' . $step)) ?></div>
                        <?php if ($stepHistEntry): ?>
                            <div style="font-size:0.65rem;color:var(--pub-muted,#94a3b8)"><?= date('m/d H:i', strtotime($stepHistEntry['created_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($si < count($trackingSteps) - 1): ?>
                        <div style="flex:1;height:2px;background:<?= ($isDone || $isCurrent) ? 'var(--pub-primary,#10B981)' : 'rgba(255,255,255,.1)' ?>;align-self:center;margin-bottom:28px"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif ($cancelledOrFailed): ?>
        <div style="background:rgba(239,68,68,.15);border-radius:10px;padding:14px 18px;margin-bottom:20px;border:1px solid rgba(239,68,68,.3)">
            <span class="pub-order-badge status-bad"><?= e(t('orders.status_' . $viewOrder['status'])) ?></span>
            <?php if ($viewOrder['cancellation_reason']): ?>
                <span style="margin-inline-start:10px;font-size:0.85rem"><?= e($viewOrder['cancellation_reason']) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Order Items -->
        <?php if ($orderItems): ?>
        <table class="pub-items-table">
            <thead>
                <tr>
                    <th><?= e(t('orders.product')) ?></th>
                    <th style="text-align:center"><?= e(t('orders.qty')) ?></th>
                    <th style="text-align:end"><?= e(t('orders.unit_price')) ?></th>
                    <th style="text-align:end"><?= e(t('orders.total')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orderItems as $it): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center">
                            <?php if ($it['image_url']): ?>
                                <img src="<?= e($it['image_url']) ?>" alt="" class="item-img">
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600"><?= e($it['product_name']) ?></div>
                                <div style="font-size:0.75rem;color:var(--pub-muted,#94a3b8)"><?= e($it['sku']) ?></div>
                                <?php if ($it['is_refunded']): ?><span style="font-size:0.72rem;background:rgba(239,68,68,.18);color:#f87171;padding:2px 8px;border-radius:10px"><?= e(t('orders.refunded')) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="text-align:center"><?= (int)$it['quantity'] ?></td>
                    <td style="text-align:end"><?= number_format((float)$it['unit_price'], 2) ?></td>
                    <td style="text-align:end;font-weight:600"><?= number_format((float)$it['total'], 2) ?> <?= e($it['currency_code']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Totals -->
        <div class="pub-totals-box">
            <div class="pub-totals-row"><span><?= e(t('orders.subtotal')) ?></span><span><?= number_format((float)$viewOrder['subtotal'], 2) ?> <?= e($viewOrder['currency_code']) ?></span></div>
            <?php if ((float)$viewOrder['tax_amount'] > 0): ?>
            <div class="pub-totals-row"><span><?= e(t('orders.tax')) ?></span><span><?= number_format((float)$viewOrder['tax_amount'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float)$viewOrder['shipping_cost'] > 0): ?>
            <div class="pub-totals-row"><span><?= e(t('orders.shipping')) ?></span><span><?= number_format((float)$viewOrder['shipping_cost'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float)$viewOrder['discount_amount'] > 0): ?>
            <div class="pub-totals-row" style="color:var(--pub-primary,#10B981)"><span><?= e(t('orders.discount')) ?></span><span>-<?= number_format((float)$viewOrder['discount_amount'], 2) ?></span></div>
            <?php endif; ?>
            <div class="pub-totals-row grand"><span><?= e(t('orders.grand_total')) ?></span><span><?= number_format((float)$viewOrder['grand_total'], 2) ?> <?= e($viewOrder['currency_code']) ?></span></div>
        </div>

        <!-- Status History -->
        <?php if ($statusHistory): ?>
        <div style="margin-top:24px">
            <div style="font-weight:600;margin-bottom:12px"><?= e(t('orders.status_history')) ?></div>
            <ul class="pub-hist-list">
            <?php foreach (array_reverse($statusHistory) as $h): ?>
                <li>
                    <div class="hist-dot"></div>
                    <div>
                        <div><strong><?= e(t('orders.status_' . $h['status'])) ?></strong>
                            <span style="color:var(--pub-muted,#94a3b8);font-size:0.78rem;margin-inline-start:8px"><?= date('Y-m-d H:i', strtotime($h['created_at'])) ?></span>
                        </div>
                        <?php if ($h['notes']): ?><div class="pub-hist-notes"><?= e($h['notes']) ?></div><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ========= ORDERS LIST VIEW ========= -->
    <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:8px"><?= e(t('orders.page_title')) ?></h1>
    <p style="color:var(--pub-muted,#94a3b8);margin-bottom:24px"><?= e(t('orders.page_subtitle')) ?></p>

    <!-- Summary Stats -->
    <div class="pub-orders-summary">
        <div class="pub-stat-card">
            <div class="stat-num"><?= $summary['total'] ?></div>
            <div class="stat-lbl"><?= e(t('orders.stat_total')) ?></div>
        </div>
        <div class="pub-stat-card">
            <div class="stat-num" style="color:var(--pub-accent,#F59E0B)"><?= $summary['pending'] ?></div>
            <div class="stat-lbl"><?= e(t('orders.stat_pending')) ?></div>
        </div>
        <div class="pub-stat-card">
            <div class="stat-num" style="color:#a78bfa"><?= $summary['processing'] ?></div>
            <div class="stat-lbl"><?= e(t('orders.stat_processing')) ?></div>
        </div>
        <div class="pub-stat-card">
            <div class="stat-num" style="color:var(--pub-primary,#10B981)"><?= $summary['delivered'] ?></div>
            <div class="stat-lbl"><?= e(t('orders.stat_delivered')) ?></div>
        </div>
        <div class="pub-stat-card">
            <div class="stat-num" style="color:#f87171"><?= $summary['cancelled'] ?></div>
            <div class="stat-lbl"><?= e(t('orders.stat_cancelled')) ?></div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="pub-filter-btns">
        <a href="?" class="pub-filter-btn <?= !$filter ? 'active' : '' ?>"><?= e(t('orders.filter_all')) ?></a>
        <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $fs): ?>
            <a href="?status=<?= $fs ?>" class="pub-filter-btn <?= $filter === $fs ? 'active' : '' ?>"><?= e(t('orders.status_' . $fs)) ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Orders List -->
    <?php if (empty($orders)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--pub-muted,#94a3b8)">
            <div style="font-size:3rem;margin-bottom:12px">📭</div>
            <div style="font-size:1.1rem;margin-bottom:16px"><?= e(t('orders.empty')) ?></div>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary"><?= e(t('orders.browse_products')) ?></a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $ord): ?>
        <a href="?view=<?= (int)$ord['id'] ?><?= $filter ? '&status='.e($filter) : '' ?>" style="display:block;text-decoration:none;color:inherit" class="pub-order-row">
            <div>
                <div class="pub-order-num">
                    <?= e($ord['order_number']) ?>
                    <?php if (!empty($ord['auction_id'])): ?>
                        <span style="background:#f59e0b;color:#000;font-size:0.65rem;padding:1px 6px;border-radius:4px;margin-inline-start:6px">🔨 <?= e(t('nav.auctions')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="pub-order-date">
                    <?= date('Y-m-d H:i', strtotime($ord['created_at'])) ?>
                    &nbsp;·&nbsp; <?= (int)$ord['item_count'] ?> <?= e(t('orders.items')) ?>
                </div>
            </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <span class="pub-order-badge <?= orderStatusClass($ord['status']) ?>">
                    <?= e(t('orders.status_' . $ord['status'])) ?>
                </span>
                <?php if ($ord['payment_status'] !== 'paid'): ?>
                    <span class="pub-order-badge status-pending" style="font-size:0.7rem"><?= e(t('orders.payment_' . $ord['payment_status'])) ?></span>
                <?php endif; ?>
                <div class="pub-order-total"><?= number_format((float)$ord['grand_total'], 2) ?> <?= e($ord['currency_code']) ?></div>
                <?php if (in_array($ord['payment_status'], ['pending','failed']) && !in_array($ord['status'], ['cancelled','refunded','failed'])): ?>
                    <span onclick="event.preventDefault(); window.location.href='/frontend/public/pay.php?order_id=<?= (int)$ord['id'] ?>'" class="pub-order-badge" style="background:var(--pub-primary,#10B981);color:#000;cursor:pointer">
                        💳 <?= e(t('orders.pay_now')) ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>