<?php
declare(strict_types=1);
/**
 * Component: ad_stats
 * Renders a statistics row with counts from API.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

$qs = 'lang=' . urlencode($lang) . '&per=1&page=1&tenant_id=' . $tenantId;

$rProd = pub_fetch($apiBase . 'public/products?' . $qs);
$rCat  = pub_fetch($apiBase . 'public/categories?' . $qs);
$rJob  = pub_fetch($apiBase . 'public/jobs?lang=' . urlencode($lang) . '&per=1');
$rEnt  = pub_fetch($apiBase . 'public/entities?' . $qs);
$rTen  = pub_fetch($apiBase . 'public/tenants?lang=' . urlencode($lang) . '&per=1');

$totalProducts   = (int)($rProd['data']['meta']['total'] ?? 0);
$totalCategories = (int)($rCat['data']['meta']['total']  ?? 0);
$totalJobs       = (int)($rJob['data']['meta']['total']  ?? 0);
$totalEntities   = (int)($rEnt['data']['meta']['total']  ?? 0);
$totalTenants    = (int)($rTen['data']['meta']['total']  ?? 0);
?>
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
