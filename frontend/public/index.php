<?php
declare(strict_types=1);
/**
 * frontend/public/index.php
 * QOOQZ — Global Public Homepage
 * Renders homepage sections dynamically from homepage_sections table.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$theme    = $ctx['theme'];
$tenantId = $ctx['tenant_id'];
$apiBase  = pub_api_url('');

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('hero.title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('hero.subtitle');

/* -------------------------------------------------------
 * Fetch homepage sections from DB
 * ----------------------------------------------------- */
$sectionsResp = pub_fetch($apiBase . 'public/homepage_sections?tenant_id=' . $tenantId . '&lang=' . urlencode($lang));
// ResponseFormatter wraps data in {success,message,data:{ok,data:[...]}}, so we need ['data']['data']
$sections     = $sectionsResp['data']['data'] ?? [];

/* -------------------------------------------------------
 * Helper: load section data based on section_type
 * data_source column in homepage_sections may contain a relative URL
 * like /categories?per_page=6 — we call our own API instead.
 * ----------------------------------------------------- */
$baseQs = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId;
function pub_section_data(string $type, int $limit, string $lang, int $tenantId, string $apiBase): array {
    $qs = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId . '&per=' . $limit . '&page=1';
    return match($type) {
        'categories'        => pub_fetch($apiBase . 'public/categories?'         . $qs . '&featured=1')['data']['data'] ?? [],
        'featured_products' => pub_fetch($apiBase . 'public/products?'           . $qs . '&is_featured=1')['data']['data'] ?? [],
        'new_products'      => pub_fetch($apiBase . 'public/products?'           . $qs . '&is_new=1')['data']['data'] ?? [],
        'slider','banners'  => pub_fetch($apiBase . 'public/banners?tenant_id='  . $tenantId)['data']['data'] ?? [],
        'deals'             => pub_fetch($apiBase . 'public/discounts?tenant_id='. $tenantId . '&lang=' . urlencode($lang))['data']['data'] ?? [],
        'brands'            => pub_fetch($apiBase . 'public/brands?'            . $qs . '&is_featured=1')['data']['data'] ?? [],
        'vendors'           => pub_fetch($apiBase . 'public/entities?'           . $qs . '&per=6')['data']['data'] ?? [],
        default             => [],
    };
}

/* -------------------------------------------------------
 * Pre-fetch stats (independent of sections)
 * ----------------------------------------------------- */
$rProd = pub_fetch($apiBase . 'public/products?lang=' . urlencode($lang) . '&per=1&page=1&tenant_id=' . $tenantId);
$rCat  = pub_fetch($apiBase . 'public/categories?lang=' . urlencode($lang) . '&per=1&tenant_id=' . $tenantId);
$rJob  = pub_fetch($apiBase . 'public/jobs?lang=' . urlencode($lang) . '&per=1');
$rEnt  = pub_fetch($apiBase . 'public/entities?lang=' . urlencode($lang) . '&per=1&tenant_id=' . $tenantId);
$rTen  = pub_fetch($apiBase . 'public/tenants?lang=' . urlencode($lang) . '&per=1');

$totalProducts  = (int)($rProd['data']['meta']['total'] ?? 0);
$totalCategories= (int)($rCat['data']['meta']['total']  ?? 0);
$totalJobs      = (int)($rJob['data']['meta']['total']  ?? 0);
$totalEntities  = (int)($rEnt['data']['meta']['total']  ?? 0);
$totalTenants   = (int)($rTen['data']['meta']['total']  ?? 0);

include dirname(__DIR__) . '/partials/header.php';

// Resolve card styles from DB card_styles table (no extra query — already loaded in theme)
$_cardEntity    = pub_card_inline_style('entities');
$_cardTenant    = pub_card_inline_style('tenants');
$_cardProduct   = pub_card_inline_style('product');
$_cardCategory  = pub_card_inline_style('category');
$_cardDeal      = pub_card_inline_style('promo');
$_cardJob       = pub_card_inline_style('feature');
// CSS class names for hover effects (generated from card_styles.hover_effect in pub_load_theme)
$_clsEntity     = pub_card_css_class('entities');
$_clsTenant     = pub_card_css_class('tenants');
$_clsProduct    = pub_card_css_class('product');
$_clsCategory   = pub_card_css_class('category');
$_clsDeal       = pub_card_css_class('promo');
$_clsJob        = pub_card_css_class('feature');
// Image-wrapper aspect ratios from card_styles.image_aspect_ratio
$_imgProduct    = pub_card_img_style('product');
$_imgCategory   = pub_card_img_style('category');
?>

