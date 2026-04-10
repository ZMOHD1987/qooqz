<?php
declare(strict_types=1);
/**
 * frontend/public/brands.php
 * QOOQZ — Public Brands Listing Page
 * Displays all active brands with logo, name, description and optional
 * "featured" filter. Links lead to products filtered by brand.
 * Note: search is handled globally by the header search bar.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('nav.brands', ['default' => 'Brands']) . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'brands';

/* -------------------------------------------------------
 * Filters
 * ----------------------------------------------------- */
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 24;
$featured = isset($_GET['featured']) && $_GET['featured'] === '1' ? 1 : null;
$offset   = ($page - 1) * $limit;

/* -------------------------------------------------------
 * Fetch brands — PDO-first, API fallback
 * ----------------------------------------------------- */
$brands  = [];
$total   = 0;
$pdo     = pub_get_pdo();

if ($pdo && $tenantId) {
    try {
        $where  = ['b.tenant_id = ?', 'b.is_active = 1'];
        $params = [$tenantId];

        if ($featured) {
            $where[]  = 'b.is_featured = 1';
        }

        $whereClause = implode(' AND ', $where);

        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM brands b WHERE $whereClause");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT b.id, b.slug, b.website_url, b.is_featured,
                    COALESCE(bt.name, b.slug) AS name,
                    COALESCE(bt.description, '') AS description,
                    (SELECT i.url FROM images i
                      JOIN image_types t ON t.id = i.image_type_id
                      WHERE i.owner_id = b.id AND t.name = 'brand'
                      ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS logo_url
               FROM brands b
          LEFT JOIN brand_translations bt ON bt.brand_id = b.id AND bt.language_code = ?
              WHERE $whereClause
              ORDER BY b.is_featured DESC, b.sort_order ASC, b.id ASC
              LIMIT $limit OFFSET $offset"
        );
        $stmt->execute(array_merge([$lang], $params));
        $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[brands.php] PDO error: ' . $e->getMessage());
    }
}

if (!$brands && !$pdo) {
    $qs = http_build_query(array_filter([
        'lang'        => $lang,
        'page'        => $page,
        'per_page'    => $limit,
        'tenant_id'   => $tenantId,
        'is_featured' => $featured,
    ]));
    $resp   = pub_fetch(pub_api_url('public/brands') . '?' . $qs);
    $brands = $resp['data']['data'] ?? ($resp['data'] ?? []);
    $total  = (int)($resp['data']['meta']['total'] ?? count($brands));
}

