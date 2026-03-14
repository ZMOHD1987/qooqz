<?php
/**
 * frontend/public/compare.php
 * Product Comparison page — side-by-side table of up to 4 products.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';
$ctx  = $GLOBALS['PUB_CONTEXT'];
$lang = $ctx['lang'];
$dir  = $ctx['dir'];

$GLOBALS['PUB_PAGE_TITLE'] = e(t('products.compare_title', ['default' => 'Compare Products'])) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';
?>
<main class="pub-container" style="padding:28px 0 48px;">
    <div class="pub-section-head">
        <h1 class="pub-section-title">⚖️ <?= e(t('products.compare_title', ['default' => 'Compare Products'])) ?></h1>
        <button type="button" class="pub-btn pub-btn--ghost pub-btn--sm" onclick="pubClearCompare()">
            <?= e(t('products.compare_clear', ['default' => 'Clear All'])) ?>
        </button>
    </div>

    <div id="pubCompareTable" style="overflow-x:auto;">
        <p style="color:var(--pub-muted);text-align:center;padding:40px 0;" id="pubCompareEmpty">
            <?= e(t('products.compare_empty', ['default' => 'No products added to comparison yet. Browse products and click ⚖️ Compare.'])) ?>
        </p>
    </div>
</main>

<style>
.pub-compare-tbl { width: 100%; border-collapse: collapse; min-width: 400px; }
.pub-compare-tbl th,
.pub-compare-tbl td { padding: 12px 16px; border: 1px solid var(--pub-border); vertical-align: top; min-width: 160px; }
.pub-compare-tbl thead th { background: var(--pub-surface); text-align: center; }
.pub-compare-tbl tbody tr:nth-child(even) td:not(:first-child) { background: rgba(255,255,255,0.03); }
.pub-compare-tbl .cmp-label { font-weight: 600; color: var(--pub-muted); font-size: 0.85rem; white-space: nowrap; }
.pub-compare-img { width: 100%; max-height: 120px; object-fit: contain; display: block; margin: 0 auto 8px; }
.pub-compare-remove { background: none; border: none; cursor: pointer; color: var(--pub-error, #ef4444); font-size: 1.2rem; }
</style>

<script>
var PUB_LANG = <?= json_encode($lang) ?>;

function pubClearCompare() {
    if (!confirm(<?= json_encode(t('products.compare_clear_confirm', ['default' => 'Remove all from comparison?'])) ?>)) return;
    localStorage.removeItem('pub_compare');
    fetch('/api/public/compare/clear', {method:'POST', credentials:'include'});
    renderTable([]);
}

function pubRemoveFromCompare(pid) {
    var ids = (localStorage.getItem('pub_compare') || '').split(',').filter(Boolean);
    ids = ids.filter(function(x){ return x !== String(pid); });
    localStorage.setItem('pub_compare', ids.join(','));
    fetch('/api/public/compare/remove', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'product_id=' + pid
    });
    loadCompare();
}

function renderTable(products) {
    var wrap = document.getElementById('pubCompareTable');
    var empty = document.getElementById('pubCompareEmpty');
    if (!products || products.length === 0) {
        wrap.innerHTML = '<p style="color:var(--pub-muted);text-align:center;padding:40px 0;">' +
            <?= json_encode(t('products.compare_empty', ['default' => 'No products added to comparison yet.'])) ?> + '</p>';
        return;
    }
    var rows = [
        ['', products.map(function(p) {
            var img = p.image_url ? '<img src="'+p.image_url+'" class="pub-compare-img" alt="">' : '<div style="height:80px;display:flex;align-items:center;justify-content:center;font-size:2rem;">🖼️</div>';
            return '<div style="text-align:center;">'
                + '<button class="pub-compare-remove" onclick="pubRemoveFromCompare('+p.id+')" title="Remove">✕</button>'
                + '<a href="/frontend/public/product.php?id='+p.id+'" style="text-decoration:none;">'
                + img
                + '<strong style="display:block;font-size:0.9rem;">'+escHtml(p.name||p.slug||'')+'</strong></a></div>';
        })],
        [<?= json_encode(t('products.price',  ['default'=>'Price'])) ?>, products.map(function(p){ return p.price ? parseFloat(p.price).toFixed(2)+' '+(p.currency_code||'') : '—'; })],
        [<?= json_encode(t('products.in_stock',['default'=>'Stock'])) ?>, products.map(function(p){
            if (!p.stock_status) return '—';
            return p.stock_status === 'in_stock' ? '<span style="color:var(--pub-success,#10b981);">✅ '+<?= json_encode(t('products.in_stock')) ?>+'</span>'
                : '<span style="color:var(--pub-error,#ef4444);">❌ '+<?= json_encode(t('products.out_of_stock')) ?>+'</span>';
        })],
        [<?= json_encode(t('products.sku',['default'=>'SKU'])) ?>, products.map(function(p){ return escHtml(p.sku||'—'); })],
        [<?= json_encode(t('products.brand',['default'=>'Brand'])) ?>, products.map(function(p){ return escHtml(p.brand_name||'—'); })],
        [<?= json_encode(t('products.rating',['default'=>'Rating'])) ?>, products.map(function(p){
            return p.rating_average ? parseFloat(p.rating_average).toFixed(1)+' ★ ('+p.rating_count+')' : '—';
        })],
        [<?= json_encode(t('products.description',['default'=>'Description'])) ?>, products.map(function(p){
            var d = p.description || '—';
            return '<div style="max-height:80px;overflow:hidden;font-size:0.82rem;">'+escHtml(d.substring(0,200))+'</div>';
        })],
    ];
    var thead = '<thead><tr><th style="width:120px;"></th>';
    products.forEach(function(p){
        thead += '<th><a href="/frontend/public/product.php?id='+p.id+'" style="color:var(--pub-primary);text-decoration:none;font-size:0.88rem;">'+escHtml(p.name||'')+'</a></th>';
    });
    thead += '</tr></thead>';
    var tbody = '<tbody>';
    rows.forEach(function(r, ri){
        if (ri === 0) { tbody += '<tr>'; r[1].forEach(function(cell){ tbody += '<td style="text-align:center;">'+cell+'</td>'; }); tbody += '</tr>'; return; }
        tbody += '<tr><td class="cmp-label">'+r[0]+'</td>';
        r[1].forEach(function(cell){ tbody += '<td style="text-align:center;">'+cell+'</td>'; });
        tbody += '</tr>';
    });
    tbody += '</tbody>';
    wrap.innerHTML = '<table class="pub-compare-tbl">'+thead+tbody+'</table>';
}

function escHtml(s) {
    var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
}

function loadCompare() {
    // Always fetch from DB (authoritative) — localStorage may be empty on a different
    // device/session even when the server has items. Sync localStorage from API response.
    fetch('/api/public/compare?lang='+PUB_LANG, {credentials:'include'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            // ResponseFormatter wraps: {success:true, data:{ok:true, products:[...]}}
            var products = (d && d.data && Array.isArray(d.data.products)) ? d.data.products : [];
            if (products.length) {
                // Sync localStorage so compare badges etc. stay accurate
                localStorage.setItem('pub_compare', products.map(function(p){ return p.id; }).join(','));
                renderTable(products);
            } else if (!d || !d.success) {
                // API error or not logged in — fall back to localStorage IDs only (no details)
                renderTable([]);
            } else {
                // Logged in but no comparison items
                renderTable([]);
            }
        })
        .catch(function(){
            renderTable([]); // network error — show empty state
        });
}

document.addEventListener('DOMContentLoaded', loadCompare);
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>