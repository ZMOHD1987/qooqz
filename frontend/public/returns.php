<?php
/**
 * frontend/public/returns.php
 * QOOQZ — Returns Page
 * Allows logged-in users to view their return requests and submit new ones.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

// Require login
if (!$_isLoggedIn) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/frontend/public/returns.php'));
    exit;
}

$GLOBALS['PUB_PAGE_TITLE'] = e(t('returns.page_title')) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';

$userId   = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
$tenantId = (int)($_SESSION['pub_tenant_id'] ?? 1);
$pdo      = pub_get_pdo();

// ── Load user's return requests ─────────────────────────────────────────────
$returns      = [];
$filterStatus = in_array($_GET['status'] ?? '', ['pending','approved','rejected','processing','completed','cancelled'])
              ? ($_GET['status'] ?? '') : '';

if ($pdo && $userId) {
    try {
        $where  = 'WHERE r.tenant_id = ? AND r.user_id = ?';
        $params = [$tenantId, $userId];
        if ($filterStatus) {
            $where .= ' AND r.status = ?';
            $params[] = $filterStatus;
        }
        $st = $pdo->prepare(
            "SELECT r.id, r.return_number, r.status, r.reason, r.requested_at, r.created_at,
                    o.order_number
             FROM returns r
             LEFT JOIN orders o ON o.id = r.order_id
             $where
             ORDER BY r.created_at DESC
             LIMIT 50"
        );
        $st->execute($params);
        $returns = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* show empty state */ }
}

// ── Load eligible orders for return dropdown ─────────────────────────────────
$eligibleOrders = [];
if ($pdo && $userId) {
    try {
        $st = $pdo->prepare(
            "SELECT id, order_number, status, grand_total, currency_code, created_at
             FROM orders
             WHERE user_id = ? AND tenant_id = ?
               AND status IN ('delivered','completed')
             ORDER BY created_at DESC
             LIMIT 100"
        );
        $st->execute([$userId, $tenantId]);
        $eligibleOrders = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* will fall back to JS API call */ }
}

// ── Status helpers ──────────────────────────────────────────────────────────
function return_status_label(string $s): string {
    $map = [
        'pending'    => 'status_pending',
        'approved'   => 'status_approved',
        'rejected'   => 'status_rejected',
        'processing' => 'status_processing',
        'completed'  => 'status_completed',
        'cancelled'  => 'status_cancelled',
    ];
    return t('returns.' . ($map[$s] ?? 'status_pending'));
}
function return_status_color(string $s): string {
    return match($s) {
        'approved', 'completed' => '#16A34A',
        'pending', 'processing' => '#D97706',
        'rejected', 'cancelled' => '#DC2626',
        default                 => '#6B7280',
    };
}
?>

