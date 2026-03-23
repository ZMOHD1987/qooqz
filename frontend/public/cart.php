<?php
declare(strict_types=1);
/**
 * frontend/public/cart.php
 * QOOQZ — Shopping Cart Page
 *
 * Cart items are stored in the browser's localStorage under key 'pub_cart'.
 * This page renders an empty shell and JavaScript fills it from localStorage.
 * This matches the pubAddToCart() function in public.js.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('cart.title') . ' — QOOQZ';

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span>🛒 <?= e(t('cart.title')) ?></span>
    </nav>

    <!-- Title row — JS fills in item count -->
    <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:24px;">
        <h1 style="font-size:1.4rem;margin:0;">🛒 <?= e(t('cart.title')) ?></h1>
        <span id="cartCountLabel" style="font-size:0.85rem;font-weight:400;color:var(--pub-muted);"></span>
    </div>

    <!-- JS renders the cart here: either items or empty message -->
    <div id="pubCartBody">
        <!-- Loading state -->
        <div id="cartLoading" style="text-align:center;padding:60px 0;color:var(--pub-muted);">
            ⏳ <?= e(t('common.loading')) ?>
        </div>
    </div>

</div>

<!-- Cart item template: two-column layout (items | summary) -->
<template id="tmplCartLayout">
    <div class="pub-cart-layout">
        <div class="pub-cart-items" id="cartItemsList"></div>
        <div class="pub-cart-summary">
            <div class="pub-cart-summary-inner">
                <h2 class="pub-cart-summary-title">📋 <?= e(t('cart.order_summary')) ?></h2>
                <div class="pub-summary-row">
                    <span><?= e(t('cart.subtotal')) ?></span>
                    <strong id="cartSubtotal">0.00</strong>
                </div>
                <div class="pub-summary-row" style="color:var(--pub-muted);font-size:0.84rem;">
                    <span><?= e(t('cart.shipping')) ?></span>
                    <span>—</span>
                </div>
                <div class="pub-summary-row pub-summary-total">
                    <span><?= e(t('cart.total')) ?></span>
                    <strong id="cartGrandTotal">0.00</strong>
                </div>
                <a href="/frontend/public/checkout.php"
                   class="pub-btn pub-btn--primary"
                   style="width:100%;text-align:center;margin-top:16px;display:block;font-size:1rem;padding:12px;">
                    💳 <?= e(t('cart.checkout')) ?>
                </a>
                <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost pub-btn--sm"
                   style="width:100%;text-align:center;margin-top:10px;display:block;">
                    ← <?= e(t('cart.continue_shopping')) ?>
                </a>
            </div>
        </div>
    </div>
</template>

<!-- Empty cart template -->
<template id="tmplCartEmpty">
    <div class="pub-empty" style="padding:60px 0;">
        <div class="pub-empty-icon">🛒</div>
        <p class="pub-empty-msg" style="font-size:1.1rem;margin-bottom:20px;">
            <?= e(t('cart.empty')) ?>
        </p>
        <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary">
            🛍️ <?= e(t('hero.browse_products')) ?>
        </a>
    </div>
</template>

<style>
.pub-cart-layout { display:grid; gap:24px; }
@media(min-width:900px){ .pub-cart-layout { grid-template-columns: 1fr 340px; align-items:start; } }
.pub-cart-items { display:grid; gap:12px; }
.pub-cart-item {
    background:var(--pub-bg);
    border:1px solid var(--pub-border);
    border-radius:var(--pub-radius);
    padding:14px;
    display:flex;
    gap:14px;
    align-items:flex-start;
    transition:box-shadow var(--pub-transition);
}
.pub-cart-item:hover { box-shadow:var(--pub-shadow); }
.pub-cart-item-img {
    width:72px; height:72px;
    border-radius:var(--pub-radius-sm);
    overflow:hidden;
    background:var(--pub-surface);
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
}
.pub-cart-item-img img { width:100%;height:100%;object-fit:cover; }
.pub-cart-item-info { flex:1; min-width:0; }
.pub-cart-item-name { font-size:0.92rem;font-weight:600;color:var(--pub-text);display:block;margin-bottom:4px; }
.pub-cart-item-price { font-size:0.88rem;color:var(--pub-primary);font-weight:700;margin:4px 0 0; }
.pub-cart-item-actions { display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0; }
.pub-qty-form { display:flex; align-items:center; gap:4px; }
.pub-qty-btn { width:28px;height:28px;border-radius:50%;border:1px solid var(--pub-border);background:var(--pub-surface);color:var(--pub-text);font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background var(--pub-transition); }
.pub-qty-btn:hover { background:var(--pub-primary);color:#fff;border-color:var(--pub-primary); }
.pub-qty-input { width:48px;height:28px;text-align:center;border:1px solid var(--pub-border);border-radius:var(--pub-radius-sm);background:var(--pub-bg);color:var(--pub-text);font-size:0.88rem; }
.pub-cart-item-subtotal { font-size:0.88rem;font-weight:700;color:var(--pub-text);margin:0; }
.pub-remove-btn { background:none;border:none;color:var(--pub-muted);cursor:pointer;font-size:1rem;padding:4px;border-radius:4px;transition:color var(--pub-transition); }
.pub-remove-btn:hover { color:#e74c3c; }
.pub-cart-summary-inner { background:var(--pub-bg);border:1px solid var(--pub-border);border-radius:var(--pub-radius);padding:20px;position:sticky;top:calc(var(--pub-header-h) + 16px); }
.pub-cart-summary-title { font-size:1rem;font-weight:700;margin:0 0 16px;color:var(--pub-text); }
.pub-summary-row { display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--pub-border); }
.pub-summary-row:last-of-type { border-bottom:none; }
.pub-summary-total { font-size:1rem;font-weight:800;color:var(--pub-text); }
</style>

<script>
(function () {
  var CURRENCY      = <?= json_encode(t('common.currency')) ?>;
  var REMOVE_CONFIRM = <?= json_encode(t('cart.remove') . '?') ?>;
  var ITEMS_LABEL   = <?= json_encode(t('cart.items')) ?>;
  var TENANT_ID     = <?= (int)($tenantId ?? 1) ?>;

  /* ──────────────────────────────────────────────────
   * Helpers
   * ────────────────────────────────────────────────── */
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function imgUrl(path) {
    if (!path) return '';
    if (/^https?:\/\/|^\/\//.test(path)) return path;
    if (path.charAt(0) === '/') return path;
    if (/^(uploads\/|admin\/uploads\/)/.test(path)) return '/' + path;
    return '/uploads/images/' + path;
  }

  /* ── localStorage helpers ─────────────────────────── */
  function getLocalCart() {
    try { return JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) { return []; }
  }

  function saveLocalCart(cart) {
    try { localStorage.setItem('pub_cart', JSON.stringify(cart)); } catch (e) {}
    var total = cart.reduce(function(s,i){ return s + Math.max(1, parseInt(i.qty,10)||1); }, 0);
    ['pubCartCount','pubCartCountMobile'].forEach(function(id){
      var el = document.getElementById(id);
      if (!el) return;
      el.textContent = total;
      el.style.display = total ? 'inline-flex' : 'none';
    });
  }

  /* ── Render ───────────────────────────────────────── */
  function renderCart(cartItems) {
    var body = document.getElementById('pubCartBody');
    var countLabel = document.getElementById('cartCountLabel');
    if (!body) return;

    if (!cartItems || cartItems.length === 0) {
      var tmpl = document.getElementById('tmplCartEmpty');
      body.innerHTML = '';
      body.appendChild(tmpl.content.cloneNode(true));
      if (countLabel) countLabel.textContent = '';
      return;
    }

    var tmpl = document.getElementById('tmplCartLayout');
    body.innerHTML = '';
    body.appendChild(tmpl.content.cloneNode(true));

    var list = document.getElementById('cartItemsList');
    var total = 0, itemCount = 0;

    cartItems.forEach(function (item, idx) {
      var price   = parseFloat(item.price) || 0;
      var qty     = Math.max(1, parseInt(item.qty, 10) || 1);
      var subtotal = price * qty;
      total    += subtotal;
      itemCount += qty;
      var dbId  = item._db_item_id || 0;  // 0 = localStorage-only item

      var img = item.image ? '<img src="' + esc(imgUrl(item.image)) + '" alt="' + esc(item.name) + '" loading="lazy" onerror="this.style.display=\'none\'">' : '🖼️';

      var div = document.createElement('div');
      div.className = 'pub-cart-item';
      div.dataset.cartIdx   = idx;
      div.dataset.dbItemId  = dbId;
      div.dataset.productId = item.id;
      div.innerHTML =
        '<div class="pub-cart-item-img">' + img + '</div>'
        + '<div class="pub-cart-item-info">'
        + '<a href="/frontend/public/product.php?id=' + (parseInt(item.id)||0) + '" class="pub-cart-item-name">' + esc(item.name || '') + '</a>'
        + '<p class="pub-cart-item-price">' + price.toFixed(2) + ' ' + esc(CURRENCY) + '</p>'
        + '</div>'
        + '<div class="pub-cart-item-actions">'
        + '<div class="pub-qty-form">'
        + '<button class="pub-qty-btn" type="button" data-idx="' + idx + '" data-db-id="' + dbId + '" data-delta="-1">−</button>'
        + '<input type="number" class="pub-qty-input cart-qty-inp" value="' + qty + '" min="1" max="999" data-idx="' + idx + '" data-db-id="' + dbId + '" data-price="' + price + '">'
        + '<button class="pub-qty-btn" type="button" data-idx="' + idx + '" data-db-id="' + dbId + '" data-delta="1">+</button>'
        + '</div>'
        + '<p class="pub-cart-item-subtotal">= ' + subtotal.toFixed(2) + ' ' + esc(CURRENCY) + '</p>'
        + '<button class="pub-remove-btn" type="button" data-idx="' + idx + '" data-db-id="' + dbId + '" title="✕">✕</button>'
        + '</div>';
      list.appendChild(div);
    });

    var subEl   = document.getElementById('cartSubtotal');
    var grandEl = document.getElementById('cartGrandTotal');
    if (subEl)   subEl.textContent   = total.toFixed(2) + ' ' + CURRENCY;
    if (grandEl) grandEl.textContent = total.toFixed(2) + ' ' + CURRENCY;
    if (countLabel) countLabel.textContent = '(' + itemCount + ' ' + ITEMS_LABEL + ')';
  }

  /* ── DB cart helpers ──────────────────────────────── */
  function dbUpdate(itemId, qty, cb) {
    if (!itemId) return cb && cb();
    fetch('/api/public/cart/update?tenant_id=' + TENANT_ID, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ item_id: itemId, qty: qty })
    }).then(function() { cb && cb(); }).catch(function() { cb && cb(); });
  }

  function dbRemove(itemId, cb) {
    if (!itemId) return cb && cb();
    fetch('/api/public/cart/remove?tenant_id=' + TENANT_ID, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ item_id: itemId })
    }).then(function() { cb && cb(); }).catch(function() { cb && cb(); });
  }

  /* ── Load DB cart, fall back to localStorage ──────── */
  var _cartItems = []; // current items being displayed

  function loadCart() {
    var loading = document.getElementById('cartLoading');
    fetch('/api/public/cart?tenant_id=' + TENANT_ID, {
      credentials: 'include',
      headers: { 'Accept': 'application/json' }
    })
    .then(function(r) { return r.ok ? r.json() : Promise.reject(r.status); })
    .then(function(resp) {
      var dbItems = (resp.data && resp.data.items) ? resp.data.items : null;
      if (dbItems && dbItems.length > 0) {
        // Map DB items to our display format
        _cartItems = dbItems.map(function(ci) {
          return {
            id: ci.product_id,
            name: ci.product_name,
            price: parseFloat(ci.unit_price) || 0,
            qty: parseInt(ci.quantity, 10) || 1,
            sku: ci.sku || '',
            image: '',   // DB doesn't store image URL in cart_items
            _db_item_id: ci.id
          };
        });
        // Enrich images from localStorage where available (same product_id)
        var localCart = getLocalCart();
        _cartItems.forEach(function(ci) {
          var lc = localCart.find(function(l) { return l.id === ci.id; });
          if (lc && lc.image) ci.image = lc.image;
        });
        // Sync localStorage to match DB
        saveLocalCart(_cartItems.map(function(ci) {
          return { id: ci.id, name: ci.name, price: ci.price, qty: ci.qty, sku: ci.sku, image: ci.image };
        }));
      } else {
        // No DB items — use localStorage
        _cartItems = getLocalCart().map(function(i, idx) {
          return Object.assign({}, i, { _db_item_id: 0 });
        });
      }
      if (loading) loading.style.display = 'none';
      renderCart(_cartItems);
    })
    .catch(function() {
      // DB load failed — fall back to localStorage
      _cartItems = getLocalCart().map(function(i) {
        return Object.assign({}, i, { _db_item_id: 0 });
      });
      if (loading) loading.style.display = 'none';
      renderCart(_cartItems);
    });
  }

  /* ── Event delegation ─────────────────────────────── */
  document.addEventListener('click', function (e) {
    // ± buttons
    var btn = e.target.closest('.pub-qty-btn[data-delta]');
    if (btn) {
      var idx   = parseInt(btn.dataset.idx, 10);
      var delta = parseInt(btn.dataset.delta, 10);
      var dbId  = parseInt(btn.dataset.dbId, 10) || 0;
      if (!_cartItems[idx]) return;
      var newQty = Math.max(1, (_cartItems[idx].qty || 1) + delta);
      _cartItems[idx].qty = newQty;
      // Update localStorage
      var lc = getLocalCart();
      var pid = _cartItems[idx].id;
      lc.forEach(function(li) { if (li.id === pid) li.qty = newQty; });
      saveLocalCart(lc);
      renderCart(_cartItems);
      dbUpdate(dbId, newQty, null);
      return;
    }

    // Remove button
    var rmBtn = e.target.closest('.pub-remove-btn[data-idx]');
    if (rmBtn) {
      if (!confirm(REMOVE_CONFIRM)) return;
      var idx  = parseInt(rmBtn.dataset.idx, 10);
      var dbId = parseInt(rmBtn.dataset.dbId, 10) || 0;
      if (!_cartItems[idx]) return;
      var pid = _cartItems[idx].id;
      _cartItems.splice(idx, 1);
      // Update localStorage
      var lc = getLocalCart().filter(function(li) { return li.id !== pid; });
      saveLocalCart(lc);
      renderCart(_cartItems);
      dbRemove(dbId, null);
      return;
    }
  });

  // Qty input direct edit
  document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('cart-qty-inp')) return;
    var idx   = parseInt(e.target.dataset.idx, 10);
    var dbId  = parseInt(e.target.dataset.dbId, 10) || 0;
    var newQty = Math.max(1, parseInt(e.target.value, 10) || 1);
    if (!_cartItems[idx]) return;
    var pid = _cartItems[idx].id;
    _cartItems[idx].qty = newQty;
    var lc = getLocalCart();
    lc.forEach(function(li) { if (li.id === pid) li.qty = newQty; });
    saveLocalCart(lc);
    renderCart(_cartItems);
    dbUpdate(dbId, newQty, null);
  });

  /* ── Init ─────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', loadCart);
}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>