<!-- =============================================
     HERO
============================================= -->
<section class="pub-hero">
    <div class="pub-container">
        <h1><?= e(t('hero.title')) ?></h1>
        <p><?= e(t('hero.subtitle')) ?></p>
        <div class="pub-hero-actions">
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary">
                🛍️ <?= e(t('hero.browse_products')) ?>
            </a>
            <a href="/frontend/public/jobs.php" class="pub-btn pub-btn--outline">
                💼 <?= e(t('hero.explore_jobs')) ?>
            </a>
        </div>
    </div>
</section>

<!-- =============================================
     SEARCH BAR
============================================= -->
<div class="pub-search-bar">
    <div class="pub-container">
        <form class="pub-search-form" method="get" action="/frontend/public/products.php" id="pubSearchForm">
            <input type="search" name="q" class="pub-search-input"
                   placeholder="<?= e(t('search.placeholder')) ?>"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" class="pub-search-btn"><?= e(t('search.button')) ?></button>
        </form>
    </div>
</div>

<!-- =============================================
     STATS ROW
============================================= -->
<div class="pub-container">
    <div class="pub-stats-row">
        <div class="pub-stat-item">
            <span class="pub-stat-value"><?= number_format($totalProducts) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.products')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value"><?= number_format($totalCategories) ?>+</span>
            <span class="pub-stat-label"><?= e(t('nav.categories')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value"><?= number_format($totalJobs) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.jobs')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value"><?= number_format($totalEntities) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.entities')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value"><?= number_format($totalTenants) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.tenants')) ?></span>
        </div>
    </div>
</div>

<div id="pub-homepage-sections">
<?php if (!empty($sections)):
    foreach ($sections as $sec):
        $secType   = $sec['section_type'] ?? '';
        $layout    = $sec['layout_type']  ?? 'grid';
        $perRow    = max(1, (int)($sec['items_per_row'] ?? 4));
        $secBg     = $sec['background_color'] ?? '';
        $secText   = $sec['text_color'] ?? '';
        $secPad    = $sec['padding'] ?? '36px 0';
        $secTitle  = $sec['title']    ?? '';
        $secSub    = $sec['subtitle'] ?? '';
        $items     = pub_section_data($secType, $perRow * 2, $lang, $tenantId, $apiBase);

        if (empty($items)) continue;

        $sStyle = '';
        if ($secBg)   $sStyle .= 'background:' . e($secBg)   . ';';
        if ($secText) $sStyle .= 'color:'       . e($secText) . ';';
        if ($secPad)  $sStyle .= 'padding:'     . e($secPad)  . ';';
