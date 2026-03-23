<?php
/**
 * frontend/public/product.php
 * QOOQZ — Product Detail Page
 * Shows full product info: gallery, description, price, brand, categories, related products.
 * Uses direct PDO queries (not HTTP loopback) for reliability on all hosting environments.
 * Note: No declare(strict_types=1) — PDO FETCH_ASSOC returns string values for all columns
 *       and strict typing causes TypeErrors with int/float comparisons in production.
 */

// Top-level exception handler — prevents HTTP 500 even if unexpected error occurs.
// Logs the error for server-side debugging and shows a friendly page.
set_exception_handler(function (Throwable $ex) {
    error_log('[product.php] Uncaught exception: ' . $ex->getMessage() . ' at ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) { http_response_code(200); }
    echo '<p style="padding:40px;text-align:center;font-family:sans-serif;">⚠️ Unable to load product. Please try again later.</p>';
    exit;
});

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];

// Accept id or slug
$productId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$productSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (!$productId && $productSlug === '') {
    header('Location: /frontend/public/products.php');
    exit;
}

// -------------------------------------------------------
// Load product — PDO first (direct DB, proven working on live server per debug_product.php Step 3).
// HTTP API as fallback for when PDO path fails.
// -------------------------------------------------------
$product    = null;
$images     = [];
$categories = [];
$related    = [];
$variants   = [];
$reviews    = [];
$questions  = [];
$relations  = [];

