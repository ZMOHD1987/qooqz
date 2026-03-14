<?php
/**
 * frontend/public/auctions.php
 * QOOQZ — Live Auctions Listing Page
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('auctions.page_title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('auctions.page_subtitle');
$GLOBALS['PUB_SEO']        = ['schema_type' => 'ItemList', 'name' => t('auctions.page_title')];

$page   = max(1, (int)($_GET['page'] ?? 1));
$status = in_array($_GET['status'] ?? 'active', ['active','scheduled','ended','all'], true) ? ($_GET['status'] ?? 'active') : 'active';
$type   = in_array($_GET['type'] ?? '', ['normal','reserve','buy_now','dutch','sealed_bid'], true) ? ($_GET['type'] ?? '') : '';

$per    = 24;
$offset = ($page - 1) * $per;
$auctions = [];

$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $aWhere  = '1=1';
        $aParams = [$lang]; // first param is for the translation subquery
        if ($status !== 'all')    { $aWhere .= ' AND a.status = ?';       $aParams[] = $status; }
        if ($type)                { $aWhere .= ' AND a.auction_type = ?'; $aParams[] = $type; }
        if ($tenantId)            { $aWhere .= ' AND a.tenant_id = ?';    $aParams[] = $tenantId; }
        // Use inline LIMIT/OFFSET (not bound params) — MySQL 5.x native prepares reject ? in LIMIT
        $limitSql = 'LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;

        $st = $pdo->prepare(
            "SELECT a.id, a.slug, a.auction_type, a.status, a.starting_price, a.current_price,
                    a.buy_now_price, a.bid_increment, a.total_bids, a.total_bidders,
                    a.start_date, a.end_date, a.is_featured, a.condition_type, a.quantity,
                    a.entity_id,
                    (SELECT c.code FROM currencies c WHERE c.id = a.currency_id LIMIT 1) AS currency_code,
                    (SELECT i.url FROM images i WHERE i.owner_id = a.product_id ORDER BY i.id ASC LIMIT 1) AS image_url,
                    (SELECT at2.title FROM auction_translations at2
                     WHERE at2.auction_id = a.id AND at2.language_code = ? LIMIT 1) AS title
             FROM auctions a
             WHERE $aWhere
             ORDER BY a.is_featured DESC, a.end_date ASC
             $limitSql"
        );
        $st->execute($aParams);
        $auctions = $st->fetchAll(PDO::FETCH_ASSOC);

        // Fallback title to slug when translation is missing
        foreach ($auctions as &$a) {
            if (empty($a['title'])) $a['title'] = $a['slug'];
        }
        unset($a);
    } catch (Throwable $e) {
        error_log('[auctions.php] ' . $e->getMessage());
        echo '<!-- [auctions] query error: ' . htmlspecialchars($e->getMessage()) . ' -->';
    }
} else {
    // HTTP fallback
    $qs = http_build_query(array_filter([
        'lang' => $lang, 'page' => $page, 'per' => $per,
        'tenant_id' => $tenantId ?: null, 'status' => $status, 'type' => $type ?: null,
    ]));
    $apiData  = pub_fetch(pub_api_url('public/auctions') . '?' . $qs);
    $auctions = $apiData['data']['auctions'] ?? $apiData['auctions'] ?? [];
}
$total = count($auctions);

function auc_countdown_label(string $endDate, string $lang): string {
    $diff = strtotime($endDate) - time();
    if ($diff <= 0) return $lang === 'ar' ? 'انتهى' : 'Ended';
    $d = floor($diff / 86400);
    $h = floor(($diff % 86400) / 3600);
    $m = floor(($diff % 3600) / 60);
    $s = $diff % 60;
    if ($d > 0) return $lang === 'ar' ? "{$d}ي {$h}س" : "{$d}d {$h}h";
    if ($h > 0) return $lang === 'ar' ? "{$h}س {$m}د" : "{$h}h {$m}m";
    return $lang === 'ar' ? "{$m}:{$s}" : "{$m}m {$s}s";
}

include dirname(__DIR__) . '/partials/header.php';

// Resolve auction card style from DB card_styles (card_type='auction')
$_auctionCardStyle = pub_card_inline_style('auction');
$_auctionCardClass = pub_card_css_class('auction');
$_auctionImgStyle  = pub_card_img_style('auction', '4/3');
?>
<div class="pub-page pub-auctions-page">
  <div class="pub-container">

    <!-- Page Header -->
    <div class="pub-page-header">
      <h1 class="pub-page-title"><?= e(t('auctions.page_title')) ?></h1>
      <p class="pub-page-desc"><?= e(t('auctions.page_subtitle')) ?></p>
    </div>

    <!-- Filters -->
    <form class="pub-filter-bar" method="get" action="">
      <input type="hidden" name="lang" value="<?= e($lang) ?>">
      <select name="status" class="pub-filter-select" onchange="this.form.submit()">
        <option value="active"    <?= $status==='active'    ? 'selected' : '' ?>><?= e(t('auctions.status_active')) ?></option>
        <option value="scheduled" <?= $status==='scheduled' ? 'selected' : '' ?>><?= e(t('auctions.status_scheduled')) ?></option>
        <option value="ended"     <?= $status==='ended'     ? 'selected' : '' ?>><?= e(t('auctions.status_ended')) ?></option>
        <option value="all"       <?= $status==='all'       ? 'selected' : '' ?>><?= e(t('auctions.status_all')) ?></option>
      </select>
      <select name="type" class="pub-filter-select" onchange="this.form.submit()">
        <option value=""          <?= !$type ? 'selected' : '' ?>><?= e(t('auctions.all_types')) ?></option>
        <option value="normal"    <?= $type==='normal'    ? 'selected' : '' ?>><?= e(t('auctions.type_normal')) ?></option>
        <option value="reserve"   <?= $type==='reserve'   ? 'selected' : '' ?>><?= e(t('auctions.type_reserve')) ?></option>
        <option value="buy_now"   <?= $type==='buy_now'   ? 'selected' : '' ?>><?= e(t('auctions.type_buy_now')) ?></option>
        <option value="dutch"     <?= $type==='dutch'     ? 'selected' : '' ?>><?= e(t('auctions.type_dutch')) ?></option>
        <option value="sealed_bid" <?= $type==='sealed_bid'? 'selected' : '' ?>><?= e(t('auctions.type_sealed_bid')) ?></option>
      </select>
    </form>

    <!-- Grid -->
    <?php if (empty($auctions)): ?>
      <div class="pub-empty-state">
        <div class="pub-empty-icon">🔨</div>
        <p><?= e(t('auctions.none_found')) ?></p>
      </div>
    <?php else: ?>
    <div class="pub-grid pub-auction-grid">
      <?php foreach ($auctions as $a):
        $aId     = (int)($a['id'] ?? 0);
        $aSlug   = $a['slug'] ?? '';
        $aTitle  = $a['title'] ?? $aSlug;
        $aImg    = $a['image_url'] ?? '';
        $aCurr   = $a['currency_code'] ?? '';
        $aPrice  = number_format((float)($a['current_price'] ?? 0), 2) . ($aCurr ? ' ' . $aCurr : '');
        $aBuyNow = $a['buy_now_price'] ? number_format((float)$a['buy_now_price'], 2) . ($aCurr ? ' ' . $aCurr : '') : '';
        $aEnd    = $a['end_date'] ?? '';
        $aBids   = (int)($a['total_bids'] ?? 0);
        $aStatus = $a['status'] ?? '';
        $aType   = $a['auction_type'] ?? 'normal';
        $aFeat   = !empty($a['is_featured']);
        $aCountdown = $aEnd ? auc_countdown_label($aEnd, $lang) : '';
        $aUrl    = '/frontend/public/auction.php?id=' . $aId;
      ?>
      <div class="pub-auction-card<?= $aFeat ? ' pub-auction-featured' : '' ?><?= $_auctionCardClass ? ' ' . $_auctionCardClass : '' ?>"<?= $_auctionCardStyle ? ' style="' . e($_auctionCardStyle) . '"' : '' ?>>
        <?php if ($aFeat): ?><div class="pub-auction-badge-feat"><?= e(t('auctions.featured')) ?></div><?php endif; ?>
        <a href="<?= e($aUrl) ?>" class="pub-auction-img-wrap">
          <?php if ($aImg): ?>
            <img src="<?= e($aImg) ?>" alt="<?= e($aTitle) ?>" class="pub-auction-img" loading="lazy">
          <?php else: ?>
            <div class="pub-auction-img-placeholder">🔨</div>
          <?php endif; ?>
          <?php if ($aStatus === 'active' && $aEnd): ?>
          <div class="pub-auction-timer" data-end="<?= e($aEnd) ?>"><?= e($aCountdown) ?></div>
          <?php elseif ($aStatus === 'ended' || $aStatus === 'sold'): ?>
          <div class="pub-auction-status-ended"><?= e(t('auctions.ended')) ?></div>
          <?php elseif ($aStatus === 'scheduled'): ?>
          <div class="pub-auction-status-sched"><?= e(t('auctions.scheduled')) ?></div>
          <?php endif; ?>
        </a>
        <div class="pub-auction-body">
          <div class="pub-auction-type-tag"><?= e(t('auctions.type_' . $aType)) ?></div>
          <a href="<?= e($aUrl) ?>" class="pub-auction-title"><?= e($aTitle) ?></a>
          <div class="pub-auction-price-row">
            <span class="pub-auction-price-label"><?= e(t('auctions.current_bid')) ?></span>
            <span class="pub-auction-price"><?= e($aPrice) ?></span>
          </div>
          <?php if ($aBuyNow): ?>
          <div class="pub-auction-buynow-row">
            <span class="pub-auction-buynow-label"><?= e(t('auctions.buy_now')) ?>:</span>
            <span class="pub-auction-buynow-price"><?= e($aBuyNow) ?></span>
          </div>
          <?php endif; ?>
          <div class="pub-auction-meta-row">
            <span>🔨 <?= e($aBids) ?> <?= e(t('auctions.bids')) ?></span>
            <?php if ($aCountdown && $aStatus === 'active'): ?>
            <span class="pub-auction-timer-inline" data-end="<?= e($aEnd) ?>">⏱ <?= e($aCountdown) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($aStatus === 'active'): ?>
          <a href="<?= e($aUrl) ?>" class="pub-btn pub-btn--primary pub-auction-bid-btn"><?= e(t('auctions.bid_now')) ?></a>
          <?php else: ?>
          <a href="<?= e($aUrl) ?>" class="pub-btn pub-btn--ghost"><?= e(t('auctions.view')) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Live countdown for all auction timers on the listing
(function(){
  function updateTimers() {
    document.querySelectorAll('[data-end]').forEach(function(el) {
      var end = new Date(el.dataset.end.replace(/\s/, 'T')).getTime();
      var now = Date.now();
      var diff = Math.floor((end - now) / 1000);
      if (diff <= 0) { el.textContent = '<?= addslashes(t('auctions.ended')) ?>'; return; }
      var d = Math.floor(diff / 86400);
      var h = Math.floor((diff % 86400) / 3600);
      var m = Math.floor((diff % 3600) / 60);
      var s = diff % 60;
      if (d > 0) el.textContent = '⏱ ' + d + 'd ' + h + 'h';
      else if (h > 0) el.textContent = '⏱ ' + h + 'h ' + m + 'm';
      else el.textContent = '⏱ ' + m + ':' + String(s).padStart(2,'0');
    });
  }
  updateTimers();
  setInterval(updateTimers, 1000);
})();
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>