$totalPg = ($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1;

/* -------------------------------------------------------
 * SEO
 * ----------------------------------------------------- */
$GLOBALS['PUB_SEO'] = [
    'title'       => t('nav.brands', ['default' => 'Brands']) . ' — QOOQZ',
    'description' => t('brands.page_description', ['default' => 'Browse all brands available on QOOQZ']),
    'keywords'    => t('brands.page_keywords', ['default' => 'brands, products, shop, QOOQZ']),
    'schema_type' => 'ItemList',
];

include dirname(__DIR__) . '/partials/header.php';

$_brandCardStyle = pub_card_inline_style('brand');
$_brandCardClass = pub_card_css_class('brand');
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.brands', ['default' => 'Brands'])) ?></span>
    </nav>

    <!-- Page heading -->
    <div class="pub-section-head" style="margin-bottom:20px;">
        <h1 style="font-size:1.4rem;margin:0;">🏷️ <?= e(t('nav.brands', ['default' => 'Brands'])) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('brands.brand_count', ['default' => 'brand(s)'])) ?>
        </span>
    </div>

    <!-- Featured filter (no inline search — search is in the global header) -->
    <div class="pub-filter-bar" style="margin-bottom:24px;">
        <a href="/frontend/public/brands.php"
           class="pub-btn pub-btn--sm <?= !$featured ? 'pub-btn--primary' : 'pub-btn--ghost' ?>">
            <?= e(t('brands.all_brands', ['default' => 'All Brands'])) ?>
        </a>
        <a href="/frontend/public/brands.php?featured=1"
           class="pub-btn pub-btn--sm <?= $featured ? 'pub-btn--primary' : 'pub-btn--ghost' ?>">
            ⭐ <?= e(t('brands.featured_only', ['default' => 'Featured'])) ?>
        </a>
    </div>

    <!-- Brands grid -->
    <?php if (!empty($brands)): ?>
    <div class="pub-grid-brand" style="
        display:grid;
        grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
        gap:20px;">

        <?php foreach ($brands as $brand): ?>
        <?php
            $brandId    = (int)($brand['id'] ?? 0);
            $brandName  = $brand['name'] ?? ($brand['slug'] ?? '');
            $brandDesc  = $brand['description'] ?? '';
            $brandLogo  = $brand['logo_url'] ?? null;
            $isFeatured = !empty($brand['is_featured']);
        ?>
        <a href="/frontend/public/products.php?brand_id=<?= $brandId ?>"
           class="pub-brand-card<?= $isFeatured ? ' pub-brand-card--featured' : '' ?><?= $_brandCardClass ? ' ' . $_brandCardClass : '' ?>"
           style="display:flex;flex-direction:column;align-items:center;text-align:center;
                  padding:18px 12px;border-radius:12px;text-decoration:none;
                  background:var(--pub-card-bg,#fff);
                  border:1px solid var(--pub-border,#e8e8e8);
                  transition:box-shadow .18s,transform .18s;
                  <?= e($_brandCardStyle) ?>"
           onmouseenter="this.style.boxShadow='0 6px 24px rgba(0,0,0,.10)';this.style.transform='translateY(-2px)'"
           onmouseleave="this.style.boxShadow='';this.style.transform=''"
           data-track-type="brand"
           data-track-id="<?= $brandId ?>"
           aria-label="<?= e($brandName) ?>">

            <!-- Logo -->
            <div style="width:80px;height:80px;border-radius:50%;overflow:hidden;
                        background:var(--pub-input-bg,#f5f5f5);
                        display:flex;align-items:center;justify-content:center;
                        margin-bottom:12px;flex-shrink:0;">
                <?php if ($brandLogo): ?>
                    <img src="<?= e(pub_img($brandLogo, 'brand_logo')) ?>"
                         alt="<?= e($brandName) ?>"
                         style="width:100%;height:100%;object-fit:contain;"
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span style="display:none;font-size:1.8rem;">🏷️</span>
                <?php else: ?>
                    <span style="font-size:1.8rem;">🏷️</span>
                <?php endif; ?>
            </div>

            <!-- Name -->
            <p style="margin:0 0 4px;font-weight:600;font-size:0.95rem;
                      color:var(--pub-text,#1a1a1a);line-height:1.3;
                      word-break:break-word;">
                <?= e($brandName) ?>
            </p>

            <!-- Description -->
            <?php if ($brandDesc): ?>
            <p style="margin:0 0 6px;font-size:0.8rem;color:var(--pub-muted,#777);
                      line-height:1.4;max-height:2.8em;overflow:hidden;
                      word-break:break-word;">
                <?= e($brandDesc) ?>
            </p>
            <?php endif; ?>

            <!-- Featured badge -->
            <?php if ($isFeatured): ?>
            <span style="margin-top:4px;font-size:0.72rem;font-weight:600;
                         background:var(--pub-primary,#f60);color:#fff;
                         padding:2px 8px;border-radius:20px;">
                ⭐ <?= e(t('products.featured', ['default' => 'Featured'])) ?>
            </span>
            <?php endif; ?>

        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination" style="margin-top:32px;" aria-label="Pagination">
        <?php
        $base_qs = http_build_query(array_filter(['featured' => $featured]));
        $pg_url  = fn(int $pg) => '?' . ($base_qs ? $base_qs . '&' : '') . 'page=' . $pg;
        ?>
        <a href="<?= $pg_url(max(1, $page - 1)) ?>"
           class="pub-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
            <?= e(t('pagination.prev', ['default' => '‹'])) ?>
        </a>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPg, $page + 2); $i++): ?>
            <a href="<?= $pg_url($i) ?>"
               class="pub-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg, $page + 1)) ?>"
           class="pub-page-btn <?= $page >= $totalPg ? 'disabled' : '' ?>">
            <?= e(t('pagination.next', ['default' => '›'])) ?>
        </a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <!-- Empty state -->
    <div class="pub-empty" style="text-align:center;padding:60px 20px;">
        <div class="pub-empty-icon" style="font-size:3rem;margin-bottom:12px;">🏷️</div>
        <p class="pub-empty-msg" style="color:var(--pub-muted);font-size:1rem;">
            <?= e(t('brands.empty', ['default' => 'No brands found.'])) ?>
        </p>
        <?php if ($featured): ?>
        <a href="/frontend/public/brands.php" class="pub-btn pub-btn--ghost pub-btn--sm" style="margin-top:16px;">
            <?= e(t('brands.all_brands', ['default' => 'View all brands'])) ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>