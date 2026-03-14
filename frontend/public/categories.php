<?php
/**
 * frontend/public/categories.php
 * QOOQZ — Public Categories Listing Page
 * Shows product/content categories with images (image_type: category)
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('categories.page_title') . ' — QOOQZ';

/* -------------------------------------------------------
 * Filters
 * ----------------------------------------------------- */
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 24;
$search    = trim($_GET['q'] ?? '');
$parentId  = isset($_GET['parent_id']) && is_numeric($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
$featured  = isset($_GET['featured']) && $_GET['featured'] === '1' ? 1 : null;

/* -------------------------------------------------------
 * Fetch from API
 * ----------------------------------------------------- */
$qs = http_build_query(array_filter([
    'lang'      => $lang,
    'page'      => $page,
    'limit'     => $limit,
    'tenant_id' => $tenantId,
    'parent_id' => $parentId,
    'featured'  => $featured,
    'status'    => 'active',
    'search'    => $search ?: null,
]));

$resp       = pub_fetch(pub_api_url('public/categories') . '?' . $qs);
$categories = $resp['data']['data'] ?? ($resp['data']['items'] ?? []);
$meta       = $resp['data']['meta'] ?? [];
$total      = (int)($meta['total'] ?? count($categories));
$totalPg    = (int)($meta['total_pages'] ?? (($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1));

// SEO meta for category page
$GLOBALS['PUB_SEO'] = [
    'title'       => t('categories.page_title') . ' — QOOQZ',
    'description' => t('categories.page_description', ['default' => 'Browse all product categories']),
    'keywords'    => t('categories.page_keywords', ['default' => 'categories, products, shop']),
    'schema_type' => 'ItemList',
];

include dirname(__DIR__) . '/partials/header.php';
$_categoryCardStyle = pub_card_inline_style('category');
$_categoryCardClass = pub_card_css_class('category');
$_categoryImgStyle  = pub_card_img_style('category', '16/9');
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.categories')) ?></span>
        <?php if ($parentId): ?>
            <span style="margin:0 6px;">›</span>
            <span><?= e(t('categories.subcategories')) ?></span>
        <?php endif; ?>
    </nav>

    <!-- Page heading -->
    <div class="pub-section-head" style="margin-bottom:20px;">
        <h1 style="font-size:1.4rem;margin:0;"><?= e(t('sections.categories')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('categories.category_count')) ?>
        </span>
    </div>

    <!-- Filter bar -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:260px;"
               placeholder="<?= e(t('categories.search_placeholder')) ?>"
               value="<?= e($search) ?>">

        <!-- Parent categories filter (root only / all) -->
        <select name="parent_id" class="pub-filter-select" data-auto-submit>
            <option value=""><?= e(t('categories.all_categories')) ?></option>
            <option value="0" <?= $parentId === 0 ? 'selected' : '' ?>><?= e(t('categories.parent_only')) ?></option>
        </select>

        <!-- Featured filter -->
        <label style="display:flex;align-items:center;gap:5px;font-size:0.88rem;cursor:pointer;">
            <input type="checkbox" name="featured" value="1" <?= $featured ? 'checked' : '' ?> onchange="this.form.submit()">
            <?= e(t('categories.featured_only')) ?>
        </label>

        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm">
            <?= e(t('categories.filter')) ?>
        </button>
        <?php if ($search || $parentId !== null || $featured): ?>
            <a href="/frontend/public/categories.php" class="pub-btn pub-btn--ghost pub-btn--sm">
                <?= e(t('categories.clear')) ?>
            </a>
        <?php endif; ?>
    </form>

    <!-- Categories grid -->
    <?php if (!empty($categories)): ?>
    <div class="pub-grid-cat">
        <?php foreach ($categories as $cat): ?>
        <?php
            $catName   = $cat['name'] ?? '';
            $catSlug   = $cat['slug'] ?? '';
            $catImg    = $cat['image_url'] ?? ($cat['image'] ?? null);
            $catCount  = (int)($cat['product_count'] ?? $cat['products_count'] ?? 0);
            $isFeatured= !empty($cat['is_featured']);
            $catDesc   = $cat['description'] ?? '';
        ?>
        <a href="/frontend/public/products.php?category_id=<?= (int)($cat['id'] ?? 0) ?>"
           class="pub-cat-card<?= $isFeatured ? ' pub-cat-card--featured' : '' ?><?= $_categoryCardClass ? ' '.$_categoryCardClass : '' ?>"
           style="text-decoration:none;<?= e($_categoryCardStyle) ?>"
           aria-label="<?= e($catName) ?>">

            <!-- Category image -->
            <div class="pub-cat-img-wrap" style="<?= e($_categoryImgStyle) ?>">
                <?php if ($catImg): ?>
                    <img src="<?= e(pub_img($catImg, 'category')) ?>"
                         alt="<?= e($catName) ?>"
                         class="pub-cat-img"
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">📂</span>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true">📂</span>
                <?php endif; ?>
                <?php if ($isFeatured): ?>
                    <span class="pub-cat-badge"><?= e(t('products.featured')) ?></span>
                <?php endif; ?>
            </div>

            <!-- Category info -->
            <div class="pub-cat-body">
                <h2 class="pub-cat-name"><?= e($catName) ?></h2>
                <?php if ($catDesc): ?>
                    <p class="pub-cat-desc"><?= e($catDesc) ?></p>
                <?php endif; ?>
                <?php if ($catCount > 0): ?>
                    <span class="pub-cat-count">
                        <?= number_format($catCount) ?> <?= e(t('categories.products_count')) ?>
                    </span>
                <?php endif; ?>
            </div>

        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination" aria-label="Pagination">
        <?php
        $base_qs = http_build_query(array_filter([
            'q'         => $search,
            'parent_id' => $parentId !== null ? $parentId : '',
            'featured'  => $featured,
        ]));
        $pg_url = fn(int $pg) => '?' . ($base_qs ? $base_qs . '&' : '') . 'page=' . $pg;
        ?>
        <a href="<?= $pg_url(max(1,$page-1)) ?>" class="pub-page-btn <?= $page<=1?'disabled':'' ?>">
            <?= e(t('pagination.prev')) ?>
        </a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>" class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>">
            <?= e(t('pagination.next')) ?>
        </a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">📂</div>
        <p class="pub-empty-msg"><?= e(t('categories.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>