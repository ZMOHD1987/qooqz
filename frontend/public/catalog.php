<?php
declare(strict_types=1);
/**
 * frontend/public/catalog.php
 * QOOQZ — Catalog Page
 * Displays an animated banner slider, product categories, and product listings.
 * Completely standalone — not included in index.php.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];
$apiBase  = pub_api_url('');

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('catalog.page_title', 'Catalog') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('catalog.page_description', 'Browse products and categories');

/* ------------------------------------------------------------------
 * Query params
 * ---------------------------------------------------------------- */
$catId  = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;

/* ------------------------------------------------------------------
 * Fetch data in parallel using pub_fetch()
 * ---------------------------------------------------------------- */
$baseQs = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId;

// Banners (for animated slider)
$bannersResp = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId);
$banners     = $bannersResp['data']['data'] ?? $bannersResp['data'] ?? [];

// Categories
$catsResp   = pub_fetch($apiBase . 'public/categories?' . $baseQs . '&status=active&limit=20');
$categories = $catsResp['data']['data'] ?? ($catsResp['data']['items'] ?? []);

// Products
$prodQs = $baseQs . '&page=' . $page . '&limit=' . $limit . '&is_active=1';
if ($catId)   $prodQs .= '&category_id=' . $catId;
if ($search)  $prodQs .= '&search=' . urlencode($search);

$prodsResp  = pub_fetch($apiBase . 'public/products?' . $prodQs);
$products   = $prodsResp['data']['data'] ?? ($prodsResp['data']['items'] ?? []);
$prodMeta   = $prodsResp['data']['meta'] ?? [];
$total      = (int)($prodMeta['total'] ?? count($products));
$totalPages = (int)($prodMeta['total_pages'] ?? (($limit > 0 && $total > 0) ? ceil($total / $limit) : 1));

// Card styles
$_productCardStyle = pub_card_inline_style('product');
$_productCardClass = pub_card_css_class('product');
$_productImgStyle  = pub_card_img_style('product');
$_catCardStyle     = pub_card_inline_style('category');
$_catCardClass     = pub_card_css_class('category');

include dirname(__DIR__) . '/partials/header.php';
?>