?>
<!-- Section: <?= e($secType) ?> -->
<section class="pub-section" style="<?= $sStyle ?>">
    <div class="pub-container">
        <?php if ($secTitle): ?>
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e($secTitle) ?></h2>
            <?php
            $viewAllLink = match($secType) {
                'categories'        => '/frontend/public/categories.php',
                'featured_products',
                'new_products',
                'deals'             => '/frontend/public/products.php',
                'brands','vendors'  => '/frontend/public/entities.php',
                default             => '',
            };
            if ($viewAllLink): ?>
            <a href="<?= e($viewAllLink) ?>" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
            <?php endif; ?>
        </div>
        <?php if ($secSub): ?><p class="pub-section-sub"><?= e($secSub) ?></p><?php endif; ?>
        <?php endif; ?>

        <?php
        /* ---- Slider / Banner section ---- */
        if ($secType === 'slider' || $secType === 'banners'): ?>
        <div class="pub-banner-slider">
            <?php foreach ($items as $b): ?>
            <div class="pub-banner-slide"<?php if (!empty($b['background_color'])): ?> style="background:<?= e($b['background_color']) ?>"<?php endif; ?>>
                <?php if (!empty($b['image_url'])): ?>
                <a href="<?= e($b['link_url'] ?? '#') ?>">
                    <img src="<?= e(pub_img($b['image_url'])) ?>"
                         alt="<?= e($b['title'] ?? '') ?>" class="pub-banner-img" loading="lazy">
                </a>
                <?php endif; ?>
                <?php if (!empty($b['title'])): ?>
                <div class="pub-banner-caption">
                    <h3><?= e($b['title']) ?></h3>
                    <?php if (!empty($b['subtitle'])): ?><p><?= e($b['subtitle']) ?></p><?php endif; ?>
                    <?php if (!empty($b['link_url']) && !empty($b['link_text'])): ?>
                    <a href="<?= e($b['link_url']) ?>" class="pub-btn pub-btn--primary pub-btn--sm"><?= e($b['link_text']) ?></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php /* ---- Deals / Discounts section ---- */
        elseif ($secType === 'deals'): ?>
        <div class="pub-grid-lg">
            <?php foreach ($items as $deal): ?>
            <div class="pub-deal-card<?= $_clsDeal ? ' '.$_clsDeal : '' ?>"<?= $_cardDeal ? ' style="'.e($_cardDeal).'"' : '' ?>>
                <?php if (!empty($deal['code'])): ?>
                    <span class="pub-deal-badge"><?= e($deal['code']) ?></span>
                <?php endif; ?>
                <p class="pub-deal-title"><?= e($deal['title'] ?? $deal['code'] ?? '') ?></p>
                <?php if (!empty($deal['description'])): ?>
                    <p class="pub-deal-desc"><?= e($deal['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($deal['ends_at'])): ?>
                    <p class="pub-deal-expires">⏰ <?= e(t('deals.expires')) ?>: <?= e(substr($deal['ends_at'], 0, 10)) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php /* ---- Categories section ---- */
        elseif ($secType === 'categories'): ?>
        <div class="pub-grid-cat">
            <?php foreach ($items as $cat): ?>
            <a href="/frontend/public/products.php?category_id=<?= (int)($cat['id'] ?? 0) ?>"
               class="pub-cat-card<?= !empty($cat['is_featured']) ? ' pub-cat-card--featured' : '' ?><?= $_clsCategory ? ' '.$_clsCategory : '' ?>"
               style="text-decoration:none;<?= e($_cardCategory) ?>">
                <div class="pub-cat-img-wrap" style="<?= e($_imgCategory) ?>">
                    <?php if (!empty($cat['image_url'])): ?>
                        <img src="<?= e(pub_img($cat['image_url'], 'category')) ?>"
                             alt="<?= e($cat['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">📂</span>
                    <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true">📂</span>
                    <?php endif; ?>
                </div>
                <div class="pub-cat-body">
                    <h3 class="pub-cat-name"><?= e($cat['name'] ?? '') ?></h3>
                    <?php if (!empty($cat['product_count'])): ?>
                        <span class="pub-cat-count"><?= (int)$cat['product_count'] ?> <?= e(t('categories.products_count')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php /* ---- Products (featured / new) ---- */
        elseif (in_array($secType, ['featured_products', 'new_products'], true)): ?>
        <div class="pub-grid">
            <?php foreach ($items as $p): ?>
            <?php
                $pId2   = (int)($p['id'] ?? 0);
                $pName2 = $p['name'] ?? '';
                $pPrice2= $p['price'] ?? null;
                $pCur2  = $p['currency_code'] ?? t('common.currency');
                $pImg2  = pub_img($p['image_url'] ?? $p['image_thumb_url'] ?? null, 'product');
            ?>
            <div class="pub-product-card<?= $_clsProduct ? ' '.$_clsProduct : '' ?>" style="position:relative;<?= e($_cardProduct) ?>">
                <!-- Wishlist heart -->
                <button class="pub-wishlist-btn"
                        type="button"
                        data-product-id="<?= $pId2 ?>"
                        data-entity-id="<?= $p['entity_id'] ?? 1 ?>"
                        onclick="pubToggleWishlist(this)"
                        title="<?= e(t('wishlist.add')) ?>">♡</button>
                <a href="/frontend/public/product.php?id=<?= $pId2 ?>"
                   style="text-decoration:none;display:flex;flex-direction:column;flex:1;"
                   aria-label="<?= e($pName2) ?>">
                    <div class="pub-cat-img-wrap" style="<?= e($_imgProduct) ?>">
                        <?php if ($pImg2): ?>
                            <img src="<?= e($pImg2) ?>"
                                 alt="<?= e($pName2) ?>" class="pub-cat-img" loading="lazy"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🖼️</span>
                        <?php else: ?>
                            <span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                        <?php endif; ?>
                    </div>
                    <div class="pub-product-card-body">
                        <?php if (!empty($p['is_featured'])): ?>
                            <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                        <?php endif; ?>
                        <p class="pub-product-name"><?= e($pName2) ?></p>
                        <?php if ($pPrice2 !== null): ?>
                            <p class="pub-product-price">
                                <?= number_format((float)$pPrice2, 2) ?>
                                <small><?= e($pCur2) ?></small>
                            </p>
                        <?php endif; ?>
                    </div>
                </a>
                <div style="padding:0 14px 12px;">
                    <button class="pub-btn pub-btn--primary pub-btn--sm"
                            style="width:100%;justify-content:center;"
                            type="button"
                            data-product-id="<?= $pId2 ?>"
                            data-product-name="<?= e($pName2) ?>"
                            data-product-price="<?= e((string)($pPrice2 ?? '0')) ?>"
                            data-product-image="<?= e($pImg2 ?: '') ?>"
                            data-product-sku="<?= e($p['sku'] ?? '') ?>"
                            data-currency="<?= e($pCur2) ?>"
                            data-added-text="✅ <?= e(t('cart.added')) ?>"
                            onclick="pubAddToCart(this)">
                        🛒 <?= e(t('cart.add')) ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php /* ---- Brands / Vendors ---- */
        elseif (in_array($secType, ['brands', 'vendors'], true)): ?>
        <div class="pub-grid-md">
            <?php foreach ($items as $ent):
                $entCardStyle = pub_entity_card_style($ent, $_cardEntity);
            ?>
            <a href="/frontend/public/entity.php?id=<?= (int)($ent['id'] ?? 0) ?>"
               class="pub-entity-card<?= $_clsEntity ? ' '.$_clsEntity : '' ?>" style="text-decoration:none;<?= e($entCardStyle) ?>">
                <div class="pub-entity-avatar">
                    <?php if (!empty($ent['logo_url'])): ?>
                        <img src="<?= e(pub_img($ent['logo_url'], 'entity_logo')) ?>"
                             alt="<?= e($ent['store_name'] ?? '') ?>" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span style="display:none;">🏢</span>
                    <?php else: ?>
                        🏢
                    <?php endif; ?>
                </div>
                <div class="pub-entity-info">
                    <p class="pub-entity-name"><?= e($ent['store_name'] ?? $ent['name'] ?? '') ?></p>
                    <?php if (!empty($ent['vendor_type'])): ?>
                        <p class="pub-entity-desc"><?= e($ent['vendor_type']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($ent['is_verified'])): ?>
                        <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>
    <?php endforeach;
else:
    /* ---- Fallback: no homepage_sections in DB → render default static sections ---- */
    $rp  = pub_fetch($apiBase . 'public/products?'  . $baseQs . '&per=8&is_featured=1');
    $rc  = pub_fetch($apiBase . 'public/categories?' . $baseQs . '&per=8&featured=1');
    $rj  = pub_fetch($apiBase . 'public/jobs?lang='  . urlencode($lang) . '&per=6&is_featured=1');
    $re  = pub_fetch($apiBase . 'public/entities?'   . $baseQs . '&per=6');
    $rt  = pub_fetch($apiBase . 'public/tenants?'    . $baseQs . '&per=6');

    $featuredProducts  = $rp['data']['data'] ?? [];
    $featuredCategories= $rc['data']['data'] ?? [];
    $latestJobs        = $rj['data']['data'] ?? [];
    $featuredEntities  = $re['data']['data'] ?? [];
    $featuredTenants   = $rt['data']['data'] ?? [];
?>
<?php if (!empty($featuredCategories)): ?>
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.categories')) ?></h2>
            <a href="/frontend/public/categories.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-cat">
            <?php foreach ($featuredCategories as $cat): ?>
            <a href="/frontend/public/products.php?category_id=<?= (int)($cat['id'] ?? 0) ?>"
               class="pub-cat-card<?= !empty($cat['is_featured']) ? ' pub-cat-card--featured' : '' ?><?= $_clsCategory ? ' '.$_clsCategory : '' ?>"
               style="text-decoration:none;<?= e($_cardCategory) ?>">
                <div class="pub-cat-img-wrap" style="<?= e($_imgCategory) ?>">
                    <?php if (!empty($cat['image_url'])): ?>
                        <img src="<?= e(pub_img($cat['image_url'], 'category')) ?>"
                             alt="<?= e($cat['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">📂</span>
                    <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true">📂</span>
                    <?php endif; ?>
                </div>
                <div class="pub-cat-body">
                    <h3 class="pub-cat-name"><?= e($cat['name'] ?? '') ?></h3>
                    <?php if (!empty($cat['product_count'])): ?>
                        <span class="pub-cat-count"><?= (int)$cat['product_count'] ?> <?= e(t('categories.products_count')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredProducts)): ?>
<section class="pub-section"<?= !empty($theme['surface']) ? ' style="background:'.e($theme['surface']).';"' : '' ?>>
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.featured_products')) ?></h2>
            <a href="/frontend/public/products.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid">
            <?php foreach ($featuredProducts as $p): ?>
            <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
               class="pub-product-card<?= $_clsProduct ? ' '.$_clsProduct : '' ?>" style="text-decoration:none;<?= e($_cardProduct) ?>">
                <div class="pub-cat-img-wrap" style="<?= e($_imgProduct) ?>">
                    <?php if (!empty($p['image_url'])): ?>
                        <img src="<?= e(pub_img($p['image_url'], 'product')) ?>"
                             alt="<?= e($p['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🖼️</span>
                    <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-card-body">
                    <?php if (!empty($p['is_featured'])): ?>
                        <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                        <p class="pub-product-price">
                            <?= number_format((float)$p['price'], 2) ?>
                            <?= e($p['currency_code'] ?? t('common.currency')) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($latestJobs)): ?>
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.latest_jobs')) ?></h2>
            <a href="/frontend/public/jobs.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-lg">
            <?php foreach ($latestJobs as $j): ?>
            <a href="/frontend/public/jobs.php?id=<?= (int)($j['id'] ?? 0) ?>"
               class="pub-job-card<?= $_clsJob ? ' '.$_clsJob : '' ?>" style="text-decoration:none;<?= e($_cardJob) ?>">
                <h3 class="pub-job-title"><?= e($j['title'] ?? '') ?></h3>
                <div class="pub-job-meta">
                    <?php if (!empty($j['employment_type'])): ?>
                        <span>🕐 <?= e(str_replace('_', ' ', $j['employment_type'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="pub-job-tags">
                    <?php if (!empty($j['is_featured'])): ?><span class="pub-tag pub-tag--featured"><?= e(t('jobs.featured')) ?></span><?php endif; ?>
                    <?php if (!empty($j['is_urgent'])): ?><span class="pub-tag pub-tag--urgent"><?= e(t('jobs.urgent')) ?></span><?php endif; ?>
                    <?php if (!empty($j['is_remote'])): ?><span class="pub-tag pub-tag--remote"><?= e(t('jobs.remote')) ?></span><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredEntities)): ?>
<section class="pub-section"<?= !empty($theme['surface']) ? ' style="background:'.e($theme['surface']).';"' : '' ?>>
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.entities')) ?></h2>
            <a href="/frontend/public/entities.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-md">
            <?php foreach ($featuredEntities as $ent):
                $entCardStyle = pub_entity_card_style($ent, $_cardEntity);
            ?>
            <a href="/frontend/public/entity.php?id=<?= (int)($ent['id'] ?? 0) ?>"
               class="pub-entity-card<?= $_clsEntity ? ' '.$_clsEntity : '' ?>" style="text-decoration:none;<?= e($entCardStyle) ?>">
                <div class="pub-entity-avatar">
                    <?php if (!empty($ent['logo_url'])): ?>
                        <img src="<?= e(pub_img($ent['logo_url'], 'entity_logo')) ?>"
                             alt="<?= e($ent['store_name'] ?? '') ?>" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span style="display:none;">🏢</span>
                    <?php else: ?>
                        🏢
                    <?php endif; ?>
                </div>
                <div class="pub-entity-info">
                    <p class="pub-entity-name"><?= e($ent['store_name'] ?? $ent['name'] ?? '') ?></p>
                    <?php if (!empty($ent['vendor_type'])): ?>
                        <p class="pub-entity-desc"><?= e($ent['vendor_type']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($ent['is_verified'])): ?>
                        <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($featuredTenants)): ?>
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.tenants')) ?></h2>
            <a href="/frontend/public/tenants.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-md">
            <?php foreach ($featuredTenants as $ten): ?>
            <a href="/frontend/public/tenants.php?id=<?= (int)($ten['id'] ?? 0) ?>"
               class="pub-entity-card<?= $_clsTenant ? ' '.$_clsTenant : '' ?>" style="text-decoration:none;<?= e($_cardTenant) ?>">
                <div class="pub-entity-avatar">🏪</div>
                <div class="pub-entity-info">
                    <p class="pub-entity-name"><?= e($ten['name'] ?? '') ?></p>
                    <?php if (!empty($ten['domain'])): ?>
                        <p class="pub-entity-desc"><?= e($ten['domain']) ?></p>
                    <?php endif; ?>
                    <?php if (($ten['status'] ?? '') === 'active'): ?>
                        <span class="pub-entity-verified">🟢 <?= e(t('tenants.active')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php endif; /* end sections/fallback */ ?>
</div><!-- #pub-homepage-sections -->

<script>
if (typeof PubHomepageEngine !== 'undefined') {
    PubHomepageEngine.init(<?= (int)$tenantId ?>, '<?= e($lang) ?>');
}
</script>
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>