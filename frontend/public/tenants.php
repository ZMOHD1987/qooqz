<?php
declare(strict_types=1);
/**
 * frontend/public/tenant.php
 * QOOQZ — Tenant Profile Page
 * Shows: tenant name/domain/status, all approved entities, add-entity CTA
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx    = $GLOBALS['PUB_CONTEXT'];
$lang   = $ctx['lang'];
$tenantId = (int)($_GET['id'] ?? 0);

if (!$tenantId) {
    header('Location: /frontend/public/tenants.php');
    exit;
}

$GLOBALS['PUB_APP_NAME']  = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH'] = '/frontend/public';

/* -------------------------------------------------------
 * Fetch tenant info
 * ----------------------------------------------------- */
$resp   = pub_fetch(pub_api_url('public/tenants/' . $tenantId) . '?lang=' . urlencode($lang));
$tenant = $resp['data']['tenant'] ?? [];

if (empty($tenant)) {
    $GLOBALS['PUB_PAGE_TITLE'] = t('tenants.not_found') . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<div class="pub-container" style="padding:60px 0;text-align:center;"><p>' . e(t('tenants.not_found')) . '</p></div>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

/* -------------------------------------------------------
 * Fetch tenant's entities
 * ----------------------------------------------------- */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 18;
$offset = ($page - 1) * $limit;
$er     = pub_fetch(
    pub_api_url('public/tenants/' . $tenantId . '/entities')
    . '?lang=' . urlencode($lang) . '&page=' . $page . '&per_page=' . $limit
);
$entities = $er['data']['data'] ?? [];
$meta     = $er['data']['meta'] ?? [];
$total    = (int)($meta['total'] ?? count($entities));
$totalPg  = (int)($meta['total_pages'] ?? 1);

$tenantName = e($tenant['name'] ?? 'Tenant');
$GLOBALS['PUB_PAGE_TITLE'] = $tenantName . ' — QOOQZ';

include dirname(__DIR__) . '/partials/header.php';

// Resolve entity card style from DB card_styles (card_type='entities')
$_tenantEntityCardStyle = pub_card_inline_style('entities');
$_tenantEntityCardClass = pub_card_css_class('entities');
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <a href="/frontend/public/tenants.php"><?= e(t('nav.tenants')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= $tenantName ?></span>
    </nav>

    <!-- Tenant header -->
    <div class="pub-info-card" style="margin-bottom:28px;">
        <div style="padding:20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <div style="width:64px;height:64px;border-radius:12px;background:var(--pub-primary);
                        display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0;">🌐</div>
            <div style="flex:1;min-width:0;">
                <h1 style="margin:0 0 4px;font-size:1.4rem;"><?= $tenantName ?></h1>
                <?php if (!empty($tenant['domain'])): ?>
                    <p style="margin:0;font-size:0.84rem;color:var(--pub-muted);">
                        🔗 <?= e($tenant['domain']) ?>
                    </p>
                <?php endif; ?>
                <div style="margin-top:8px;">
                    <span style="font-size:0.78rem;background:var(--pub-primary);color:#fff;
                                 padding:3px 10px;border-radius:20px;">
                        <?= e(t('tenants.active_status')) ?>
                    </span>
                    <span style="font-size:0.82rem;color:var(--pub-muted);margin-inline-start:10px;">
                        <?= $total ?> <?= e(t('entities.page_title')) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA: Add Entity under this tenant -->
    <?php if (!empty($GLOBALS['PUB_CONTEXT']['user'])): ?>
    <div class="pub-cta-banner" style="margin-bottom:24px;">
        <div>
            <h2>🏪 <?= e(t('join_entity.cta_title')) ?></h2>
            <p><?= e(t('join_entity.cta_subtitle')) ?></p>
        </div>
        <a href="/frontend/public/join_entity.php?tenant_id=<?= $tenantId ?>" class="pub-btn--cta">
            <?= e(t('join_entity.cta_btn')) ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Section heading -->
    <div class="pub-section-head" style="margin-bottom:16px;">
        <h2 style="font-size:1.1rem;margin:0;">🏪 <?= e(t('entities.page_title')) ?></h2>
    </div>

    <!-- Entities grid -->
    <?php if (!empty($entities)): ?>
    <div class="pub-grid-md">
        <?php foreach ($entities as $en):
            // Per-entity card color from entity_settings.additional_settings
            $enCardStyle = pub_entity_card_style($en, $_tenantEntityCardStyle);
        ?>
        <a href="/frontend/public/entity.php?id=<?= (int)($en['id'] ?? 0) ?>"
           class="pub-entity-card<?= $_tenantEntityCardClass ? ' ' . $_tenantEntityCardClass : '' ?>" style="text-decoration:none;<?= e($enCardStyle) ?>">
            <div class="pub-entity-card-logo">
                <?php if (!empty($en['logo_url'])): ?>
                    <img src="<?= e(pub_img($en['logo_url'], 'entity_logo')) ?>"
                         alt="<?= e($en['store_name'] ?? '') ?>"
                         loading="lazy" class="pub-entity-card-logo-img"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="pub-entity-card-logo-placeholder" style="display:none;">🏢</span>
                <?php else: ?>
                    <span class="pub-entity-card-logo-placeholder">🏢</span>
                <?php endif; ?>
            </div>
            <div class="pub-entity-card-body">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <p class="pub-entity-card-name"><?= e($en['store_name'] ?? '') ?></p>
                    <?php if (!empty($en['is_verified'])): ?>
                        <span style="font-size:0.75rem;">✅</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($en['vendor_type'])): ?>
                    <p class="pub-entity-card-type"><?= e($en['vendor_type']) ?></p>
                <?php endif; ?>
                <?php if (!empty($en['phone'])): ?>
                    <p style="font-size:0.8rem;color:var(--pub-muted);margin:4px 0 0;">📞 <?= e($en['phone']) ?></p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1):
        $pgUrl = fn(int $pg) => '?id=' . $tenantId . '&page=' . $pg;
    ?>
    <nav class="pub-pagination" style="margin-top:28px;">
        <?php if ($page > 1): ?>
            <a href="<?= $pgUrl($page - 1) ?>" class="pub-page-btn"><?= e(t('pagination.prev')) ?></a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPg, $page + 2); $i++): ?>
            <a href="<?= $pgUrl($i) ?>" class="pub-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPg): ?>
            <a href="<?= $pgUrl($page + 1) ?>" class="pub-page-btn"><?= e(t('pagination.next')) ?></a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">🏪</div>
        <p class="pub-empty-msg"><?= e(t('entities.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>