<main class="pub-container" style="padding:40px 0 60px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:24px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('returns.page_title')) ?></span>
    </nav>

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:700;margin:0;"><?= e(t('returns.page_title')) ?></h1>
            <p style="font-size:0.92rem;color:var(--pub-muted);margin:4px 0 0;"><?= e(t('returns.page_subtitle')) ?></p>
        </div>
        <button onclick="document.getElementById('returnFormWrap').style.display='block';this.style.display='none';window.scrollTo({top:0,behavior:'smooth'});"
                style="padding:10px 22px;background:var(--pub-primary);color:#fff;border:none;border-radius:8px;
                       font-size:0.95rem;font-weight:600;cursor:pointer;">
            + <?= e(t('returns.new_return')) ?>
        </button>
    </div>

    <!-- New return form (hidden by default) -->
    <div id="returnFormWrap" style="display:none;margin-bottom:32px;">
        <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:12px;padding:28px;">
            <h2 style="font-size:1.1rem;font-weight:700;margin:0 0 20px;"><?= e(t('returns.new_return')) ?></h2>
            <form id="returnForm">
                <!-- Order select dropdown -->
                <div style="margin-bottom:16px;">
                    <label style="font-size:0.87rem;font-weight:600;display:block;margin-bottom:6px;">
                        <?= e(t('returns.order_id')) ?> *
                    </label>
                    <select id="returnOrderNumber" name="order_number" required
                            style="width:100%;padding:10px 12px;border:1px solid var(--pub-border);
                                   border-radius:8px;font-size:0.93rem;box-sizing:border-box;
                                   background:var(--pub-bg);color:var(--pub-text);appearance:auto;">
                        <option value=""><?= e(t('returns.select_order')) ?></option>
                        <?php foreach ($eligibleOrders as $eo): ?>
                        <option value="<?= (int)$eo['id'] ?>">
                            #<?= e($eo['order_number'] ?: $eo['id']) ?>
                            <?php if (!empty($eo['grand_total'])): ?>
                            — <?= e(number_format((float)$eo['grand_total'], 2)) ?> <?= e($eo['currency_code'] ?? '') ?>
                            <?php endif; ?>
                            (<?= e(date('Y-m-d', strtotime($eo['created_at']))) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="returnOrderStatus" style="margin-top:8px;font-size:0.85rem;"></div>
                </div>

                <!-- Order items preview (shown after successful lookup) -->
                <div id="returnItemsWrap" style="display:none;margin-bottom:16px;">
                    <label style="font-size:0.87rem;font-weight:600;display:block;margin-bottom:8px;">
                        <?= e(t('returns.order_items')) ?>
                    </label>
                    <div id="returnItemsList"
                         style="border:1px solid var(--pub-border);border-radius:8px;overflow:hidden;"></div>
                </div>

                <!-- Reason -->
                <div id="returnReasonWrap" style="display:none;margin-bottom:20px;">
                    <label style="font-size:0.87rem;font-weight:600;display:block;margin-bottom:6px;">
                        <?= e(t('returns.reason')) ?> *
                    </label>
                    <textarea name="reason" id="returnReason" rows="4"
                              placeholder="<?= e(t('returns.reason_placeholder')) ?>"
                              style="width:100%;padding:10px 12px;border:1px solid var(--pub-border);
                                     border-radius:8px;font-size:0.93rem;resize:vertical;box-sizing:border-box;
                                     background:var(--pub-bg);color:var(--pub-text);"></textarea>
                </div>

                <!-- Buttons -->
                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('returnFormWrap').style.display='none';
                                document.querySelector('button[onclick*=returnFormWrap]').style.display='';"
                            style="padding:10px 22px;background:transparent;border:1px solid var(--pub-border);
                                   border-radius:8px;font-size:0.95rem;cursor:pointer;color:var(--pub-text);">
                        <?= e(t('returns.cancel')) ?>
                    </button>
                    <button type="submit" id="returnSubmitBtn" disabled
                            style="padding:10px 22px;background:var(--pub-primary);color:#fff;border:none;
                                   border-radius:8px;font-size:0.95rem;font-weight:600;cursor:pointer;opacity:0.5;">
                        <?= e(t('returns.submit')) ?>
                    </button>
                </div>
                <div id="returnFormMsg" style="margin-top:12px;font-size:0.9rem;"></div>
            </form>
        </div>
    </div>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <?php
        $tabs = [
            ''           => t('returns.filter_all'),
            'pending'    => t('returns.filter_pending'),
            'approved'   => t('returns.filter_approved'),
        ];
        foreach ($tabs as $val => $label):
            $active = $filterStatus === $val;
        ?>
        <a href="?status=<?= urlencode($val) ?>"
           style="padding:7px 16px;border-radius:20px;font-size:0.87rem;font-weight:<?= $active?'700':'500'?>;
                  text-decoration:none;transition:all .2s;
                  background:<?= $active?'var(--pub-primary)':'var(--pub-surface)'?>;
                  color:<?= $active?'#fff':'var(--pub-text)'?>;
                  border:1px solid <?= $active?'transparent':'var(--pub-border)'?>;">
            <?= e($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Returns list -->
    <?php if (empty($returns)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--pub-muted);">
        <div style="font-size:3rem;margin-bottom:16px;">📦</div>
        <p style="font-size:1rem;margin:0 0 16px;"><?= e(t('returns.empty')) ?></p>
        <button onclick="document.getElementById('returnFormWrap').style.display='block';
                         document.querySelector('button[onclick*=returnFormWrap]').style.display='none';
                         window.scrollTo({top:0,behavior:'smooth'});"
                style="padding:10px 24px;background:var(--pub-primary);color:#fff;border:none;
                       border-radius:8px;font-size:0.95rem;font-weight:600;cursor:pointer;">
            + <?= e(t('returns.new_return')) ?>
        </button>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($returns as $ret): ?>
        <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:12px;
                    padding:18px 20px;display:flex;align-items:center;justify-content:space-between;
                    flex-wrap:wrap;gap:12px;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                    <span style="font-size:0.78rem;color:var(--pub-muted);">
                        <?= e(t('returns.return_number')) ?><?= (int)$ret['id'] ?>
                    </span>
                    <?php if (!empty($ret['order_number'])): ?>
                    <span style="font-size:0.78rem;background:rgba(0,0,0,0.06);padding:2px 8px;border-radius:10px;">
                        <?= e(t('returns.order_ref')) ?> #<?= e($ret['order_number']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($ret['reason'])): ?>
                <div style="font-size:0.93rem;margin-bottom:4px;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:500px;">
                    <?= e($ret['reason']) ?>
                </div>
                <?php endif; ?>
                <div style="font-size:0.82rem;color:var(--pub-muted);">
                    <?= e(t('returns.requested_on')) ?>:
                    <?= e(date('Y-m-d', strtotime($ret['requested_at'] ?? $ret['created_at']))) ?>
                </div>
            </div>
            <!-- Status -->
            <span style="font-size:0.82rem;font-weight:600;padding:4px 12px;border-radius:20px;
                         background:<?= e(return_status_color($ret['status'] ?? 'pending')) ?>22;
                         color:<?= e(return_status_color($ret['status'] ?? 'pending')) ?>;">
                <?= e(return_status_label($ret['status'] ?? 'pending')) ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<script>
(function () {
    var form        = document.getElementById('returnForm');
    var orderSelect = document.getElementById('returnOrderNumber');
    var orderStatus = document.getElementById('returnOrderStatus');
    var itemsWrap   = document.getElementById('returnItemsWrap');
    var itemsList   = document.getElementById('returnItemsList');
    var reasonWrap  = document.getElementById('returnReasonWrap');
    var submitBtn   = document.getElementById('returnSubmitBtn');
    var formMsg     = document.getElementById('returnFormMsg');
    var tenantId    = <?= (int)$tenantId ?>;

    if (!form) return;

    /* ---- Load eligible orders via API if PHP rendered none ---- */
    function loadEligibleOrders() {
        if (!orderSelect) return;
        if (orderSelect.options.length > 1) return; // already populated by PHP
        orderStatus.style.color = 'var(--pub-muted)';
        orderStatus.textContent = <?= json_encode(t('returns.loading_orders')) ?>;
        fetch('/api/public/returns/eligible-orders?tenant_id=' + tenantId, {
            credentials: 'include'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            orderStatus.textContent = '';
            var orders = (res.data && Array.isArray(res.data.items)) ? res.data.items : [];
            if (!orders.length) {
                orderStatus.style.color = 'var(--pub-muted)';
                orderStatus.textContent = <?= json_encode(t('returns.no_eligible_orders')) ?>;
                return;
            }
            orders.forEach(function (o) {
                var opt  = document.createElement('option');
                opt.value = o.id;
                var label = '#' + (o.order_number || o.id);
                if (o.grand_total) label += ' — ' + parseFloat(o.grand_total).toFixed(2) + ' ' + (o.currency_code || '');
                if (o.created_at) label += ' (' + o.created_at.substring(0, 10) + ')';
                opt.textContent = label;
                orderSelect.appendChild(opt);
            });
        })
        .catch(function () {
            orderStatus.textContent = '';
        });
    }

    /* Load orders when the "new return" button is clicked or immediately */
    var showBtn = document.querySelector('button[onclick*="returnFormWrap"]');
    if (showBtn) {
        showBtn.addEventListener('click', function () { setTimeout(loadEligibleOrders, 50); });
    }
    loadEligibleOrders();

    /* ---- Auto-lookup when an order is selected ---- */
    function doLookup() {
        var num = orderSelect ? orderSelect.value.trim() : '';
        if (!num) {
            orderStatus.textContent = '';
            itemsWrap.style.display  = 'none';
            reasonWrap.style.display = 'none';
            submitBtn.disabled       = true;
            submitBtn.style.opacity  = '0.5';
            return;
        }
        orderStatus.style.color = 'var(--pub-muted)';
        orderStatus.textContent = <?= json_encode(t('returns.loading')) ?>;
        itemsWrap.style.display  = 'none';
        reasonWrap.style.display = 'none';
        submitBtn.disabled       = true;
        submitBtn.style.opacity  = '0.5';

        fetch('/api/public/returns/order-items?order_number=' + encodeURIComponent(num) + '&tenant_id=' + tenantId, {
            credentials: 'include'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success && res.data && res.data.order) {
                var order = res.data.order;
                var items = res.data.items || [];
                var existingReturn = res.data.existing_return;

                if (existingReturn) {
                    orderStatus.style.color = '#D97706';
                    orderStatus.textContent = <?= json_encode(t('returns.already_returned')) ?>;
                    return;
                }

                orderStatus.style.color = '#16A34A';
                orderStatus.textContent = <?= json_encode(t('returns.order_found')) ?> + ' #' + order.order_number;

                // Render items using DOM methods to prevent XSS
                if (items.length) {
                    itemsList.innerHTML = '';
                    var isRtl = document.documentElement.dir === 'rtl';
                    items.forEach(function (it, idx) {
                        var row = document.createElement('div');
                        row.style.cssText = 'display:flex;align-items:center;padding:10px 14px;background:'
                            + (idx % 2 === 0 ? 'var(--pub-bg)' : 'var(--pub-surface)')
                            + ';border-bottom:1px solid var(--pub-border);';

                        if (it.image_url) {
                            var img = document.createElement('img');
                            img.src   = it.image_url;
                            img.alt   = '';
                            img.style.cssText = 'width:40px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;'
                                + (isRtl ? 'margin-left:10px;' : 'margin-right:10px;');
                            row.appendChild(img);
                        } else {
                            var ph = document.createElement('span');
                            ph.style.cssText = 'width:40px;height:40px;border-radius:6px;background:var(--pub-border);'
                                + 'display:inline-block;flex-shrink:0;'
                                + (isRtl ? 'margin-left:10px;' : 'margin-right:10px;');
                            row.appendChild(ph);
                        }

                        var info = document.createElement('div');
                        info.style.flex = '1';

                        var name = document.createElement('div');
                        name.style.cssText = 'font-size:0.92rem;font-weight:600;';
                        name.textContent = it.product_name;

                        var qty = document.createElement('div');
                        qty.style.cssText = 'font-size:0.8rem;color:var(--pub-muted);';
                        qty.textContent = <?= json_encode(t('returns.qty')) ?> + ': ' + it.quantity;

                        info.appendChild(name);
                        info.appendChild(qty);
                        row.appendChild(info);
                        itemsList.appendChild(row);
                    });
                    itemsWrap.style.display = 'block';
                }

                reasonWrap.style.display = 'block';
                document.getElementById('returnReason').required = true;
                submitBtn.disabled      = false;
                submitBtn.style.opacity = '1';
            } else {
                orderStatus.style.color = '#DC2626';
                orderStatus.textContent = res.message || <?= json_encode(t('returns.order_not_found')) ?>;
            }
        })
        .catch(function () {
            orderStatus.style.color = '#DC2626';
            orderStatus.textContent = <?= json_encode(t('returns.error')) ?>;
        });
    }

    if (orderSelect) {
        orderSelect.addEventListener('change', doLookup);
    }

    /* ---- Form submit ---- */
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var reason = document.getElementById('returnReason').value.trim();
        if (!reason) {
            formMsg.style.color = '#DC2626';
            formMsg.textContent = <?= json_encode(t('returns.reason')) ?>;
            return;
        }

        submitBtn.disabled      = true;
        submitBtn.style.opacity = '0.7';

        var data = {
            order_number: orderSelect ? orderSelect.value.trim() : '',
            reason:       reason,
            tenant_id:    tenantId
        };

        fetch('/api/public/returns?tenant_id=' + tenantId, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success || res.id || (res.data && res.data.id)) {
                formMsg.style.color = '#16A34A';
                formMsg.textContent = <?= json_encode(t('returns.success')) ?>;
                form.reset();
                itemsWrap.style.display  = 'none';
                reasonWrap.style.display = 'none';
                orderStatus.textContent  = '';
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                throw new Error(res.message || res.error || 'error');
            }
        })
        .catch(function (err) {
            formMsg.style.color     = '#DC2626';
            formMsg.textContent     = err.message !== 'error' ? err.message : <?= json_encode(t('returns.error')) ?>;
            submitBtn.disabled      = false;
            submitBtn.style.opacity = '1';
        });
    });
})();
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>