<main class="pub-container" style="padding-top:24px;padding-bottom:56px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home', 'Home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('catalog.page_title', 'Catalog')) ?></span>
        <?php if ($catId): ?>
            <span style="margin:0 6px;">›</span>
            <span><?= e(t('catalog.filtered_by_category', 'Filtered by category')) ?></span>
        <?php endif; ?>
    </nav>

    <!-- ================================================================
         ANIMATED BANNER SLIDER
    ================================================================ -->
    <?php if (!empty($banners)): ?>
    <section class="catalog-slider-wrap" style="margin-bottom:32px;" aria-label="<?= e(t('slider.title', 'Promotions')) ?>">
        <div class="catalog-slider" id="catalogSlider">
            <div class="catalog-slider-track" id="catalogSliderTrack">
                <?php foreach ($banners as $i => $b): ?>
                <div class="catalog-slide<?= $i === 0 ? ' active' : '' ?>"
                     id="catalogSlide<?= $i ?>"
                     style="<?= !empty($b['background_color']) ? 'background:' . e($b['background_color']) . ';' : '' ?>">
                    <?php if (!empty($b['image_url'])): ?>
                    <a href="<?= e($b['link_url'] ?? '#') ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>">
                        <picture>
                            <?php if (!empty($b['mobile_image_url'])): ?>
                            <source media="(max-width:600px)" srcset="<?= e(pub_img($b['mobile_image_url'])) ?>">
                            <?php endif; ?>
                            <img src="<?= e(pub_img($b['image_url'])) ?>"
                                 alt="<?= e($b['title'] ?? '') ?>"
                                 class="catalog-slide-img"
                                 loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                        </picture>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($b['title']) || !empty($b['subtitle'])): ?>
                    <div class="catalog-slide-caption">
                        <?php if (!empty($b['title'])): ?>
                        <h2 class="catalog-slide-title"><?= e($b['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($b['subtitle'])): ?>
                        <p class="catalog-slide-sub"><?= e($b['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($b['link_url']) && !empty($b['link_text'])): ?>
                        <a href="<?= e($b['link_url']) ?>" class="pub-btn pub-btn--primary pub-btn--sm">
                            <?= e($b['link_text']) ?> →
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($banners) > 1): ?>
            <button class="catalog-arrow catalog-arrow-prev" id="catalogSliderPrev"
                    aria-label="<?= e(t('slider.prev', 'Previous')) ?>"
                    onclick="catalogSliderMove(-1)">‹</button>
            <button class="catalog-arrow catalog-arrow-next" id="catalogSliderNext"
                    aria-label="<?= e(t('slider.next', 'Next')) ?>"
                    onclick="catalogSliderMove(1)">›</button>
            <div class="catalog-dots" id="catalogDots">
                <?php foreach ($banners as $i => $b): ?>
                <button class="catalog-dot<?= $i === 0 ? ' active' : '' ?>"
                        aria-label="<?= e(t('slider.goto', 'Go to') . ' ' . ($i + 1)) ?>"
                        onclick="catalogSliderGoto(<?= $i ?>)"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ================================================================
         CATEGORIES
    ================================================================ -->
    <?php if (!empty($categories)): ?>
    <section class="pub-section" style="margin-bottom:36px;">
        <div class="pub-section-head" style="margin-bottom:16px;">
            <h2 class="pub-section-title"><?= e(t('sections.categories', 'Categories')) ?></h2>
            <a href="/frontend/public/categories.php" class="pub-section-link">
                <?= e(t('catalog.view_all_categories', 'View All')) ?> →
            </a>
        </div>
        <div class="catalog-cats-strip">
            <a href="/frontend/public/catalog.php<?= $search ? '?q=' . urlencode($search) : '' ?>"
               class="catalog-cat-pill<?= $catId === 0 ? ' active' : '' ?>">
                🗂️ <?= e(t('categories.all_categories', 'All')) ?>
            </a>
            <?php foreach ($categories as $cat): ?>
            <?php
                $catUrl = '/frontend/public/catalog.php?category_id=' . (int)($cat['id'] ?? 0);
                if ($search) $catUrl .= '&q=' . urlencode($search);
                $isActive = ($catId === (int)($cat['id'] ?? 0));
                $catImg   = !empty($cat['image_url']) ? pub_img($cat['image_url']) : '';
                $catName  = $cat['name'] ?? $cat['slug'] ?? 'Category';
            ?>
            <a href="<?= e($catUrl) ?>" class="catalog-cat-pill<?= $isActive ? ' active' : '' ?>"
               title="<?= e($catName) ?>">
                <?php if ($catImg): ?>
                <img src="<?= e($catImg) ?>" alt="<?= e($catName) ?>"
                     width="24" height="24" loading="lazy"
                     style="border-radius:50%;object-fit:cover;">
                <?php endif; ?>
                <?= e($catName) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ================================================================
         PRODUCTS
    ================================================================ -->
    <section>
        <div class="pub-section-head" style="margin-bottom:16px;">
            <h2 class="pub-section-title">
                🛍️ <?= e(t('nav.products', 'Products')) ?>
                <?php if ($total > 0): ?>
                <span style="font-size:0.8rem;font-weight:400;color:var(--pub-muted);">
                    (<?= number_format($total) ?>)
                </span>
                <?php endif; ?>
            </h2>
            <a href="/frontend/public/products.php" class="pub-section-link">
                <?= e(t('catalog.view_all_products', 'View All')) ?> →
            </a>
        </div>

        <!-- Search + filter bar -->
        <form method="get" class="pub-filter-bar" style="margin-bottom:20px;">
            <?php if ($catId): ?>
            <input type="hidden" name="category_id" value="<?= $catId ?>">
            <?php endif; ?>
            <input type="search" name="q" class="pub-search-input"
                   placeholder="<?= e(t('products.search_placeholder', 'Search products…')) ?>"
                   value="<?= e($search) ?>">
            <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm">
                🔍 <?= e(t('products.filter', 'Search')) ?>
            </button>
            <?php if ($search || $catId): ?>
            <a href="/frontend/public/catalog.php" class="pub-btn pub-btn--ghost pub-btn--sm">
                ✕ <?= e(t('products.clear', 'Clear')) ?>
            </a>
            <?php endif; ?>
        </form>

        <?php if (!empty($products)): ?>
        <div class="pub-product-grid">
            <?php foreach ($products as $p): ?>
            <?php
                $name   = $p['name'] ?? $p['slug'] ?? 'Product';
                $price  = $p['price'] ?? null;
                $curr   = $p['currency_code'] ?? '';
                $img    = !empty($p['image_url']) ? pub_img($p['image_url']) : '';
                $slug   = $p['slug'] ?? '';
                $pUrl   = '/frontend/public/product.php?slug=' . urlencode($slug);
                $badge  = ($p['is_featured'] ?? false) ? t('products.badge_featured', 'Featured') : '';
                $stock  = ($p['stock_status'] ?? 'in_stock') === 'out_of_stock';
            ?>
            <a href="<?= e($pUrl) ?>" class="pub-product-card <?= e($_productCardClass) ?>"
               style="<?= e($_productCardStyle) ?>" aria-label="<?= e($name) ?>">
                <div class="pub-product-img-wrap" style="<?= e($_productImgStyle) ?>">
                    <?php if ($img): ?>
                    <img src="<?= e($img) ?>" alt="<?= e($name) ?>" loading="lazy">
                    <?php else: ?>
                    <div class="pub-product-img-placeholder">🛍️</div>
                    <?php endif; ?>
                    <?php if ($badge): ?>
                    <span class="pub-badge pub-badge--featured"><?= e($badge) ?></span>
                    <?php endif; ?>
                    <?php if ($stock): ?>
                    <span class="pub-badge pub-badge--oos"><?= e(t('products.out_of_stock', 'Out of stock')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-info">
                    <p class="pub-product-name"><?= e($name) ?></p>
                    <?php if ($price !== null): ?>
                    <p class="pub-product-price">
                        <?= number_format((float)$price, 2) ?>
                        <?= $curr ? '<span class="pub-product-currency">' . e($curr) . '</span>' : '' ?>
                    </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="pub-pagination" style="margin-top:32px;" aria-label="<?= e(t('pagination.nav', 'Pagination')) ?>">
            <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
            <?php
                $pgQs = ['page' => $pg];
                if ($catId)  $pgQs['category_id'] = $catId;
                if ($search) $pgQs['q'] = $search;
                $pgUrl = '/frontend/public/catalog.php?' . http_build_query($pgQs);
            ?>
            <a href="<?= e($pgUrl) ?>"
               class="pub-page-btn<?= $pg === $page ? ' active' : '' ?>"
               aria-current="<?= $pg === $page ? 'page' : 'false' ?>"><?= $pg ?></a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="pub-empty" style="padding:48px 0;">
            <div class="pub-empty-icon">🛍️</div>
            <p class="pub-empty-msg">
                <?= e($search
                    ? t('catalog.no_results_for', 'No products found for') . ' "' . e($search) . '"'
                    : t('catalog.no_products', 'No products available in this category')) ?>
            </p>
            <?php if ($search || $catId): ?>
            <a href="/frontend/public/catalog.php" class="pub-btn pub-btn--outline" style="margin-top:12px;">
                <?= e(t('products.clear', 'Show all products')) ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

</main>

<!-- ================================================================
     SLIDER STYLES
================================================================ -->
<style>
/* ─── Animated Banner Slider ─────────────────────────────────── */
.catalog-slider-wrap { width: 100%; }
.catalog-slider {
    position: relative;
    border-radius: var(--pub-radius, 10px);
    overflow: hidden;
    background: var(--pub-surface, #1a2332);
    aspect-ratio: 16/5;
    min-height: 180px;
    max-width: 100%;
}
.catalog-slide {
    display: none;
    position: absolute;
    inset: 0;
    background: var(--pub-surface, #1a2332);
    animation: catalogFadeIn 0.6s ease;
}
.catalog-slide.active { display: block; }
@keyframes catalogFadeIn {
    from { opacity: 0; transform: scale(1.02); }
    to   { opacity: 1; transform: scale(1); }
}
.catalog-slide-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.catalog-slide-caption {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 20px 24px;
    background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 100%);
    color: #fff;
}
.catalog-slide-title {
    margin: 0 0 4px;
    font-size: clamp(1rem, 3vw, 1.6rem);
    font-weight: 800;
    text-shadow: 0 1px 4px rgba(0,0,0,0.5);
}
.catalog-slide-sub {
    margin: 0 0 10px;
    font-size: clamp(0.82rem, 2vw, 1rem);
    opacity: 0.9;
}
.catalog-arrow {
    position: absolute;
    top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.38);
    color: #fff; border: none;
    width: 40px; height: 40px;
    font-size: 1.5rem; cursor: pointer;
    border-radius: 50%; z-index: 5;
    transition: background 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.catalog-arrow:hover { background: rgba(0,0,0,0.65); }
.catalog-arrow-prev { left: 12px; }
.catalog-arrow-next { right: 12px; }
[dir="rtl"] .catalog-arrow-prev { left: auto; right: 12px; }
[dir="rtl"] .catalog-arrow-next { right: auto; left: 12px; }
.catalog-dots {
    position: absolute;
    bottom: 10px; left: 0; right: 0;
    display: flex; justify-content: center; gap: 6px; z-index: 5;
}
.catalog-dot {
    width: 9px; height: 9px; border-radius: 50%;
    border: none; background: rgba(255,255,255,0.45);
    cursor: pointer; padding: 0; transition: background 0.2s, transform 0.2s;
}
.catalog-dot.active { background: #fff; transform: scale(1.3); }

/* ─── Categories strip ───────────────────────────────────────── */
.catalog-cats-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.catalog-cat-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--pub-text, #e2e8f0);
    background: var(--pub-surface, #1a2332);
    border: 1.5px solid var(--pub-border, rgba(255,255,255,0.1));
    text-decoration: none;
    transition: background 0.18s, border-color 0.18s, color 0.18s;
    white-space: nowrap;
}
.catalog-cat-pill:hover,
.catalog-cat-pill.active {
    background: var(--pub-primary, #2d8cf0);
    border-color: var(--pub-primary, #2d8cf0);
    color: #fff;
}

/* ─── Pagination ─────────────────────────────────────────────── */
.pub-pagination {
    display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;
}
.pub-page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 36px; padding: 0 10px;
    border-radius: var(--pub-radius, 8px);
    font-size: 0.88rem; font-weight: 500;
    color: var(--pub-text, #e2e8f0);
    background: var(--pub-surface, #1a2332);
    border: 1.5px solid var(--pub-border, rgba(255,255,255,0.1));
    text-decoration: none;
    transition: background 0.18s, border-color 0.18s;
}
.pub-page-btn:hover,
.pub-page-btn.active {
    background: var(--pub-primary, #2d8cf0);
    border-color: var(--pub-primary, #2d8cf0);
    color: #fff;
}

@media (max-width: 480px) {
    .catalog-slider { aspect-ratio: 16/9; }
    .catalog-arrow { width: 32px; height: 32px; font-size: 1.2rem; }
}
</style>

<!-- ================================================================
     SLIDER JAVASCRIPT (autoplay every 5 s)
================================================================ -->
<script>
(function () {
    var current = 0;
    var slides  = document.querySelectorAll('.catalog-slide');
    var dots    = document.querySelectorAll('.catalog-dot');
    var total   = slides.length;
    var timer;

    function show(n) {
        if (total === 0) return;
        n = ((n % total) + total) % total;
        slides.forEach(function (s, i) { s.classList.toggle('active', i === n); });
        dots.forEach(function (d, i)   { d.classList.toggle('active', i === n); });
        current = n;
    }

    window.catalogSliderMove = function (delta) { clearInterval(timer); show(current + delta); startAuto(); };
    window.catalogSliderGoto = function (n)     { clearInterval(timer); show(n);               startAuto(); };

    function startAuto() {
        if (total <= 1) return;
        timer = setInterval(function () { show(current + 1); }, 5000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        startAuto();

        /* Touch / swipe support */
        var slider = document.getElementById('catalogSlider');
        if (!slider) return;
        var startX = 0;
        slider.addEventListener('touchstart', function (e) {
            startX = e.touches[0].clientX;
        }, { passive: true });
        slider.addEventListener('touchend', function (e) {
            var diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) {
                clearInterval(timer);
                show(current + (diff > 0 ? 1 : -1));
                startAuto();
            }
        });
    });
}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
