<?php
declare(strict_types=1);
/**
 * frontend/public/bundles.php
 * Product Bundles page — list of bundle deals.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';
$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];

$entityId = isset($_GET['entity_id']) && ctype_digit($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

$qs = ['lang' => $lang, 'tenant_id' => $tenantId, 'limit' => 24, 'page' => max(1, (int)($_GET['page'] ?? 1))];
if ($entityId) $qs['entity_id'] = $entityId;
$resp    = pub_fetch(pub_api_url('') . 'public/bundles?' . http_build_query(array_filter($qs)));
$bundles = $resp['data'] ?? [];
$meta    = $resp['meta'] ?? [];

$GLOBALS['PUB_PAGE_TITLE'] = e(t('bundles.page_title', ['default' => 'Bundle Deals'])) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';

// Resolve bundle card style from DB card_styles (card_type='bundle')
$_bundleCardStyle = pub_card_inline_style('bundle');
$_bundleCardClass = pub_card_css_class('bundle');
$_bundleImgStyle  = pub_card_img_style('bundle', '4/3');
?>
<main class="pub-container" style="padding:28px 0 48px;">
    <div class="pub-section-head">
        <h1 class="pub-section-title">📦 <?= e(t('bundles.page_title', ['default' => 'Bundle Deals'])) ?></h1>
        <p style="color:var(--pub-muted);font-size:0.9rem;"><?= e(t('bundles.page_subtitle', ['default' => 'Save more when you buy product bundles'])) ?></p>
    </div>

    <?php if (empty($bundles)): ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">📦</div>
        <p><?= e(t('bundles.empty', ['default' => 'No bundle deals available at the moment'])) ?></p>
        <a href="products.php" class="pub-btn pub-btn--primary"><?= e(t('nav.products')) ?></a>
    </div>
    <?php else: ?>
    <div class="pub-grid-md" style="margin-top:24px;">
        <?php foreach ($bundles as $b):
            $bName = $b['name'] ?? ($lang === 'ar' ? ($b['bundle_name_ar'] ?? $b['bundle_name'] ?? '') : ($b['bundle_name'] ?? ''));
            $bPct  = (float)($b['discount_percentage'] ?? 0);
            $bSave = (float)($b['discount_amount'] ?? 0);
            $bOrig = (float)($b['original_total_price'] ?? 0);
            $bFinal= (float)($b['bundle_price'] ?? 0);
            $bImg  = $b['bundle_image'] ?? null;
            $bId   = (int)($b['id'] ?? 0);
            $bStock= (int)($b['stock_quantity'] ?? 0);
        ?>
        <div class="pub-card<?= $_bundleCardClass ? ' ' . $_bundleCardClass : '' ?>" style="position:relative;overflow:hidden;border-radius:12px;padding:0;<?= e($_bundleCardStyle) ?>">
            <?php if ($bPct > 0): ?>
            <div style="position:absolute;top:12px;left:12px;background:var(--pub-accent,#f59e0b);color:#000;
                        border-radius:20px;padding:3px 12px;font-size:0.82rem;font-weight:700;z-index:2;">
                -<?= round($bPct) ?>%
            </div>
            <?php endif; ?>
            <?php if ($bImg): ?>
            <img src="<?= e(pub_img($bImg)) ?>" alt="<?= e($bName) ?>"
                 style="width:100%;height:180px;object-fit:cover;" loading="lazy">
            <?php else: ?>
            <div style="width:100%;height:180px;background:var(--pub-surface);display:flex;align-items:center;justify-content:center;font-size:3rem;">📦</div>
            <?php endif; ?>
            <div style="padding:16px;">
                <h3 style="margin:0 0 8px;font-size:1rem;"><?= e($bName) ?></h3>
                <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:8px;">
                    <span style="font-size:1.15rem;font-weight:700;color:var(--pub-primary);"><?= number_format($bFinal, 2) ?></span>
                    <?php if ($bOrig > $bFinal): ?>
                    <span style="text-decoration:line-through;color:var(--pub-muted);font-size:0.85rem;"><?= number_format($bOrig, 2) ?></span>
                    <?php endif; ?>
                    <?php if ($bSave > 0): ?>
                    <span style="color:var(--pub-success,#10b981);font-size:0.8rem;">
                        <?= e(t('bundles.save', ['default' => 'Save'])) ?> <?= number_format($bSave, 2) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($bStock > 0 && $bStock <= 10): ?>
                <p style="font-size:0.8rem;color:var(--pub-accent,#f59e0b);margin:0 0 8px;">
                    ⚡ <?= e(t('products.low_stock', ['count' => $bStock, 'default' => 'Only '.$bStock.' left'])) ?>
                </p>
                <?php endif; ?>
                <a href="/frontend/public/bundle.php?id=<?= $bId ?>"
                   class="pub-btn pub-btn--primary" style="width:100%;text-align:center;display:block;">
                    <?= e(t('bundles.view', ['default' => 'View Bundle'])) ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>