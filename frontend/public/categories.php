<?php
/**
 * frontend/public/categories.php
 * QOOQZ — Public Categories Tree Navigation
 * Shows root categories (parent_id=0) and allows drilling down to subcategories.
 * Only categories with products (directly or via children) are displayed.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('categories.page_title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'categories';

/* -------------------------------------------------------
 * Filters & Navigation
 * ----------------------------------------------------- */
$search   = trim($_GET['q'] ?? '');
$featured = isset($_GET['featured']) && $_GET['featured'] === '1' ? 1 : null;
$parentId = isset($_GET['parent_id']) && is_numeric($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;

/* -------------------------------------------------------
 * Fetch TREE from API
 * ----------------------------------------------------- */
$qs = http_build_query(array_filter([
    'lang'      => $lang,
    'tenant_id' => $tenantId,
    'tree'      => 1,
    'featured'  => $featured,
    'search'    => $search ?: null,
]));

$resp = pub_fetch(pub_api_url('public/categories') . '?' . $qs);
$tree = $resp['data']['data'] ?? [];

/**
 * Recursively compute total product count for a category
 * (own product_count + sum of children's total)
 */
function addTotalProductCount(array &$node): int {
    $total = (int)($node['product_count'] ?? 0);
    if (!empty($node['children'])) {
        foreach ($node['children'] as &$child) {
            $total += addTotalProductCount($child);
        }
    }
    $node['total_products'] = $total;
    return $total;
}

foreach ($tree as &$root) {
    addTotalProductCount($root);
}
unset($root);

// Build a flat map for quick lookup by id
$flatMap = [];
function buildFlatMap(array $nodes, array &$map): void {
    foreach ($nodes as $node) {
        $map[(int)$node['id']] = $node;
        if (!empty($node['children'])) {
            buildFlatMap($node['children'], $map);
        }
    }
}
buildFlatMap($tree, $flatMap);

// Get children of current parent (or root if parentId = 0)
$currentChildren = [];
if ($parentId === 0) {
    // Root categories: those without parent_id in the flat map (or parent_id = 0)
    foreach ($tree as $root) {
        if (($root['total_products'] ?? 0) > 0) {
            $currentChildren[] = $root;
        }
    }
} else {
    if (isset($flatMap[$parentId])) {
        $parentNode = $flatMap[$parentId];
        foreach ($parentNode['children'] ?? [] as $child) {
            if (($child['total_products'] ?? 0) > 0) {
                $currentChildren[] = $child;
            }
        }
    }
}

// Build breadcrumb trail from root to current parent
$breadcrumbs = [];
if ($parentId !== 0) {
    $current = $parentId;
    while ($current !== 0 && isset($flatMap[$current])) {
        $breadcrumbs[] = $flatMap[$current];
        $current = (int)($flatMap[$current]['parent_id'] ?? 0);
    }
    $breadcrumbs = array_reverse($breadcrumbs);
}

// SEO meta
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
        <?php if ($parentId === 0): ?>
            <span><?= e(t('nav.categories')) ?></span>
        <?php else: ?>
            <a href="/frontend/public/categories.php?parent_id=0<?= $search ? '&q='.urlencode($search) : '' ?><?= $featured ? '&featured=1' : '' ?>">
                <?= e(t('nav.categories')) ?>
            </a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span style="margin:0 6px;">›</span>
                <a href="/frontend/public/categories.php?parent_id=<?= (int)$crumb['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $featured ? '&featured=1' : '' ?>">
                    <?= e($crumb['name'] ?? '') ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <!-- Page heading -->
    <div class="pub-section-head" style="margin-bottom:20px;">
        <h1 style="font-size:1.4rem;margin:0;">
            <?php if ($parentId === 0): ?>
                <?= e(t('sections.categories')) ?>
            <?php else: ?>
                <?= e($flatMap[$parentId]['name'] ?? t('categories.subcategories')) ?>
            <?php endif; ?>
        </h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format(count($currentChildren)) ?> <?= e(t('categories.category_count')) ?>
        </span>
    </div>

    <!-- Filter bar (simplified) -->
    <form method="get" class="pub-filter-bar">
        <input type="hidden" name="parent_id" value="<?= $parentId ?>">
        <!-- Featured filter -->
        <label style="display:flex;align-items:center;gap:5px;font-size:0.88rem;cursor:pointer;">
            <input type="checkbox" name="featured" value="1" <?= $featured ? 'checked' : '' ?> onchange="this.form.submit()">
            <?= e(t('categories.featured_only')) ?>
        </label>

        <?php if (!empty($search)): ?>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('common.search')) ?>" class="pub-input" style="width:200px;">
        <?php else: ?>
            <input type="text" name="q" placeholder="<?= e(t('common.search')) ?>" class="pub-input" style="width:200px;">
        <?php endif; ?>

        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm">
            <?= e(t('categories.filter')) ?>
        </button>
        <?php if ($search || $featured): ?>
            <a href="/frontend/public/categories.php?parent_id=<?= $parentId ?>" class="pub-btn pub-btn--ghost pub-btn--sm">
                <?= e(t('categories.clear')) ?>
            </a>
        <?php endif; ?>
    </form>

    <!-- Categories grid (current level) -->
    <?php if (!empty($currentChildren)): ?>
    <div class="pub-grid-cat">
        <?php foreach ($currentChildren as $cat): ?>
        <?php
            $catName   = $cat['name'] ?? '';
            $catImg    = $cat['image_url'] ?? ($cat['image'] ?? null);
            $totalProducts = (int)($cat['total_products'] ?? 0);
            $isFeatured = !empty($cat['is_featured']);
            $catDesc   = $cat['description'] ?? '';
            $catId     = (int)($cat['id'] ?? 0);
            $hasChildren = !empty($cat['children']);
            // Link: if has children, go to subcategories; otherwise go to products
            $targetUrl = $hasChildren 
                ? "/frontend/public/categories.php?parent_id={$catId}" 
                : "/frontend/public/products.php?category_id={$catId}";
        ?>
        <div onclick="window.location.href='<?= $targetUrl ?>'" 
           class="pub-cat-card<?= $isFeatured ? ' pub-cat-card--featured' : '' ?><?= $_categoryCardClass ? ' '.$_categoryCardClass : '' ?>"
           style="text-decoration:none;cursor:pointer;<?= e($_categoryCardStyle) ?>"
           role="link"
           aria-label="<?= e($catName) ?>">

            <!-- Category image -->
            <div class="pub-cat-img-wrap" style="position:relative;<?= e($_categoryImgStyle) ?>">
                <?php if ($catImg): ?>
                    <img src="<?= e(pub_img($catImg, 'category')) ?>"
                         alt="<?= e($catName) ?>"
                         class="pub-cat-img"
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="pub-img-placeholder" style="display:none;" aria-hidden="true"><i class="fa fa-folder-open pub-img-placeholder-icon"></i></span>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true"><i class="fa fa-folder-open pub-img-placeholder-icon"></i></span>
                <?php endif; ?>
                <?php if ($isFeatured): ?>
                    <span class="pub-cat-badge"><?= e(t('products.featured')) ?></span>
                <?php endif; ?>
                <!-- Hover overlay -->
                <div class="pub-card-overlay">
                    <a class="pub-card-action" href="<?= $targetUrl ?>"
                       title="<?= $hasChildren ? e(t('categories.browse_subcategories')) : e(t('categories.browse_products')) ?>"
                       onclick="event.stopPropagation()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:middle;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Category info -->
            <div class="pub-cat-body">
                <h2 class="pub-cat-name"><?= e($catName) ?></h2>
                <?php if ($catDesc): ?>
                    <p class="pub-cat-desc"><?= e($catDesc) ?></p>
                <?php endif; ?>
                <span class="pub-cat-count">
                    <?= number_format($totalProducts) ?> <?= e(t('categories.products_count')) ?>
                </span>
                <?php if ($hasChildren): ?>
                    <span class="pub-cat-subcount" style="display:block;font-size:0.75rem;color:var(--pub-muted);">
                        <?= count($cat['children']) ?> <?= e(t('categories.subcategories')) ?>
                    </span>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">📂</div>
        <p class="pub-empty-msg"><?= e(t('categories.empty_no_products')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>