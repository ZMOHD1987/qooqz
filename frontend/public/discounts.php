<?php
declare(strict_types=1);
/**
 * frontend/public/discounts.php
 * QOOQZ — Public Discounts & Offers Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];
$qs       = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId;

$GLOBALS['PUB_PAGE_TITLE'] = t('discounts.page_title') . ' — QOOQZ';

// Resolve discount card style from DB card_styles (card_type='discount')
$_discountCardStyle = pub_card_inline_style('discount');
$_discountCardClass = pub_card_css_class('discount');

/* -------------------------------------------------------
 * Fetch all active discounts for this tenant
 * ----------------------------------------------------- */
$resp      = pub_fetch(pub_api_url('public/discounts') . '?' . $qs);
$discounts = $resp['data']['data'] ?? ($resp['data'] ?? []);
if (!is_array($discounts)) $discounts = [];

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:32px;">

    <!-- Page heading -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:28px;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:800;margin:0 0 4px;color:var(--pub-text);">
                🏷️ <?= e(t('discounts.page_title')) ?>
            </h1>
            <p style="color:var(--pub-muted);margin:0;font-size:0.9rem;"><?= e(t('discounts.page_subtitle')) ?></p>
        </div>
        <a href="/frontend/public/products.php?tenant_id=<?= $tenantId ?>"
           class="pub-btn pub-btn--sm" style="text-decoration:none;">
            🛍️ <?= e(t('nav.products')) ?>
        </a>
    </div>

    <?php if (empty($discounts)): ?>
    <div class="pub-empty" style="padding:80px 0;">
        <div class="pub-empty-icon">🏷️</div>
        <p class="pub-empty-msg"><?= e(t('discounts.none')) ?></p>
    </div>
    <?php else: ?>

    <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));">
        <?php foreach ($discounts as $d): ?>
        <div class="pub-discount-card<?= $_discountCardClass ? ' ' . $_discountCardClass : '' ?>"<?= $_discountCardStyle ? ' style="' . e($_discountCardStyle) . '"' : '' ?>>
            <?php if (!empty($d['marketing_badge'])): ?>
                <span class="pub-discount-badge-top"><?= e($d['marketing_badge']) ?></span>
            <?php endif; ?>

            <?php
            // Status / expiry badge
            $dStatus = $d['status'] ?? 'active';
            $dExpired = !empty($d['ends_at']) && strtotime($d['ends_at']) < time();
            ?>
            <?php if ($dExpired): ?>
                <span style="position:absolute;top:8px;inset-inline-end:8px;background:#666;color:#fff;font-size:0.72rem;padding:2px 8px;border-radius:999px;">⏰ <?= e(t('discounts.expired')) ?></span>
            <?php elseif ($dStatus === 'inactive'): ?>
                <span style="position:absolute;top:8px;inset-inline-end:8px;background:#888;color:#fff;font-size:0.72rem;padding:2px 8px;border-radius:999px;">⬛ <?= e(t('discounts.inactive')) ?></span>
            <?php endif; ?>

            <div class="pub-discount-inner">
                <div class="pub-discount-icon">🏷️</div>
                <div class="pub-discount-body">
                    <p class="pub-discount-title"><?= e($d['title'] ?? $d['code'] ?? '') ?></p>
                    <?php if (!empty($d['description'])): ?>
                        <p class="pub-discount-desc"><?= e($d['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($d['code'])): ?>
                    <div class="pub-discount-code-row">
                        <span class="pub-discount-code" id="dc-<?= (int)($d['id'] ?? 0) ?>">
                            <?= e($d['code']) ?>
                        </span>
                        <button class="pub-btn pub-btn--ghost pub-btn--sm"
                                onclick="pubCopyDiscount('<?= e(addslashes($d['code'])) ?>', this)">
                            📋 <?= e(t('discounts.copy_code')) ?>
                        </button>
                    </div>
                    <?php endif; ?>

                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                        <?php if (!empty($d['ends_at'])): ?>
                        <span class="pub-discount-expires">
                            ⏰ <?= e(t('discounts.expires')) ?>: <?= e(substr($d['ends_at'], 0, 10)) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($d['max_redemptions'])): ?>
                        <span class="pub-discount-expires">
                            👥 <?= (int)$d['max_redemptions'] - (int)($d['current_redemptions'] ?? 0) ?> <?= e(t('discounts.remaining')) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($d['terms_conditions'])): ?>
            <details class="pub-discount-terms">
                <summary><?= e(t('discounts.terms')) ?></summary>
                <p><?= e($d['terms_conditions']) ?></p>
            </details>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div><!-- /.pub-container -->

<script>
function pubCopyDiscount(code, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(function() {
            var orig = btn.textContent;
            btn.textContent = '✅';
            setTimeout(function() { btn.textContent = orig; }, 1800);
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = code; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
        var orig = btn.textContent; btn.textContent = '✅';
        setTimeout(function() { btn.textContent = orig; }, 1800);
    }
}
</script>

<style>
.pub-discount-card { background:var(--pub-surface); border:1px solid var(--pub-border);
  border-radius:var(--pub-radius); overflow:hidden; position:relative; }
.pub-discount-badge-top { position:absolute; top:0; right:0; background:var(--pub-accent,#F59E0B); color:#000;
  font-size:0.72rem; font-weight:800; padding:3px 10px; border-bottom-left-radius:var(--pub-radius); }
.pub-discount-inner { display:flex; gap:14px; padding:16px; align-items:flex-start; }
.pub-discount-icon { font-size:2rem; flex-shrink:0; }
.pub-discount-body { flex:1; min-width:0; }
.pub-discount-title { font-size:1rem; font-weight:700; margin:0 0 4px; color:var(--pub-text); }
.pub-discount-desc  { font-size:0.85rem; color:var(--pub-muted); margin:0 0 10px; }
.pub-discount-code-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
.pub-discount-code { font-family:monospace; font-size:0.9rem; font-weight:800; letter-spacing:2px;
  background:var(--pub-bg); border:1px dashed var(--pub-border); padding:4px 12px;
  border-radius:6px; color:var(--pub-primary); }
.pub-discount-expires { font-size:0.78rem; color:var(--pub-muted); }
.pub-discount-terms { padding:10px 16px 14px; border-top:1px solid var(--pub-border); }
.pub-discount-terms summary { font-size:0.82rem; color:var(--pub-muted); cursor:pointer; }
.pub-discount-terms p { font-size:0.82rem; color:var(--pub-muted); margin:6px 0 0; }
</style>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>