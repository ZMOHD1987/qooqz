<?php
require_once dirname(__DIR__) . '/includes/public_context.php';

// Require login
if (!$_isLoggedIn) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/frontend/public/wishlist.php'));
    exit;
}

$GLOBALS['PUB_PAGE_TITLE'] = e(t('wishlist.page_title')) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';

// Resolve product card style from DB card_styles (card_type='product')
$_wishProductCardStyle = pub_card_inline_style('product');
$_wishProductCardClass = pub_card_css_class('product');
$_wishProductImgStyle  = pub_card_img_style('product');

// Load wishlist items via direct PDO for reliability
$wishItems = [];
$wishlistId = 0;
$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
        // Get or create default wishlist
        $stWl = $pdo->prepare('SELECT id FROM wishlists WHERE user_id = ? AND tenant_id = ? AND is_default = 1 LIMIT 1');
        $stWl->execute([$userId, $tenantId]);
        $wlRow = $stWl->fetch(PDO::FETCH_ASSOC);
        if ($wlRow) {
            $wishlistId = (int)$wlRow['id'];
            $stItems = $pdo->prepare(
                "SELECT wi.id, wi.product_id, wi.priority, wi.notes, wi.created_at,
                        COALESCE(pt.name, p.slug) AS product_name,
                        (SELECT i2.url FROM images i2 WHERE i2.owner_id = p.id
                         ORDER BY i2.is_main DESC, i2.id ASC LIMIT 1) AS image_url,
                        (SELECT pp2.price FROM product_pricing pp2 WHERE pp2.product_id = p.id LIMIT 1) AS price,
                        (SELECT pp2.currency_code FROM product_pricing pp2 WHERE pp2.product_id = p.id LIMIT 1) AS currency_code,
                        p.stock_status
                 FROM wishlist_items wi
                 JOIN products p ON p.id = wi.product_id
                 LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
                 WHERE wi.wishlist_id = ? AND wi.removed_at IS NULL
                 ORDER BY wi.priority DESC, wi.created_at DESC"
            );
            $stItems->execute([$lang, $wishlistId]);
            $wishItems = $stItems->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $ex) {
        $wishItems = [];
    }
}
?>

<main class="pub-container" style="padding-top:24px;padding-bottom:48px;">
    <h1 class="pub-page-title">♡ <?= e(t('wishlist.page_title')) ?></h1>
    <p class="pub-page-sub"><?= e(t('wishlist.page_subtitle')) ?></p>

    <?php if (empty($wishItems)): ?>
    <div class="pub-wishlist-empty">
        <span class="pub-wishlist-empty-icon">♡</span>
        <p><?= e(t('wishlist.empty')) ?></p>
        <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary" style="margin-top:16px;display:inline-block;">
            <?= e(t('wishlist.browse_products')) ?>
        </a>
    </div>
    <?php else: ?>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
        <p style="color:var(--pub-muted);font-size:0.9rem;">
            <?= count($wishItems) ?> <?= e(t('wishlist.items_count')) ?>
        </p>
        <button class="pub-btn pub-btn--ghost pub-btn--sm" type="button" onclick="pubClearWishlist(this)">
            🗑 <?= e(t('wishlist.clear_all')) ?>
        </button>
    </div>

    <div class="pub-grid">
        <?php foreach ($wishItems as $wi):
            $wPid   = (int)$wi['product_id'];
            $wName  = $wi['product_name'] ?? '';
            $wPrice = isset($wi['price']) ? (float)$wi['price'] : null;
            $wCur   = $wi['currency_code'] ?? '';
            $wImg   = pub_img($wi['image_url'] ?? null, 'product');
        ?>
        <div class="pub-product-card<?= $_wishProductCardClass ? ' ' . $_wishProductCardClass : '' ?>" style="position:relative;<?= e($_wishProductCardStyle) ?>" id="wishcard-<?= $wPid ?>">
            <!-- Heart button (active — in wishlist) -->
            <button class="pub-wishlist-btn pub-wishlist-active"
                    type="button"
                    data-product-id="<?= $wPid ?>"
                    onclick="pubToggleWishlist(this)"
                    title="<?= e(t('wishlist.remove')) ?>">♥</button>

            <a href="/frontend/public/product.php?id=<?= $wPid ?>"
               style="text-decoration:none;display:flex;flex-direction:column;flex:1;"
               aria-label="<?= e($wName) ?>">
                <div class="pub-cat-img-wrap" style="aspect-ratio:1;">
                    <?php if ($wImg): ?>
                        <img src="<?= e($wImg) ?>" alt="<?= e($wName) ?>"
                             class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🖼️</span>
                    <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-card-body">
                    <p class="pub-product-name"><?= e($wName) ?></p>
                    <?php if ($wPrice !== null): ?>
                    <p class="pub-product-price">
                        <?= number_format($wPrice, 2) ?> <small><?= e($wCur) ?></small>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($wi['stock_status'])): ?>
                    <span style="font-size:0.75rem;color:<?= $wi['stock_status'] === 'in_stock' ? 'var(--pub-success,#10B981)' : 'var(--pub-error,#EF4444)' ?>;">
                        <?= $wi['stock_status'] === 'in_stock' ? e(t('products.in_stock')) : e(t('products.out_of_stock')) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </a>
            <div style="padding:0 14px 14px;display:flex;gap:8px;">
                <button class="pub-btn pub-btn--primary pub-btn--sm"
                        type="button"
                        style="flex:1;justify-content:center;"
                        data-product-id="<?= $wPid ?>"
                        data-product-name="<?= e($wName) ?>"
                        data-product-price="<?= e((string)($wPrice ?? '0')) ?>"
                        data-product-image="<?= e($wImg ?: '') ?>"
                        data-currency="<?= e($wCur) ?>"
                        data-added-text="✅ <?= e(t('cart.added')) ?>"
                        onclick="pubAddToCart(this)">
                    🛒 <?= e(t('cart.add')) ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<script>
// Remove wishlist card from DOM after toggling off
(function() {
    var _origToggle = window.pubToggleWishlist;
    window.pubToggleWishlist = function(btn) {
        var pid = btn.dataset.productId;
        var wasActive = btn.classList.contains('pub-wishlist-active');
        _origToggle.call(this, btn);
        if (wasActive) {
            // Remove the card from wishlist page after a short delay
            setTimeout(function() {
                var card = document.getElementById('wishcard-' + pid);
                if (card) {
                    card.style.transition = 'opacity 0.3s';
                    card.style.opacity = '0';
                    setTimeout(function() { card.remove(); }, 300);
                }
            }, 600);
        }
    };
})();

function pubClearWishlist(btn) {
    if (!confirm(<?= json_encode(t('wishlist.confirm_clear')) ?>)) return;
    btn.disabled = true;
    fetch('/api/public/wishlist/clear', { method: 'POST', credentials: 'include' })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) window.location.reload(); })
    .catch(function() {})
    .finally(function() { btn.disabled = false; });
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>