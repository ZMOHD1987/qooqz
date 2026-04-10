<?php
declare(strict_types=1);
/**
 * frontend/public/search.php
 * QOOQZ — Global Search Results Page
 *
 * Searches across: products, categories, entities, tenants, jobs, auctions.
 * Each type uses its existing public API endpoint with ?search= parameter.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = (int)$ctx['tenant_id'];
$apiBase  = pub_api_url('');

$q    = trim($_GET['q'] ?? '');
$type = trim($_GET['context'] ?? 'all');

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = ($q !== '' ? e($q) . ' — ' : '') . t('search.results_title', 'نتائج البحث') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('search.results_desc', 'نتائج البحث في المنتجات والتصنيفات والمتاجر والمزادات والوظائف');
$GLOBALS['PUB_PAGE_TYPE']  = 'all';

// ── Context → which sources to query ────────────────────────────────────────
$_contextSources = [
    'products'   => ['products'],
    'categories' => ['categories'],
    'entities'   => ['entities'],
    'tenants'    => ['tenants'],
    'jobs'       => ['jobs'],
    'auctions'   => ['auctions'],
    'cart'       => ['products'],
    'orders'     => ['products'],
    'brands'     => ['products'],
    'bundles'    => ['products'],
    'all'        => ['products', 'categories', 'entities', 'tenants', 'jobs', 'auctions'],
];
$_activeSources = $_contextSources[$type] ?? $_contextSources['all'];

// ── Fetch results from active sources ───────────────────────────────────────
$results = [
    'products'   => [],
    'categories' => [],
    'entities'   => [],
    'tenants'    => [],
    'jobs'       => [],
    'auctions'   => [],
];

if ($q !== '') {
    $enc    = urlencode($q);
    $base   = 'tenant_id=' . $tenantId . '&lang=' . urlencode($lang) . '&per=8&page=1';

    $allUrls = [
        'products'   => $apiBase . 'public/products?'   . $base . '&search=' . $enc,
        'categories' => $apiBase . 'public/categories?' . $base . '&search=' . $enc,
        'entities'   => $apiBase . 'public/entities?'   . $base . '&search=' . $enc,
        'tenants'    => $apiBase . 'public/tenants?lang=' . urlencode($lang) . '&per=8&page=1&search=' . $enc,
        'jobs'       => $apiBase . 'public/jobs?lang='   . urlencode($lang) . '&per=8&page=1&search=' . $enc,
        'auctions'   => $apiBase . 'public/auctions?lang=' . urlencode($lang) . '&tenant_id=' . $tenantId . '&per=8&page=1&status=all&search=' . $enc,
    ];
    // Narrow to context-relevant sources only
    $urls = array_intersect_key($allUrls, array_flip($_activeSources));

    // Fetch all sources in parallel using cURL multi, falling back to sequential pub_fetch()
    if (function_exists('curl_multi_init')) {
        $mh      = curl_multi_init();
        $handles = [];
        foreach ($urls as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh);
        } while ($active && $status === CURLM_OK);

        $raw = [];
        foreach ($handles as $key => $ch) {
            $body      = curl_multi_getcontent($ch);
            $decoded   = $body ? json_decode($body, true) : null;
            $raw[$key] = is_array($decoded) ? $decoded : [];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    } else {
        $raw = [];
        foreach ($urls as $key => $url) {
            $decoded   = pub_fetch($url);
            $raw[$key] = is_array($decoded) ? $decoded : [];
        }
    }

    $results['products']   = $raw['products']['data']['data']     ?? [];
    $results['categories'] = $raw['categories']['data']['data']   ?? [];
    $results['entities']   = $raw['entities']['data']['data']     ?? [];
    $results['tenants']    = $raw['tenants']['data']['data']      ?? [];
    $results['jobs']       = $raw['jobs']['data']['data']         ?? [];
    $results['auctions']   = $raw['auctions']['data']['auctions'] ?? [];

    // Normalize to arrays
    foreach ($results as $k => $v) {
        $results[$k] = is_array($v) ? $v : [];
    }
}

$totalFound = array_sum(array_map('count', $results));

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container pub-search-results-page" style="padding-top:32px;padding-bottom:48px;">

    <?php if ($q === ''): ?>
    <p class="pub-search-hint" style="text-align:center;color:var(--pub-muted,#888);font-size:1.1rem;">
        <?= e(t('search.enter_query', 'أدخل كلمة للبحث')) ?>
    </p>
    <?php elseif ($totalFound === 0): ?>
    <div class="pub-search-empty" style="text-align:center;padding:48px 0;">
        <p style="font-size:2rem;margin:0 0 12px;">🔍</p>
        <p style="font-size:1.1rem;color:var(--pub-muted,#888);">
            <?= e(t('search.no_results', 'لا توجد نتائج لـ')) ?> "<strong><?= e($q) ?></strong>"
        </p>
    </div>
    <?php else: ?>

    <p class="pub-search-summary" style="color:var(--pub-muted,#888);margin-bottom:24px;font-size:0.95rem;">
        <?= e(t('search.results_for', 'نتائج البحث عن')) ?> "<strong><?= e($q) ?></strong>"
        — <?= $totalFound ?> <?= e(t('search.result_count', 'نتيجة')) ?>
    </p>

    <?php

    // ── Helper: section header ────────────────────────────────────────────
    $sectionHead = function(string $icon, string $title, string $viewAllUrl) {
        echo '<div class="pub-search-section-head" style="display:flex;align-items:center;justify-content:space-between;margin:32px 0 16px;">';
        echo '<h2 style="margin:0;font-size:1.2rem;display:flex;align-items:center;gap:8px;">' . $icon . ' ' . e($title) . '</h2>';
        if ($viewAllUrl !== '') {
            echo '<a href="' . e($viewAllUrl) . '" class="pub-section-link">' . e(t('sections.view_all', 'عرض الكل')) . '</a>';
        }
        echo '</div>';
    };

    // ── Products ──────────────────────────────────────────────────────────
    if (!empty($results['products'])):
        $sectionHead('🛍️', t('search.products', 'المنتجات'), '/frontend/public/products.php?q=' . urlencode($q));
    ?>
    <div class="pub-grid">
        <?php foreach ($results['products'] as $p):
            $pId   = (int)($p['id'] ?? 0);
            $pName = trim($p['name'] ?? '');
            $pImg  = pub_img($p['image_url'] ?? $p['image_thumb_url'] ?? null, 'product');
            $pPrice= $p['price'] ?? null;
            $pCur  = $p['currency_code'] ?? '';
            if (!$pId) continue;
        ?>
        <a href="/frontend/public/product.php?id=<?= $pId ?>"
           class="pub-product-card"
           style="text-decoration:none;">
            <div class="pub-cat-img-wrap">
                <?php if ($pImg): ?>
                    <img src="<?= e($pImg) ?>" alt="<?= e($pName) ?>" loading="lazy" class="pub-cat-img"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span style="display:none;" aria-hidden="true">🖼️</span>
                <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                <?php endif; ?>
            </div>
            <div class="pub-product-card-body">
                <p class="pub-product-name"><?= e($pName) ?></p>
                <?php if ($pPrice !== null): ?>
                <p class="pub-product-price"><?= number_format((float)$pPrice, 2) ?> <small><?= e($pCur) ?></small></p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php // ── Categories ─────────────────────────────────────────────────────── ?>
    <?php if (!empty($results['categories'])):
        $sectionHead('📂', t('search.categories', 'التصنيفات'), '/frontend/public/categories.php?q=' . urlencode($q));
    ?>
    <div class="pub-grid-sm">
        <?php foreach ($results['categories'] as $c):
            $cId   = (int)($c['id'] ?? 0);
            $cName = trim($c['name'] ?? '');
            $cImg  = pub_img($c['image_url'] ?? null, 'category');
            if (!$cId) continue;
        ?>
        <a href="/frontend/public/categories.php?id=<?= $cId ?>"
           class="pub-category-card"
           style="text-decoration:none;">
            <?php if ($cImg): ?>
            <div class="pub-cat-img-wrap">
                <img src="<?= e($cImg) ?>" alt="<?= e($cName) ?>" loading="lazy" class="pub-cat-img"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;" aria-hidden="true">📂</span>
            </div>
            <?php endif; ?>
            <p class="pub-cat-name" style="text-align:center;padding:8px 4px 4px;"><?= e($cName) ?></p>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php // ── Entities ──────────────────────────────────────────────────────── ?>
    <?php if (!empty($results['entities'])):
        $sectionHead('🏢', t('search.entities', 'البائعون'), '/frontend/public/entities.php?q=' . urlencode($q));
    ?>
    <div class="pub-grid-md">
        <?php foreach ($results['entities'] as $ent):
            $eId   = (int)($ent['id'] ?? 0);
            $eName = trim($ent['store_name'] ?? $ent['name'] ?? '');
            $eLogo = pub_img($ent['logo_url'] ?? null, 'entity_logo');
            $eType = trim($ent['vendor_type'] ?? '');
            $eVerif= !empty($ent['is_verified']);
            if (!$eId) continue;
        ?>
        <a href="/frontend/public/entity.php?id=<?= $eId ?>"
           class="pub-entity-card"
           style="text-decoration:none;">
            <div class="pub-entity-avatar">
                <?php if ($eLogo): ?>
                    <img src="<?= e($eLogo) ?>" alt="<?= e($eName) ?>" loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span style="display:none;" aria-label="">🏢</span>
                <?php else: ?>
                    <span aria-hidden="true">🏢</span>
                <?php endif; ?>
            </div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($eName) ?></p>
                <?php if ($eType !== ''): ?><p class="pub-entity-desc"><?= e($eType) ?></p><?php endif; ?>
                <?php if ($eVerif): ?><span class="pub-entity-verified">✅ <?= e(t('entities.verified', 'موثق')) ?></span><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php // ── Tenants ───────────────────────────────────────────────────────── ?>
    <?php if (!empty($results['tenants'])):
        $sectionHead('🏪', t('search.tenants', 'المتاجر'), '/frontend/public/tenants.php?q=' . urlencode($q));
    ?>
    <div class="pub-grid-md">
        <?php foreach ($results['tenants'] as $tn):
            $tnId   = (int)($tn['id'] ?? 0);
            $tnName = trim($tn['name'] ?? '');
            $tnLogo = pub_img($tn['logo_url'] ?? null, 'tenant_logo');
            if (!$tnId) continue;
        ?>
        <a href="/frontend/public/tenant.php?id=<?= $tnId ?>"
           class="pub-entity-card"
           style="text-decoration:none;">
            <div class="pub-entity-avatar">
                <?php if ($tnLogo): ?>
                    <img src="<?= e($tnLogo) ?>" alt="<?= e($tnName) ?>" loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span style="display:none;" aria-label="">🏪</span>
                <?php else: ?>
                    <span aria-hidden="true">🏪</span>
                <?php endif; ?>
            </div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($tnName) ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php // ── Jobs ──────────────────────────────────────────────────────────── ?>
    <?php if (!empty($results['jobs'])):
        $sectionHead('💼', t('search.jobs', 'الوظائف'), '/frontend/public/jobs.php?q=' . urlencode($q));
    ?>
    <div class="pub-jobs-list" style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($results['jobs'] as $j):
            $jId    = (int)($j['id'] ?? 0);
            $jTitle = trim($j['title'] ?? $j['job_title'] ?? '');
            $jLoc   = trim($j['location'] ?? '');
            $jType  = trim($j['job_type'] ?? '');
            if (!$jId) continue;
        ?>
        <a href="/frontend/public/job.php?id=<?= $jId ?>"
           class="pub-job-card"
           style="text-decoration:none;display:flex;align-items:flex-start;gap:12px;
                  background:var(--pub-surface,#f8f9fb);border:1px solid var(--pub-border,#e6e9ee);
                  border-radius:8px;padding:14px 16px;">
            <span style="font-size:1.6rem;line-height:1;">💼</span>
            <div>
                <p style="margin:0 0 4px;font-weight:600;color:var(--pub-text,#222);"><?= e($jTitle) ?></p>
                <?php if ($jLoc || $jType): ?>
                <p style="margin:0;font-size:0.85rem;color:var(--pub-muted,#888);">
                    <?= $jLoc ? e($jLoc) : '' ?>
                    <?= ($jLoc && $jType) ? ' · ' : '' ?>
                    <?= $jType ? e($jType) : '' ?>
                </p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php // ── Auctions ──────────────────────────────────────────────────────── ?>
    <?php if (!empty($results['auctions'])):
        $sectionHead('🔨', t('search.auctions', 'المزادات'), '/frontend/public/auctions.php?q=' . urlencode($q));
    ?>
    <div class="pub-grid-sm">
        <?php foreach ($results['auctions'] as $a):
            $aId    = (int)($a['id'] ?? 0);
            $aTitle = trim($a['title'] ?? '');
            $aImg   = pub_img($a['image_url'] ?? null, 'auction');
            $aPrice = $a['current_price'] ?? $a['starting_price'] ?? null;
            $aCur   = $a['currency_code'] ?? '';
            $aEnd   = $a['end_date'] ?? '';
            if (!$aId) continue;
        ?>
        <a href="/frontend/public/auction.php?id=<?= $aId ?>"
           class="pub-auction-card"
           style="text-decoration:none;display:flex;flex-direction:column;
                  background:var(--pub-surface,#f8f9fb);border:1px solid var(--pub-border,#e6e9ee);
                  border-radius:10px;overflow:hidden;">
            <?php if ($aImg): ?>
            <div style="aspect-ratio:16/9;overflow:hidden;">
                <img src="<?= e($aImg) ?>" alt="<?= e($aTitle) ?>" loading="lazy"
                     style="width:100%;height:100%;object-fit:cover;">
            </div>
            <?php endif; ?>
            <div style="padding:12px;">
                <p style="margin:0 0 6px;font-weight:600;color:var(--pub-text,#222);font-size:0.95rem;"><?= e($aTitle) ?></p>
                <?php if ($aPrice !== null): ?>
                <p style="margin:0;font-size:0.9rem;color:var(--pub-primary,#2d8cf0);font-weight:700;">
                    <?= number_format((float)$aPrice, 0) ?> <?= e($aCur) ?>
                </p>
                <?php endif; ?>
                <?php if ($aEnd): ?>
                <p style="margin:4px 0 0;font-size:0.78rem;color:var(--pub-muted,#888);">
                    <?= e(t('auctions.ends', 'ينتهي')) ?>: <?= e(date('Y-m-d', strtotime($aEnd))) ?>
                </p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; // end $totalFound > 0 ?>

</div><!-- .pub-container -->

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>