// Step 1: PDO (direct DB) — proven working by debug_product.php Step 3.
$pdo = pub_get_pdo();
if (!$product && $pdo) {
    try {
        // Resolve slug → id
        if (!$productId && $productSlug !== '') {
            $params = [$productSlug];
            $cond   = ' AND is_active = 1';
            $st = $pdo->prepare('SELECT id FROM products WHERE slug = ?' . $cond . ' LIMIT 1');
            $st->execute($params);
            $r = $st->fetch();
            if ($r) $productId = (int)$r['id'];
        }

        if ($productId) {
            $qParams = [$lang, $productId];
            $st = $pdo->prepare(
                "SELECT p.id, p.sku, p.slug, p.barcode, p.brand_id,
                        p.is_active, p.is_featured, p.is_new, p.is_bestseller,
                        p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                        p.views_count, p.tenant_id,
                        COALESCE(pt.name, p.slug) AS name,
                        pt.short_description, pt.description, pt.specifications,
                        (SELECT pp.price FROM product_pricing pp
                           WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                        NULL AS compare_at_price,
                        (SELECT pp.currency_code FROM product_pricing pp
                           WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                        NULL AS brand_name,
                        (SELECT i.url FROM images i WHERE i.owner_id = p.id
                           ORDER BY i.id ASC LIMIT 1) AS image_url,
                        NULL AS image_thumb_url
                   FROM products p
              LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
                  WHERE p.id = ?"
            );
            $st->execute($qParams);
            $product = $st->fetch() ?: null;

            if ($product) {
                // Gallery images — wrapped in own try-catch so missing columns
                // (thumb_url, alt_text, is_main) never null out $product.
                try {
                    $st = $pdo->prepare(
                        "SELECT i.id, i.url
                           FROM images i
                          WHERE i.owner_id = ?
                          ORDER BY i.id ASC LIMIT 10"
                    );
                    $st->execute([$productId]);
                    $rawImgs = $st->fetchAll();
                    // Try to enrich with optional columns (thumb_url, alt_text, is_main)
                    $images = [];
                    foreach ($rawImgs as $img) {
                        $images[] = [
                            'id'        => $img['id'],
                            'url'       => $img['url'],
                            'thumb_url' => $img['thumb_url'] ?? $img['url'],
                            'alt_text'  => $img['alt_text'] ?? '',
                        ];
                    }
                    // Also set image_url and image_thumb_url on product if not already set
                    if (!empty($images) && empty($product['image_url'])) {
                        $product['image_url']       = $images[0]['url'];
                        $product['image_thumb_url'] = $images[0]['thumb_url'];
                    } elseif (!empty($product['image_url']) && empty($product['image_thumb_url'])) {
                        $product['image_thumb_url'] = $product['image_url'];
                    }
                } catch (Throwable $_) { $images = []; }

                // Categories — own try-catch for same reason
                try {
                    $st = $pdo->prepare(
                        "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug
                           FROM categories c
                     INNER JOIN product_categories pc ON pc.category_id = c.id AND pc.product_id = ?
                      LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
                          LIMIT 5"
                    );
                    $st->execute([$productId, $lang]);
                    $categories = $st->fetchAll();
                } catch (Throwable $_) { $categories = []; }

                // Variants — load active variants with their pricing
                try {
                    $st = $pdo->prepare(
                        "SELECT pv.id, pv.sku, pv.stock_quantity, pv.is_default,
                                (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = ? AND pp.variant_id = pv.id ORDER BY pp.id ASC LIMIT 1) AS price,
                                (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = ? AND pp.variant_id = pv.id ORDER BY pp.id ASC LIMIT 1) AS currency_code
                           FROM product_variants pv
                          WHERE pv.product_id = ? AND pv.is_active = 1
                          ORDER BY pv.is_default DESC, pv.id ASC LIMIT 20"
                    );
                    $st->execute([$productId, $productId, $productId]);
                    $variants = $st->fetchAll();
                } catch (Throwable $_) {
                    $variants = [];
                }

                // Related products (same first category)
                // Uses scalar subqueries for price — avoids JOIN on pp2.variant_id which
                // may not exist in all product_pricing schema versions.
                if (!empty($categories[0]['id'])) {
                    try {
                        $st = $pdo->prepare(
                            "SELECT p2.id, COALESCE(pt2.name, p2.slug) AS name, p2.slug,
                                    p2.stock_status, p2.stock_quantity,
                                    (SELECT pp2.price FROM product_pricing pp2
                                       WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS price,
                                    (SELECT pp2.currency_code FROM product_pricing pp2
                                       WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS currency_code,
                                    (SELECT i2.url FROM images i2 WHERE i2.owner_id = p2.id
                                       ORDER BY i2.id ASC LIMIT 1) AS image_url
                               FROM products p2
                         INNER JOIN product_categories pc2 ON pc2.product_id = p2.id AND pc2.category_id = ?
                          LEFT JOIN product_translations pt2 ON pt2.product_id = p2.id AND pt2.language_code = ?
                              WHERE p2.is_active = 1 AND p2.id != ?
                              ORDER BY p2.is_featured DESC, p2.id DESC LIMIT 8"
                        );
                        $st->execute([(int)$categories[0]['id'], $lang, $productId]);
                        $related = $st->fetchAll();
                    } catch (Throwable $_) {
                        $related = []; // non-critical: related products failing must not affect main product
                    }
                }

                // Auto-record recently viewed (fire and forget — non-fatal)
                try {
                    $rvUid = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
                    $rvSid = session_id() ?: null;
                    $pdo->prepare(
                        'INSERT INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
                         VALUES (?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE viewed_at = NOW()'
                    )->execute([$rvUid, $rvSid, $productId]);
                } catch (Throwable $_) {
                    try {
                        $rvUid2 = $_SESSION['user_id'] ?? null;
                        $pdo->prepare(
                            'INSERT IGNORE INTO recently_viewed_products (user_id, session_id, product_id, viewed_at)
                             VALUES (?, ?, ?, NOW())'
                        )->execute([$rvUid2, session_id() ?: null, $productId]);
                    } catch (Throwable $__) { /* non-fatal */ }
                }

                // Reviews — approved only
                try {
                    $st = $pdo->prepare(
                        "SELECT r.id, r.rating, r.title, r.comment, r.is_verified_purchase,
                                r.helpful_count, r.created_at,
                                COALESCE(u.name, u.username, 'User') AS author
                           FROM product_reviews r
                      LEFT JOIN users u ON u.id = r.user_id
                          WHERE r.product_id = ? AND r.is_approved = 1
                          ORDER BY r.helpful_count DESC, r.created_at DESC LIMIT 30"
                    );
                    $st->execute([$productId]);
                    $reviews = $st->fetchAll();
                } catch (Throwable $_) { $reviews = []; }

                // Q&A — approved questions + approved answers
                try {
                    $st = $pdo->prepare(
                        "SELECT q.id, q.question, q.helpful_count, q.created_at,
                                COALESCE(uq.name, uq.username, 'User') AS asker
                           FROM product_questions q
                      LEFT JOIN users uq ON uq.id = q.user_id
                          WHERE q.product_id = ? AND q.is_approved = 1
                          ORDER BY q.helpful_count DESC, q.created_at DESC LIMIT 20"
                    );
                    $st->execute([$productId]);
                    $questions = $st->fetchAll();
                    foreach ($questions as &$qRow) {
                        $sta = $pdo->prepare(
                            "SELECT a.id, a.answer, a.is_staff_answer, a.helpful_count, a.created_at,
                                    COALESCE(ua.name, ua.username, 'User') AS answerer
                               FROM product_answers a
                          LEFT JOIN users ua ON ua.id = a.user_id
                              WHERE a.question_id = ? AND a.is_approved = 1
                              ORDER BY a.is_staff_answer DESC, a.helpful_count DESC LIMIT 5"
                        );
                        $sta->execute([(int)$qRow['id']]);
                        $qRow['answers'] = $sta->fetchAll();
                    }
                    unset($qRow);
                } catch (Throwable $_) { $questions = []; }

                // Product relations (upsell/cross_sell/accessory/alternative)
                try {
                    $st = $pdo->prepare(
                        "SELECT pr.relation_type,
                                p2.id, COALESCE(pt2.name, p2.slug) AS name, p2.slug, p2.stock_status,
                                (SELECT pp2.price FROM product_pricing pp2 WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS price,
                                (SELECT pp2.currency_code FROM product_pricing pp2 WHERE pp2.product_id = p2.id ORDER BY pp2.id ASC LIMIT 1) AS currency_code,
                                (SELECT i2.url FROM images i2 WHERE i2.owner_id = p2.id ORDER BY i2.id ASC LIMIT 1) AS image_url
                           FROM product_relations pr
                      INNER JOIN products p2 ON p2.id = pr.related_product_id AND p2.is_active = 1
                       LEFT JOIN product_translations pt2 ON pt2.product_id = p2.id AND pt2.language_code = ?
                          WHERE pr.product_id = ?
                          ORDER BY pr.sort_order ASC, p2.id ASC LIMIT 20"
                    );
                    $st->execute([$lang, $productId]);
                    $relations = $st->fetchAll();
                } catch (Throwable $_) { $relations = []; }
            }
        }
    } catch (Throwable $e) {
        error_log('[product.php] PDO product load failed: ' . $e->getMessage());
        $product = null;
    }
}

// Step 2: HTTP API fallback — if PDO fails or returns null, try the public API.
if (!$product) {
    $_apiQs = http_build_query(array_filter([
        'id'   => $productId ?: null,
        'slug' => $productSlug ?: null,
        'lang' => $lang,
    ]));
    $_apiResp = pub_fetch(pub_api_url('public/products') . '?' . $_apiQs);
    if (!empty($_apiResp['data']['product'])) {
        $product    = $_apiResp['data']['product'];
        $images     = $_apiResp['data']['images']     ?? [];
        $categories = $_apiResp['data']['categories'] ?? [];
        $related    = $_apiResp['data']['related']    ?? [];
        if (!$productId && !empty($product['id'])) {
            $productId = (int)$product['id'];
        }
    }
}

// Step 3: Load secondary data (variants, reviews, Q&A) via PDO when available.
if ($product && $pdo && $productId) {
    // Variants
    if (empty($variants)) {
        try {
            $st = $pdo->prepare(
                "SELECT pv.id, pv.sku, pv.stock_quantity, pv.is_default,
                        (SELECT pp.price FROM product_pricing pp WHERE pp.product_id = ? AND pp.variant_id = pv.id ORDER BY pp.id ASC LIMIT 1) AS price,
                        (SELECT pp.currency_code FROM product_pricing pp WHERE pp.product_id = ? AND pp.variant_id = pv.id ORDER BY pp.id ASC LIMIT 1) AS currency_code
                   FROM product_variants pv
                  WHERE pv.product_id = ? AND pv.is_active = 1
                  ORDER BY pv.is_default DESC, pv.id ASC LIMIT 20"
            );
            $st->execute([$productId, $productId, $productId]);
            $variants = $st->fetchAll();
        } catch (Throwable $_) { $variants = []; }
    }
    // Reviews
    if (empty($reviews)) {
        try {
            $st = $pdo->prepare(
                "SELECT r.id, r.rating, r.title, r.comment, r.is_verified_purchase,
                        r.helpful_count, r.created_at,
                        COALESCE(u.name, u.username, 'User') AS author
                   FROM product_reviews r
              LEFT JOIN users u ON u.id = r.user_id
                  WHERE r.product_id = ? AND r.is_approved = 1
                  ORDER BY r.helpful_count DESC, r.created_at DESC LIMIT 30"
            );
            $st->execute([$productId]);
            $reviews = $st->fetchAll();
        } catch (Throwable $_) { $reviews = []; }
    }
}

$productName  = $product['name'] ?? $product['slug'] ?? '';
$productDesc  = $product['description'] ?? $product['short_description'] ?? '';
$price        = $product['price'] ?? null;
$comparePrice = $product['compare_at_price'] ?? null;
$currency     = $product['currency_code'] ?? '';
$stockStatus  = $product['stock_status'] ?? '';
$stockQty     = (int)($product['stock_quantity'] ?? 0);
$mainImage    = $product['image_url'] ?? '';
$thumbImage   = $product['image_thumb_url'] ?? $mainImage;
$brandName    = $product['brand_name'] ?? '';
$specs        = $product['specifications'] ?? '';
$isFeatured   = !empty($product['is_featured']);
$isNew        = !empty($product['is_new']);
$isBestseller = !empty($product['is_bestseller']);

// Build gallery — always include main image first
if (empty($images) && $mainImage) {
    $images = [['url' => $mainImage, 'thumb_url' => $thumbImage, 'alt_text' => $productName]];
}

$inStock = in_array($stockStatus, ['in_stock', ''], true);

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';

// Show a "not found" page if product is still null (no redirect — user sees 404)
if (!$product) {
    $GLOBALS['PUB_PAGE_TITLE'] = e(t('products.not_found_title', ['default' => 'Product Not Found'])) . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<main class="pub-container" style="padding:60px 0;text-align:center;">';
    echo '<div class="pub-empty-icon" style="font-size:4rem;">🔍</div>';
    echo '<h1 style="margin:16px 0 8px;">' . e(t('products.not_found_title', ['default' => 'Product Not Found'])) . '</h1>';
    echo '<p style="color:var(--pub-muted);">' . e(t('products.not_found_msg', ['default' => 'This product is unavailable or does not exist.'])) . '</p>';
    echo '<a href="/frontend/public/products.php" class="pub-btn pub-btn--primary" style="margin-top:24px;display:inline-block;">'
       . e(t('nav.products')) . '</a>';
    echo '</main>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$GLOBALS['PUB_PAGE_TITLE'] = e($productName) . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = strip_tags($productDesc ?: $productName);

// Load SEO meta from seo_meta table (if admin has configured it for this product)
$_seoMeta = pub_get_seo_meta('product', $productId, $lang);
if (!empty($_seoMeta['title']))       $GLOBALS['PUB_PAGE_TITLE'] = e($_seoMeta['title']);
if (!empty($_seoMeta['description'])) $GLOBALS['PUB_PAGE_DESC']  = e($_seoMeta['description']);
$GLOBALS['PUB_SEO'] = [
    'canonical_url'  => $_seoMeta['canonical_url']  ?? '',
    'robots'         => $_seoMeta['robots']         ?? 'index,follow',
    'keywords'       => $_seoMeta['keywords']       ?? implode(',', array_column($categories, 'name')),
    'og_title'       => $_seoMeta['og_title']       ?? $productName,
    'og_description' => $_seoMeta['og_description'] ?? strip_tags($productDesc ?: ''),
    'og_image'       => $_seoMeta['og_image']       ?? $mainImage,
    'og_type'        => 'product',
    'schema_markup'  => $_seoMeta['schema_markup']  ?? '',
    // Product-specific schema data (used by header.php for JSON-LD if schema_markup is empty)
    'schema_type'    => 'Product',
    'schema_name'    => $productName,
    'schema_image'   => $mainImage ? pub_img($mainImage) : '',
    'schema_description' => strip_tags($productDesc ?: ''),
    'schema_sku'     => $product['sku'] ?? '',
    'schema_price'   => $price,
    'schema_currency'=> $currency,
    'schema_availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    'schema_rating'  => !empty($product['rating_count']) ? [
        'ratingValue' => number_format((float)($product['rating_average'] ?? 0), 1),
        'reviewCount' => (int)($product['rating_count'] ?? 0),
    ] : null,
];

include dirname(__DIR__) . '/partials/header.php';

// Resolve product card style from DB card_styles (card_type='product')
$_productCardStyle = pub_card_inline_style('product');
$_productCardClass = pub_card_css_class('product');
$_productImgStyle  = pub_card_img_style('product');
?>

<!-- Breadcrumb -->
<div class="pub-search-bar" style="padding:10px 0;">
    <div class="pub-container">
        <nav aria-label="breadcrumb" style="font-size:0.85rem;color:var(--pub-muted);">
            <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
            <span style="margin:0 6px;">›</span>
            <a href="/frontend/public/products.php"><?= e(t('nav.products')) ?></a>
            <?php foreach ($categories as $cat): ?>
            <span style="margin:0 6px;">›</span>
            <a href="/frontend/public/products.php?category_id=<?= (int)$cat['id'] ?>"><?= e($cat['name'] ?? '') ?></a>
            <?php endforeach; ?>
            <span style="margin:0 6px;">›</span>
            <span><?= e($productName) ?></span>
        </nav>
    </div>
</div>

<!-- =============================================
     PRODUCT DETAIL
============================================= -->
<main class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <div class="pub-product-detail">

        <!-- Gallery -->
        <div class="pub-product-gallery">
            <div class="pub-gallery-main" id="pubGalleryMain">
                <?php if ($mainImage): ?>
                <img src="<?= e(pub_img($mainImage)) ?>"
                     alt="<?= e($productName) ?>" id="pubMainImg"
                     class="pub-gallery-main-img" loading="eager">
                <?php else: ?>
                <div class="pub-gallery-placeholder" aria-hidden="true">🖼️</div>
                <?php endif; ?>
                <!-- Badges -->
                <div class="pub-gallery-badges">
                    <?php if ($isFeatured): ?><span class="pub-product-badge"><?= e(t('products.featured')) ?></span><?php endif; ?>
                    <?php if ($isNew): ?><span class="pub-product-badge" style="background:var(--pub-secondary);"><?= e(t('products.new')) ?></span><?php endif; ?>
                    <?php if ($isBestseller): ?><span class="pub-product-badge" style="background:var(--pub-accent);"><?= e(t('products.bestseller')) ?></span><?php endif; ?>
                </div>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="pub-gallery-thumbs" id="pubGalleryThumbs">
                <?php foreach ($images as $img): ?>
                <img src="<?= e(pub_img($img['thumb_url'] ?? $img['url'])) ?>"
                     alt="<?= e($img['alt_text'] ?? $productName) ?>"
                     class="pub-gallery-thumb"
                     loading="lazy"
                     data-full="<?= e(pub_img($img['url'])) ?>"
                     onclick="document.getElementById('pubMainImg').src=this.dataset.full;
                              document.querySelectorAll('.pub-gallery-thumb').forEach(function(t){t.classList.remove('active')});
                              this.classList.add('active');">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="pub-product-info">

            <?php if ($brandName): ?>
            <p class="pub-product-brand"><?= e($brandName) ?></p>
            <?php endif; ?>

            <h1 class="pub-product-detail-title"><?= e($productName) ?></h1>

            <!-- Rating -->
            <?php
            $ratingAvg   = (float)($product['rating_average'] ?? 0);
            $ratingCount = (int)($product['rating_count'] ?? 0);
            if ($ratingCount > 0):
                // Build 5 stars: filled (★), half (✦), empty (☆)
                $starHtml = '';
                for ($s = 1; $s <= 5; $s++) {
                    if ($ratingAvg >= $s) {
                        $starHtml .= '<span class="pub-star pub-star--full">★</span>';
                    } elseif ($ratingAvg >= $s - 0.5) {
                        $starHtml .= '<span class="pub-star pub-star--half">★</span>';
                    } else {
                        $starHtml .= '<span class="pub-star pub-star--empty">☆</span>';
                    }
                }
            ?>
            <div class="pub-product-rating">
                <span class="pub-stars" aria-label="<?= number_format($ratingAvg, 1) ?> <?= e(t('products.out_of_5', ['default' => 'out of 5'])) ?>"><?= $starHtml ?></span>
                <span class="pub-rating-score"><?= number_format($ratingAvg, 1) ?></span>
                <span class="pub-rating-count">(<?= $ratingCount ?> <?= e(t('products.reviews')) ?>)</span>
            </div>
            <?php endif; ?>

            <!-- Price -->
            <?php if ($price !== null): ?>
            <div class="pub-product-price-block">
                <span class="pub-product-detail-price">
                    <?= number_format((float)$price, 2) ?> <?= e($currency) ?>
                </span>
                <?php if ($comparePrice && (float)$comparePrice > (float)$price): ?>
                <span class="pub-product-compare-price">
                    <?= number_format((float)$comparePrice, 2) ?> <?= e($currency) ?>
                </span>
                <span class="pub-product-discount-pct">
                    <?= round((1 - $price / $comparePrice) * 100) ?>% <?= e(t('products.off')) ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Stock -->
            <div class="pub-product-stock <?= $inStock ? 'in-stock' : 'out-stock' ?>">
                <?php if ($inStock): ?>
                ✅ <?= e(t('products.in_stock')) ?>
                <?php if ($stockQty > 0 && $stockQty <= 10): ?>
                — <span style="color:var(--pub-accent);">
                    <?= e(t('products.low_stock', ['count' => $stockQty])) ?>
                </span>
                <?php endif; ?>
                <?php else: ?>
                ❌ <?= e(t('products.out_of_stock')) ?>
                <?php endif; ?>
            </div>

            <!-- Short description -->
            <?php if (!empty($product['short_description'])): ?>
            <p class="pub-product-short-desc"><?= e($product['short_description']) ?></p>
            <?php endif; ?>

            <!-- Variants Selector -->
            <?php if (!empty($variants)): ?>
            <div class="pub-variant-wrap" style="margin:12px 0;">
                <label style="font-size:0.88rem;color:var(--pub-muted);display:block;margin-bottom:6px;">
                    <?= e(t('products.variant', ['default' => 'Choose Option'])) ?>:
                </label>
                <div style="display:flex;flex-wrap:wrap;gap:8px;" id="pubVariantBtns">
                <?php foreach ($variants as $v):
                    $vActive = !empty($v['is_default']);
                    $vLabel  = $v['sku'] ?? 'Variant ' . $v['id'];
                    $vStock  = (int)($v['stock_quantity'] ?? 0);
                    $vPrice  = $v['price'] ?? null;
                ?>
                    <button type="button"
                            class="pub-variant-btn<?= $vActive ? ' active' : '' ?>"
                            data-variant-id="<?= (int)$v['id'] ?>"
                            data-price="<?= e((string)($vPrice ?? $price ?? '')) ?>"
                            data-currency="<?= e($v['currency_code'] ?? $currency) ?>"
                            data-stock="<?= $vStock ?>"
                            onclick="pubSelectVariant(this)"
                            <?= $vStock <= 0 ? 'style="opacity:0.5;text-decoration:line-through;"' : '' ?>>
                        <?= e($vLabel) ?>
                        <?php if ($vPrice !== null): ?>
                        <small style="display:block;font-size:0.8em;"><?= number_format((float)$vPrice, 2) ?> <?= e($v['currency_code'] ?? $currency) ?></small>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Add to cart -->
            <?php if ($inStock): ?>
            <div class="pub-product-cart-row">
                <div class="pub-qty-wrap">
                    <button class="pub-qty-btn" onclick="pubQtyChange(-1)" type="button">−</button>
                    <input type="number" id="pubQtyInput" class="pub-qty-input" value="1" min="1"
                           max="<?= $stockQty > 0 ? (int)$stockQty : 999 ?>">
                    <button class="pub-qty-btn" onclick="pubQtyChange(1)" type="button">+</button>
                </div>
                <button class="pub-btn pub-btn--primary pub-add-to-cart"
                        id="pubAddToCartBtn"
                        data-product-id="<?= (int)$product['id'] ?>"
                        data-product-name="<?= e($productName) ?>"
                        data-product-price="<?= e((string)($price ?? '0')) ?>"
                        data-product-image="<?= e(pub_img($mainImage)) ?>"
                        data-currency="<?= e($currency) ?>"
                        data-added-text="✅ <?= e(t('cart.added')) ?>"
                        onclick="pubAddToCart(this)">
                    🛒 <?= e(t('cart.add')) ?>
                </button>
                <!-- Wishlist heart button on detail page -->
                <button class="pub-wishlist-btn"
                        type="button"
                        style="position:static;border-radius:8px;width:auto;padding:0 16px;height:40px;line-height:40px;font-size:1.3rem;"
                        data-product-id="<?= (int)$product['id'] ?>"
                        data-entity-id="<?= (int)($product['entity_id'] ?? 1) ?>"
                        onclick="pubToggleWishlist(this)"
                        title="<?= e(t('wishlist.add')) ?>">♡</button>
                <!-- Compare button -->
                <button type="button"
                        id="pubCompareBtn"
                        class="pub-btn pub-btn--ghost"
                        style="height:40px;padding:0 14px;font-size:0.9rem;"
                        data-product-id="<?= (int)$product['id'] ?>"
                        onclick="pubToggleCompare(this)"
                        title="<?= e(t('products.compare', ['default' => 'Compare'])) ?>">
                    ⚖️ <?= e(t('products.compare', ['default' => 'Compare'])) ?>
                </button>
            </div>
            <?php endif; ?>

            <!-- Categories -->
            <?php if (!empty($categories)): ?>
            <div class="pub-product-cats">
                <span class="pub-product-cat-label"><?= e(t('products.categories')) ?>:</span>
                <?php foreach ($categories as $cat): ?>
                <a href="/frontend/public/products.php?category_id=<?= (int)$cat['id'] ?>"
                   class="pub-cat-tag"><?= e($cat['name'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- SKU -->
            <?php if (!empty($product['sku'])): ?>
            <p style="font-size:0.82rem;color:var(--pub-muted);margin-top:8px;">
                <?= e(t('products.sku')) ?>: <span><?= e($product['sku']) ?></span>
            </p>
            <?php endif; ?>

        </div><!-- /.pub-product-info -->
    </div><!-- /.pub-product-detail -->

    <!-- =============================================
         DESCRIPTION / SPECS TABS
    ============================================= -->
    <div class="pub-section" style="padding:28px 0 0;">
        <div class="pub-tabs" id="pubDetailTabs">
            <?php if ($productDesc): ?>
            <button class="pub-tab active" onclick="pubTabSwitch(this,'pubDescPanel')" type="button">
                <?= e(t('products.description')) ?>
            </button>
            <?php endif; ?>
            <?php if ($specs): ?>
            <button class="pub-tab<?= $productDesc ? '' : ' active' ?>"
                    onclick="pubTabSwitch(this,'pubSpecPanel')" type="button">
                <?= e(t('products.specifications')) ?>
            </button>
            <?php endif; ?>
            <button class="pub-tab<?= (!$productDesc && !$specs) ? ' active' : '' ?>"
                    onclick="pubTabSwitch(this,'pubReviewsPanel')" type="button">
                <?= e(t('products.reviews')) ?>
                <?php if (!empty($reviews)): ?><span class="pub-tab-count"><?= count($reviews) ?></span><?php endif; ?>
            </button>
            <button class="pub-tab" onclick="pubTabSwitch(this,'pubQaPanel')" type="button">
                Q&amp;A
                <?php if (!empty($questions)): ?><span class="pub-tab-count"><?= count($questions) ?></span><?php endif; ?>
            </button>
        </div>

        <?php if ($productDesc): ?>
        <div id="pubDescPanel" class="pub-tab-panel" style="padding:18px 0;line-height:1.8;color:var(--pub-text);">
            <?= nl2br(e($productDesc)) ?>
        </div>
        <?php endif; ?>

        <?php if ($specs): ?>
        <div id="pubSpecPanel" class="pub-tab-panel" style="<?= $productDesc ? 'display:none; ' : '' ?>padding:18px 0;">
            <?= nl2br(e($specs)) ?>
        </div>
        <?php endif; ?>

        <!-- Reviews panel -->
        <div id="pubReviewsPanel" class="pub-tab-panel" style="<?= ($productDesc || $specs) ? 'display:none; ' : '' ?>padding:18px 0;">
            <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $rv): ?>
            <div style="border-bottom:1px solid var(--pub-border,#333);padding:14px 0;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span style="color:#f59e0b;font-size:1.1em;"><?= str_repeat('★', (int)($rv['rating'] ?? 0)) ?><?= str_repeat('☆', 5 - (int)($rv['rating'] ?? 0)) ?></span>
                    <strong style="font-size:.9em;"><?= e($rv['author'] ?? '') ?></strong>
                    <?php if (!empty($rv['is_verified_purchase'])): ?>
                    <span style="font-size:.75em;background:var(--pub-success,#10b981);color:#fff;padding:1px 6px;border-radius:4px;">✓ <?= e(t('products.verified_purchase')) ?></span>
                    <?php endif; ?>
                    <span style="font-size:.75em;color:var(--pub-muted,#999);margin-left:auto;"><?= e(substr($rv['created_at'] ?? '', 0, 10)) ?></span>
                </div>
                <?php if (!empty($rv['title'])): ?><p style="font-weight:600;margin:0 0 4px;"><?= e($rv['title']) ?></p><?php endif; ?>
                <?php if (!empty($rv['comment'])): ?><p style="margin:0;color:var(--pub-muted,#aaa);font-size:.93em;"><?= e($rv['comment']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p style="color:var(--pub-muted,#999);text-align:center;padding:28px 0;"><?= e(t('products.no_reviews')) ?></p>
            <?php endif; ?>
            <!-- Write a review (login-gated) -->
            <?php if ($_isLoggedIn): ?>
            <div style="margin-top:20px;padding:16px;background:var(--pub-surface,#1a1a2e);border-radius:8px;">
                <h4 style="margin:0 0 12px;"><?= e(t('products.write_review')) ?></h4>
                <div style="margin-bottom:8px;">
                    <label style="display:block;font-size:.85em;margin-bottom:4px;"><?= e(t('products.your_rating')) ?></label>
                    <div id="pubStarPicker" style="font-size:1.6em;cursor:pointer;color:#f59e0b;">
                        <?php for ($i = 1; $i <= 5; $i++): ?><span data-val="<?= $i ?>" onclick="pubPickStar(<?= $i ?>)">☆</span><?php endfor; ?>
                    </div>
                    <input type="hidden" id="pubReviewRating" value="0">
                </div>
                <input type="text" id="pubReviewTitle" placeholder="<?= e(t('products.review_title')) ?>"
                       style="width:100%;padding:8px;margin-bottom:8px;background:var(--pub-bg,#0d0d0d);color:var(--pub-text,#fff);border:1px solid var(--pub-border,#333);border-radius:6px;box-sizing:border-box;">
                <textarea id="pubReviewComment" rows="3" placeholder="<?= e(t('products.review_comment')) ?>"
                          style="width:100%;padding:8px;margin-bottom:8px;background:var(--pub-bg,#0d0d0d);color:var(--pub-text,#fff);border:1px solid var(--pub-border,#333);border-radius:6px;box-sizing:border-box;resize:vertical;"></textarea>
                <button onclick="pubSubmitReview(<?= (int)$productId ?>)" class="pub-btn pub-btn--primary" style="padding:8px 18px;"><?= e(t('products.submit_review')) ?></button>
                <span id="pubReviewMsg" style="margin-left:10px;font-size:.85em;"></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Q&A panel -->
        <div id="pubQaPanel" class="pub-tab-panel" style="display:none;padding:18px 0;">
            <?php if (!empty($questions)): ?>
            <?php foreach ($questions as $q): ?>
            <div style="border-bottom:1px solid var(--pub-border,#333);padding:14px 0;">
                <div style="display:flex;gap:8px;margin-bottom:6px;">
                    <span style="font-size:1.2em;">❓</span>
                    <div>
                        <p style="margin:0 0 4px;font-weight:600;"><?= e($q['question'] ?? '') ?></p>
                        <span style="font-size:.75em;color:var(--pub-muted,#999);"><?= e($q['asker'] ?? '') ?> · <?= e(substr($q['created_at'] ?? '', 0, 10)) ?></span>
                    </div>
                </div>
                <?php foreach ((array)($q['answers'] ?? []) as $ans): ?>
                <div style="margin-left:28px;margin-top:8px;padding:8px 10px;background:var(--pub-surface,#1a1a2e);border-radius:6px;">
                    <p style="margin:0 0 4px;font-size:.93em;"><?= e($ans['answer'] ?? '') ?></p>
                    <span style="font-size:.75em;color:var(--pub-muted,#999);"><?= e($ans['answerer'] ?? '') ?><?= !empty($ans['is_staff_answer']) ? ' 🏷️ Staff' : '' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p style="color:var(--pub-muted,#999);text-align:center;padding:28px 0;"><?= e(t('products.no_questions')) ?></p>
            <?php endif; ?>
            <!-- Ask a question (login-gated) -->
            <?php if ($_isLoggedIn): ?>
            <div style="margin-top:20px;padding:16px;background:var(--pub-surface,#1a1a2e);border-radius:8px;">
                <h4 style="margin:0 0 12px;"><?= e(t('products.ask_question')) ?></h4>
                <textarea id="pubQuestionText" rows="2" placeholder="<?= e(t('products.question_placeholder')) ?>"
                          style="width:100%;padding:8px;margin-bottom:8px;background:var(--pub-bg,#0d0d0d);color:var(--pub-text,#fff);border:1px solid var(--pub-border,#333);border-radius:6px;box-sizing:border-box;resize:vertical;"></textarea>
                <button onclick="pubSubmitQuestion(<?= (int)$productId ?>)" class="pub-btn pub-btn--primary" style="padding:8px 18px;"><?= e(t('products.submit_question')) ?></button>
                <span id="pubQaMsg" style="margin-left:10px;font-size:.85em;"></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- =============================================
         PRODUCT RELATIONS (upsell, cross-sell, etc.)
    ============================================= -->
    <?php
    $relGroups = [];
    foreach ($relations as $r) {
        $relGroups[$r['relation_type']][] = $r;
    }
    $relTitles = [
        'upsell'      => t('products.upsell'),
        'cross_sell'  => t('products.cross_sell'),
        'accessory'   => t('products.accessories'),
        'alternative' => t('products.alternatives'),
    ];
    foreach ($relGroups as $rtype => $rItems): ?>
    <section class="pub-section" style="margin-top:8px;">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e($relTitles[$rtype] ?? ucwords(str_replace('_', ' ', $rtype))) ?></h2>
        </div>
        <div class="pub-grid">
            <?php foreach ($rItems as $p): ?>
            <div class="pub-product-card<?= $_productCardClass ? ' ' . $_productCardClass : '' ?>"<?= $_productCardStyle ? ' style="' . e($_productCardStyle) . '"' : '' ?>>
                <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>" style="text-decoration:none;">
                    <div class="pub-cat-img-wrap" style="<?= e($_productImgStyle) ?>">
                        <?php if (!empty($p['image_url'])): ?>
                        <img src="<?= e(pub_img($p['image_url'], 'product')) ?>" alt="<?= e($p['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🖼️</span>
                        <?php else: ?><span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                        <?php endif; ?>
                    </div>
                    <div class="pub-product-card-body">
                        <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                        <?php if (!empty($p['price'])): ?>
                        <p class="pub-product-price"><?= number_format((float)$p['price'], 2) ?> <?= e($p['currency_code'] ?? '') ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                <button class="pub-cart-btn" onclick="pubAddToCart(this)"
                    data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                    data-product-name="<?= e($p['name'] ?? '') ?>"
                    data-product-price="<?= e($p['price'] ?? '0') ?>"
                    data-product-image="<?= e($p['image_url'] ?? '') ?>"
                    data-product-sku="<?= e($p['sku'] ?? '') ?>"
                    data-currency="<?= e($p['currency_code'] ?? '') ?>">
                    <?= e(t('cart.add')) ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <!-- =============================================
         RELATED PRODUCTS
    ============================================= -->
    <?php if (!empty($related)): ?>
    <section class="pub-section">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('products.related')) ?></h2>
        </div>
        <div class="pub-grid">
            <?php foreach ($related as $p): ?>
            <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
               class="pub-product-card<?= $_productCardClass ? ' ' . $_productCardClass : '' ?>"
               style="text-decoration:none;<?= $_productCardStyle ? e($_productCardStyle) : '' ?>">
                <div class="pub-cat-img-wrap" style="<?= e($_productImgStyle) ?>">
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
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                    <p class="pub-product-price">
                        <?= number_format((float)$p['price'], 2) ?> <?= e($p['currency_code'] ?? '') ?>
                    </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<script>
/* Tab switcher */
function pubTabSwitch(btn, panelId) {
    document.querySelectorAll('.pub-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.pub-tab-panel').forEach(function(p) { p.style.display = 'none'; });
    btn.classList.add('active');
    var panel = document.getElementById(panelId);
    if (panel) panel.style.display = '';
}

/* Gallery thumb active on load */
document.addEventListener('DOMContentLoaded', function() {
    var first = document.querySelector('.pub-gallery-thumb');
    if (first) first.classList.add('active');
});

/* Variant selector */
function pubSelectVariant(btn) {
    document.querySelectorAll('#pubVariantBtns .pub-variant-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var price    = btn.dataset.price;
    var currency = btn.dataset.currency;
    var stock    = parseInt(btn.dataset.stock || '0', 10);
    // Update displayed price
    var priceEl = document.querySelector('.pub-product-detail-price');
    if (priceEl && price) priceEl.textContent = parseFloat(price).toFixed(2) + ' ' + currency;
    // Update cart button data
    var cartBtn = document.getElementById('pubAddToCartBtn');
    if (cartBtn) {
        cartBtn.dataset.productPrice = price;
        cartBtn.dataset.currency     = currency;
        cartBtn.dataset.variantId    = btn.dataset.variantId;
    }
    // Update stock indicator
    var stockEl = document.querySelector('.pub-product-stock');
    if (stockEl) {
        if (stock > 0) {
            stockEl.className = 'pub-product-stock in-stock';
        } else {
            stockEl.className = 'pub-product-stock out-stock';
        }
    }
}

/* Compare toggle */
function pubToggleCompare(btn) {
    var pid = btn.dataset.productId;
    var inList = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean);
    var idx = inList.indexOf(pid);
    if (idx >= 0) {
        inList.splice(idx, 1);
        btn.textContent = '⚖️ Compare';
        fetch('/api/public/compare/remove', {method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'product_id='+pid});
    } else {
        if (inList.length >= 4) { alert('Max 4 products can be compared.'); return; }
        inList.push(pid);
        btn.textContent = '✅ In Compare';
        fetch('/api/public/compare/add', {method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'product_id='+pid});
    }
    localStorage.setItem('pub_compare', inList.join(','));
    pubUpdateCompareBadge();
}

/* Compare badge */
function pubUpdateCompareBadge() {
    var n = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean).length;
    var els = document.querySelectorAll('.pub-compare-badge');
    els.forEach(function(el) { el.textContent = n; el.style.display = n > 0 ? 'inline-flex' : 'none'; });
}
document.addEventListener('DOMContentLoaded', function() {
    pubUpdateCompareBadge();
    // Restore compare button state
    var btn = document.getElementById('pubCompareBtn');
    if (btn) {
        var pid = btn.dataset.productId;
        var inList = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean);
        if (inList.indexOf(pid) >= 0) btn.textContent = '✅ In Compare';
    }
});

/* Star picker for review form */
function pubPickStar(val) {
    var stars = document.querySelectorAll('#pubStarPicker span');
    stars.forEach(function(s, i) { s.textContent = i < val ? '★' : '☆'; });
    document.getElementById('pubReviewRating').value = val;
}

/* Submit review */
function pubSubmitReview(productId) {
    var rating  = parseInt(document.getElementById('pubReviewRating').value || '0', 10);
    var title   = (document.getElementById('pubReviewTitle').value || '').trim();
    var comment = (document.getElementById('pubReviewComment').value || '').trim();
    var msg     = document.getElementById('pubReviewMsg');
    if (rating < 1 || rating > 5) { msg.textContent = '⚠️ Please select a rating.'; msg.style.color = '#ef4444'; return; }
    msg.textContent = '…'; msg.style.color = '';
    fetch('/api/public/products/' + productId + '/reviews', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'rating=' + rating + '&title=' + encodeURIComponent(title) + '&comment=' + encodeURIComponent(comment)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            msg.textContent = '✅ Review submitted — awaiting approval.';
            msg.style.color = '#10b981';
            document.getElementById('pubReviewTitle').value = '';
            document.getElementById('pubReviewComment').value = '';
            pubPickStar(0);
        } else {
            msg.textContent = '❌ ' + (d.message || 'Error');
            msg.style.color = '#ef4444';
        }
    }).catch(function() { msg.textContent = '❌ Network error.'; msg.style.color = '#ef4444'; });
}

/* Submit question */
function pubSubmitQuestion(productId) {
    var question = (document.getElementById('pubQuestionText').value || '').trim();
    var msg      = document.getElementById('pubQaMsg');
    if (!question) { msg.textContent = '⚠️ Please enter your question.'; msg.style.color = '#ef4444'; return; }
    msg.textContent = '…'; msg.style.color = '';
    fetch('/api/public/products/' + productId + '/questions', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'question=' + encodeURIComponent(question)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            msg.textContent = '✅ Question submitted — awaiting review.';
            msg.style.color = '#10b981';
            document.getElementById('pubQuestionText').value = '';
        } else {
            msg.textContent = '❌ ' + (d.message || 'Error');
            msg.style.color = '#ef4444';
        }
    }).catch(function() { msg.textContent = '❌ Network error.'; msg.style.color = '#ef4444'; });
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>