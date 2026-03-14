<?php
/**
 * frontend/public/auction.php
 * QOOQZ — Live Auction Detail & Bidding Page
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];
$_user    = $ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);

$auctionId = (int)($_GET['id'] ?? 0);
if (!$auctionId) {
    header('Location: /frontend/public/auctions.php');
    exit;
}

$GLOBALS['PUB_APP_NAME']  = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH'] = '/frontend/public';

$pdo = pub_get_pdo();

// Load auction via PDO (more reliable than HTTP on LiteSpeed)
$auction = null;
$bids    = [];
$isWatching  = false;
$autoBidData = null;

if ($pdo) {
    try {
        $st = $pdo->prepare(
            "SELECT a.*,
                    COALESCE((SELECT c.code FROM currencies c WHERE c.id = a.currency_id LIMIT 1), 'SAR') AS currency_code,
                    (SELECT at2.title FROM auction_translations at2 WHERE at2.auction_id=a.id AND at2.language_code=? LIMIT 1) AS title,
                    (SELECT at2.description FROM auction_translations at2 WHERE at2.auction_id=a.id AND at2.language_code=? LIMIT 1) AS description,
                    (SELECT at2.terms_conditions FROM auction_translations at2 WHERE at2.auction_id=a.id AND at2.language_code=? LIMIT 1) AS terms_conditions,
                    (SELECT i.url FROM images i WHERE i.owner_id = a.product_id ORDER BY i.id ASC LIMIT 1) AS image_url
             FROM auctions a WHERE a.id=? LIMIT 1"
        );
        $st->execute([$lang, $lang, $lang, $auctionId]);
        $auction = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { error_log('[auction.php] ' . $e->getMessage()); }

    if (!$auction) {
        // Fallback: try without translation
        try {
            $st = $pdo->prepare("SELECT * FROM auctions WHERE id=? LIMIT 1");
            $st->execute([$auctionId]);
            $auction = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($auction) $auction['title'] = $auction['slug'];
        } catch (Throwable $e) {}
    }

    if ($auction) {
        // Load bid history
        try {
            $st = $pdo->prepare(
                "SELECT ab.id, ab.bid_amount, ab.bid_type, ab.is_winning, ab.created_at,
                        COALESCE(u.username, CONCAT('User#', ab.user_id)) AS bidder
                 FROM auction_bids ab LEFT JOIN users u ON u.id = ab.user_id
                 WHERE ab.auction_id=? ORDER BY ab.bid_amount DESC, ab.created_at DESC LIMIT 20"
            );
            $st->execute([$auctionId]);
            $bids = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        // Watch status
        if ($_isLoggedIn && !empty($_user['id'])) {
            try {
                $st = $pdo->prepare('SELECT id FROM auction_watchers WHERE auction_id=? AND user_id=? LIMIT 1');
                $st->execute([$auctionId, $_user['id']]);
                $isWatching = (bool)$st->fetchColumn();
            } catch (Throwable $e) {}

            // Auto-bid
            try {
                $st = $pdo->prepare('SELECT max_bid_amount, is_active FROM auto_bid_settings WHERE auction_id=? AND user_id=? AND is_active=1 LIMIT 1');
                $st->execute([$auctionId, $_user['id']]);
                $autoBidData = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {}
        }
    }
}

if (!$auction) {
    $GLOBALS['PUB_PAGE_TITLE'] = t('common.not_found') . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<div class="pub-container" style="padding:80px 20px;text-align:center"><h2>🔨</h2><p>' . e(t('auctions.not_found')) . '</p><a href="/frontend/public/auctions.php" class="pub-btn pub-btn--primary">' . e(t('auctions.back_to_list')) . '</a></div>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$aTitle     = $auction['title'] ?: ($auction['slug'] ?? '');
$aImg       = $auction['image_url'] ?? '';
$aStatus    = $auction['status'] ?? 'draft';
$aType      = $auction['auction_type'] ?? 'normal';
$aCurrent   = (float)($auction['current_price'] ?? 0);
$aStarting  = (float)($auction['starting_price'] ?? 0);
$aIncrement = (float)($auction['bid_increment'] ?? 5);
$aBuyNow    = (float)($auction['buy_now_price'] ?? 0);
$aReserve   = (float)($auction['reserve_price'] ?? 0);
$aTotalBids = (int)($auction['total_bids'] ?? 0);
$aTotalBidders = (int)($auction['total_bidders'] ?? 0);
$aEndDate   = $auction['end_date'] ?? '';
$aWinner    = (int)($auction['winner_user_id'] ?? 0);
$aIsActive  = $aStatus === 'active';
$aIsEnded   = in_array($aStatus, ['ended', 'sold', 'cancelled']);
$aMinNextBid = $aCurrent + $aIncrement;
$aCreatedBy  = (int)($auction['created_by'] ?? 0);
$aIsOwner    = $_isLoggedIn && $aCreatedBy > 0 && $aCreatedBy === (int)($_user['id'] ?? 0);

$GLOBALS['PUB_PAGE_TITLE'] = e($aTitle) . ' — ' . e(t('auctions.page_title')) . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = substr($auction['description'] ?? '', 0, 160);
$GLOBALS['PUB_SEO'] = [
    'schema_type' => 'Product',
    'name'        => $aTitle,
    'image'       => $aImg,
    'og_image'    => $aImg,
];

// Resolve auction card style from DB card_styles (card_type='auction')
$_auctionDetailCardStyle = pub_card_inline_style('auction');
$_auctionDetailCardClass = pub_card_css_class('auction');

include dirname(__DIR__) . '/partials/header.php';
?>
<style>
.pub-auction-detail { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
.pub-auc-grid { display: grid; grid-template-columns: 1fr 380px; gap: 28px; align-items: start; }
@media (max-width: 820px) { .pub-auc-grid { grid-template-columns: 1fr; } }
.pub-auc-gallery img { width: 100%; border-radius: var(--pub-radius); object-fit: cover; max-height: 400px; }
.pub-auc-img-placeholder { width: 100%; aspect-ratio: 4/3; background: var(--pub-surface); display: flex; align-items: center; justify-content: center; font-size: 60px; border-radius: var(--pub-radius); }
.pub-auc-panel { background: var(--pub-surface); border-radius: var(--pub-radius); padding: 24px; box-shadow: var(--pub-shadow); }
.pub-auc-status-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
.pub-auc-type-tag { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: var(--pub-primary); color: #fff; text-transform: uppercase; }
.pub-auc-status-tag { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.pub-auc-status-active { background: #22c55e; color: #fff; }
.pub-auc-status-ended  { background: #ef4444; color: #fff; }
.pub-auc-status-scheduled { background: #f59e0b; color: #fff; }
.pub-auc-title { font-size: 1.4rem; font-weight: 700; color: var(--pub-text); margin: 12px 0; }
.pub-auc-countdown-box { background: linear-gradient(135deg, #1a1a2e, #16213e); color: #fff; border-radius: var(--pub-radius); padding: 18px 20px; text-align: center; margin-bottom: 18px; }
.pub-auc-countdown-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: .7; margin-bottom: 6px; }
.pub-auc-countdown { display: flex; justify-content: center; gap: 12px; }
.pub-auc-time-unit { text-align: center; }
.pub-auc-time-num { display: block; font-size: 2rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.pub-auc-time-lbl { display: block; font-size: 10px; opacity: .6; text-transform: uppercase; }
.pub-auc-time-sep { font-size: 2rem; font-weight: 700; line-height: 1.2; opacity: .5; }
.pub-auc-price-box { border: 2px solid var(--pub-border); border-radius: var(--pub-radius); padding: 16px 20px; margin-bottom: 16px; }
.pub-auc-price-label { font-size: 12px; color: var(--pub-muted); margin-bottom: 2px; }
.pub-auc-price-val { font-size: 2rem; font-weight: 800; color: var(--pub-primary); }
.pub-auc-price-currency { font-size: 1rem; font-weight: 400; opacity: .7; }
.pub-auc-stats-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
.pub-auc-stat { text-align: center; flex: 1; }
.pub-auc-stat-val { font-size: 1.3rem; font-weight: 700; display: block; }
.pub-auc-stat-lbl { font-size: 11px; color: var(--pub-muted); text-transform: uppercase; }
.pub-auc-bid-form { margin-bottom: 16px; }
.pub-auc-bid-row { display: flex; gap: 8px; }
.pub-auc-bid-input { flex: 1; padding: 12px 14px; border: 2px solid var(--pub-border); border-radius: var(--pub-radius-sm); font-size: 1rem; font-family: inherit; transition: border-color .2s; }
.pub-auc-bid-input:focus { border-color: var(--pub-primary); outline: none; }
.pub-auc-bid-btn { padding: 12px 20px; background: var(--pub-primary); color: #fff; border: none; border-radius: var(--pub-radius-sm); font-weight: 700; cursor: pointer; font-size: 1rem; white-space: nowrap; }
.pub-auc-bid-btn:hover { opacity: .88; }
.pub-auc-bid-hint { font-size: 12px; color: var(--pub-muted); margin-top: 4px; }
.pub-auc-auto-bid-row { background: var(--pub-bg); border: 1px dashed var(--pub-border); border-radius: var(--pub-radius-sm); padding: 14px; margin-bottom: 12px; }
.pub-auc-auto-bid-title { font-size: 13px; font-weight: 600; margin-bottom: 8px; }
.pub-auc-buynow-btn { width: 100%; padding: 14px; background: #22c55e; color: #fff; border: none; border-radius: var(--pub-radius-sm); font-weight: 700; font-size: 1rem; cursor: pointer; margin-bottom: 10px; }
.pub-auc-buynow-btn:hover { background: #16a34a; }
.pub-auc-watch-btn { width: 100%; padding: 10px; background: transparent; border: 2px solid var(--pub-border); border-radius: var(--pub-radius-sm); cursor: pointer; font-size: 13px; color: var(--pub-muted); display: flex; align-items: center; justify-content: center; gap: 6px; }
.pub-auc-watch-btn.active { border-color: #ef4444; color: #ef4444; }
.pub-auc-msg { padding: 10px 14px; border-radius: var(--pub-radius-sm); font-size: 13px; margin-top: 8px; display: none; }
.pub-auc-msg.success { background: #d1fae5; color: #065f46; }
.pub-auc-msg.error   { background: #fee2e2; color: #991b1b; }
.pub-auc-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--pub-border); margin: 32px 0 16px; }
.pub-auc-tab-btn { padding: 10px 20px; border: none; background: none; cursor: pointer; font-size: 14px; font-weight: 600; color: var(--pub-muted); border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .2s; }
.pub-auc-tab-btn.active { color: var(--pub-primary); border-bottom-color: var(--pub-primary); }
.pub-auc-tab-pane { display: none; }
.pub-auc-tab-pane.active { display: block; }
.pub-auc-bid-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.pub-auc-bid-table th { text-align: start; padding: 8px 12px; background: var(--pub-surface); font-size: 12px; text-transform: uppercase; color: var(--pub-muted); }
.pub-auc-bid-table td { padding: 8px 12px; border-bottom: 1px solid var(--pub-border); }
.pub-auc-bid-table tr:first-child td { font-weight: 700; color: var(--pub-primary); }
.pub-auc-winner-badge { display: inline-block; background: #f59e0b; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px; margin-inline-start: 4px; }
.pub-auc-ended-banner { background: linear-gradient(135deg, #1f2937, #374151); color: #fff; border-radius: var(--pub-radius); padding: 20px 24px; text-align: center; margin-bottom: 18px; }
</style>

<div class="pub-auction-detail">

  <!-- Breadcrumb -->
  <nav class="pub-breadcrumb">
    <a href="/frontend/public/auctions.php"><?= e(t('auctions.page_title')) ?></a>
    <span class="pub-bc-sep">›</span>
    <span><?= e($aTitle) ?></span>
  </nav>

  <div class="pub-auc-grid">
    <!-- Left: Image -->
    <div>
      <div class="pub-auc-gallery">
        <?php if ($aImg): ?>
          <img src="<?= e($aImg) ?>" alt="<?= e($aTitle) ?>" id="aucMainImg">
        <?php else: ?>
          <div class="pub-auc-img-placeholder">🔨</div>
        <?php endif; ?>
      </div>

      <!-- Description tab section (below image on desktop) -->
      <div class="pub-auc-tabs">
        <button class="pub-auc-tab-btn active" onclick="aucTab(this,'tab-desc')"><?= e(t('auctions.tab_desc')) ?></button>
        <button class="pub-auc-tab-btn" onclick="aucTab(this,'tab-bids')"><?= e(t('auctions.tab_bids')) ?> (<?= count($bids) ?>)</button>
        <?php if (!empty($auction['terms_conditions'])): ?>
        <button class="pub-auc-tab-btn" onclick="aucTab(this,'tab-terms')"><?= e(t('auctions.tab_terms')) ?></button>
        <?php endif; ?>
      </div>

      <div class="pub-auc-tab-pane active" id="tab-desc">
        <?php if (!empty($auction['description'])): ?>
          <div class="pub-product-desc"><?= nl2br(e($auction['description'])) ?></div>
        <?php else: ?>
          <p class="pub-muted-text"><?= e(t('auctions.no_desc')) ?></p>
        <?php endif; ?>

        <!-- Condition + Shipping -->
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:16px;">
          <?php if (!empty($auction['condition_type'])): ?>
          <div class="pub-auction-detail-meta"><span class="pub-auction-detail-label"><?= e(t('auctions.condition')) ?>:</span> <?= e(t('auctions.cond_' . $auction['condition_type'])) ?></div>
          <?php endif; ?>
          <?php if ((float)($auction['shipping_cost'] ?? 0) > 0): ?>
          <div class="pub-auction-detail-meta"><span class="pub-auction-detail-label"><?= e(t('auctions.shipping')) ?>:</span> <?= number_format((float)$auction['shipping_cost'], 2) ?></div>
          <?php elseif (isset($auction['shipping_cost'])): ?>
          <div class="pub-auction-detail-meta"><span style="color:#22c55e">✓</span> <?= e(t('auctions.free_shipping')) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="pub-auc-tab-pane" id="tab-bids">
        <?php if (empty($bids)): ?>
          <p class="pub-muted-text"><?= e(t('auctions.no_bids_yet')) ?></p>
        <?php else: ?>
        <table class="pub-auc-bid-table">
          <thead>
            <tr><th><?= e(t('auctions.bidder')) ?></th><th><?= e(t('auctions.bid_amount')) ?></th><th><?= e(t('auctions.bid_time')) ?></th></tr>
          </thead>
          <tbody id="aucBidTableBody">
            <?php foreach ($bids as $b): ?>
            <tr>
              <td><?= e($b['bidder']) ?><?php if ($b['is_winning']): ?><span class="pub-auc-winner-badge">🏆</span><?php endif; ?></td>
              <td><?= e(number_format((float)$b['bid_amount'], 2)) ?></td>
              <td><?= e(date('M j, H:i', strtotime($b['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <?php if (!empty($auction['terms_conditions'])): ?>
      <div class="pub-auc-tab-pane" id="tab-terms">
        <div class="pub-product-desc"><?= nl2br(e($auction['terms_conditions'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right: Bid Panel -->
    <div class="pub-auc-panel<?= $_auctionDetailCardClass ? ' ' . $_auctionDetailCardClass : '' ?>"
         id="aucBidPanel"<?= $_auctionDetailCardStyle ? ' style="' . e($_auctionDetailCardStyle) . '"' : '' ?>>

      <!-- Status bar -->
      <div class="pub-auc-status-bar">
        <span class="pub-auc-type-tag"><?= e(t('auctions.type_' . $aType)) ?></span>
        <span class="pub-auc-status-tag pub-auc-status-<?= e($aIsActive ? 'active' : ($aIsEnded ? 'ended' : 'scheduled')) ?>">
          <?= $aIsActive ? '🟢 ' . e(t('auctions.status_active')) : ($aIsEnded ? '🔴 ' . e(t('auctions.ended')) : '🟡 ' . e(t('auctions.status_scheduled'))) ?>
        </span>
      </div>

      <h1 class="pub-auc-title"><?= e($aTitle) ?></h1>

      <!-- Countdown -->
      <?php if ($aIsActive && $aEndDate): ?>
      <div class="pub-auc-countdown-box">
        <div class="pub-auc-countdown-label"><?= e(t('auctions.ends_in')) ?></div>
        <div class="pub-auc-countdown" id="aucCountdown">
          <div class="pub-auc-time-unit"><span class="pub-auc-time-num" id="aucDays">--</span><span class="pub-auc-time-lbl"><?= e(t('auctions.days')) ?></span></div>
          <span class="pub-auc-time-sep">:</span>
          <div class="pub-auc-time-unit"><span class="pub-auc-time-num" id="aucHours">--</span><span class="pub-auc-time-lbl"><?= e(t('auctions.hours')) ?></span></div>
          <span class="pub-auc-time-sep">:</span>
          <div class="pub-auc-time-unit"><span class="pub-auc-time-num" id="aucMins">--</span><span class="pub-auc-time-lbl"><?= e(t('auctions.mins')) ?></span></div>
          <span class="pub-auc-time-sep">:</span>
          <div class="pub-auc-time-unit"><span class="pub-auc-time-num" id="aucSecs">--</span><span class="pub-auc-time-lbl"><?= e(t('auctions.secs')) ?></span></div>
        </div>
      </div>
      <?php elseif ($aIsEnded): ?>
      <div class="pub-auc-ended-banner">
        🏁 <?= e(t('auctions.auction_ended')) ?>
        <?php if ($aWinner): ?><br><small><?= e(t('auctions.sold_for')) ?> <?= number_format((float)($auction['winning_amount'] ?? 0), 2) ?></small><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Current Price -->
      <div class="pub-auc-price-box">
        <div class="pub-auc-price-label"><?= e(t('auctions.current_bid')) ?></div>
        <div class="pub-auc-price-val" id="aucCurrentPrice">
          <?= number_format($aCurrent, 2) ?>
          <span class="pub-auc-price-currency"><?= e($auction['currency_code'] ?? 'SAR') ?></span>
        </div>
      </div>

      <!-- Stats -->
      <div class="pub-auc-stats-row">
        <div class="pub-auc-stat"><span class="pub-auc-stat-val" id="aucTotalBids"><?= $aTotalBids ?></span><span class="pub-auc-stat-lbl"><?= e(t('auctions.bids')) ?></span></div>
        <div class="pub-auc-stat"><span class="pub-auc-stat-val" id="aucBidders"><?= $aTotalBidders ?></span><span class="pub-auc-stat-lbl"><?= e(t('auctions.bidders')) ?></span></div>
        <?php if ($aStarting): ?>
        <div class="pub-auc-stat"><span class="pub-auc-stat-val"><?= number_format($aStarting, 0) ?></span><span class="pub-auc-stat-lbl"><?= e(t('auctions.starting')) ?></span></div>
        <?php endif; ?>
      </div>

      <?php if ($aIsActive): ?>
        <?php if ($aIsOwner): ?>
        <!-- Auction owner — cannot bid -->
        <div style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:14px;text-align:center;font-size:0.9rem;color:#f59e0b;margin-bottom:12px">
          🔒 <?= e(t('auctions.owner_cannot_bid')) ?>
        </div>
        <?php elseif ($_isLoggedIn): ?>

        <!-- Manual Bid Form -->
        <form class="pub-auc-bid-form" onsubmit="aucPlaceBid(event)" id="aucBidForm">
          <div class="pub-auc-bid-row">
            <input type="number" id="aucBidInput" class="pub-auc-bid-input" name="bid_amount"
              min="<?= $aMinNextBid ?>" step="<?= $aIncrement ?>" value="<?= $aMinNextBid ?>"
              placeholder="<?= e(t('auctions.enter_bid')) ?>">
            <button type="submit" class="pub-auc-bid-btn">🔨 <?= e(t('auctions.place_bid')) ?></button>
          </div>
          <div class="pub-auc-bid-hint"><?= e(t('auctions.min_bid')) ?>: <strong><?= number_format($aMinNextBid, 2) ?></strong> (<?= e(t('auctions.increment')) ?>: +<?= number_format($aIncrement, 2) ?>)</div>
        </form>

        <!-- Auto-bid -->
        <div class="pub-auc-auto-bid-row">
          <div class="pub-auc-auto-bid-title">🤖 <?= e(t('auctions.auto_bid')) ?></div>
          <form onsubmit="aucSetAutoBid(event)" id="aucAutoBidForm" style="display:flex;gap:8px;">
            <input type="number" id="aucMaxBidInput" class="pub-auc-bid-input" name="max_bid_amount"
              min="<?= $aMinNextBid ?>" step="<?= $aIncrement ?>"
              value="<?= $autoBidData ? (float)$autoBidData['max_bid_amount'] : '' ?>"
              placeholder="<?= e(t('auctions.max_bid')) ?>">
            <button type="submit" class="pub-auc-bid-btn" style="white-space:nowrap"><?= e(t('auctions.set_auto')) ?></button>
          </form>
          <?php if ($autoBidData): ?>
          <p style="font-size:12px;color:#22c55e;margin-top:6px">✓ <?= e(t('auctions.auto_bid_active')) ?>: <?= number_format((float)$autoBidData['max_bid_amount'], 2) ?></p>
          <?php endif; ?>
        </div>

        <!-- Buy Now -->
        <?php if ($aBuyNow > 0): ?>
        <button class="pub-auc-buynow-btn" onclick="aucBuyNow()">
          ⚡ <?= e(t('auctions.buy_now')) ?> — <?= number_format($aBuyNow, 2) ?>
        </button>
        <?php endif; ?>

        <!-- Watch -->
        <button class="pub-auc-watch-btn<?= $isWatching ? ' active' : '' ?>" id="aucWatchBtn" onclick="aucToggleWatch()">
          <?= $isWatching ? '❤️' : '🤍' ?> <?= e($isWatching ? t('auctions.watching') : t('auctions.watch')) ?>
        </button>

        <!-- Messages -->
        <div class="pub-auc-msg" id="aucMsg"></div>

        <?php else: ?>
        <!-- Not logged in -->
        <a href="/frontend/login.php?redirect=<?= urlencode('/frontend/public/auction.php?id=' . $auctionId) ?>" class="pub-btn pub-btn--primary" style="width:100%;text-align:center;display:block;padding:14px">
          🔑 <?= e(t('auctions.login_to_bid')) ?>
        </a>
        <?php endif; // elseif logged in / owner check ?>
      <?php endif; // aIsActive ?>
    </div>
  </div>
</div>

<script>
var AUC_ID     = <?= (int)$auctionId ?>;
var AUC_END    = <?= json_encode($aEndDate) ?>;
var AUC_ACTIVE = <?= $aIsActive ? 'true' : 'false' ?>;

// ---- Countdown ----
function aucUpdateCountdown() {
  if (!AUC_END || !AUC_ACTIVE) return;
  var end  = new Date(AUC_END.replace(/\s/, 'T')).getTime();
  var diff = Math.floor((end - Date.now()) / 1000);
  if (diff <= 0) {
    document.getElementById('aucDays').textContent = '00';
    document.getElementById('aucHours').textContent = '00';
    document.getElementById('aucMins').textContent = '00';
    document.getElementById('aucSecs').textContent = '00';
    return;
  }
  document.getElementById('aucDays').textContent  = String(Math.floor(diff / 86400)).padStart(2,'0');
  document.getElementById('aucHours').textContent = String(Math.floor((diff % 86400) / 3600)).padStart(2,'0');
  document.getElementById('aucMins').textContent  = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
  document.getElementById('aucSecs').textContent  = String(diff % 60).padStart(2,'0');
}
if (AUC_ACTIVE) { aucUpdateCountdown(); setInterval(aucUpdateCountdown, 1000); }

// ---- Live status poll (every 8s) ----
function aucPollStatus() {
  if (!AUC_ACTIVE) return;
  fetch('/api/public/auctions/' + AUC_ID + '/status', {credentials:'include'})
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.success || !d.data || !d.data.status) return;
      var s = d.data.status;
      var priceEl = document.getElementById('aucCurrentPrice');
      if (priceEl) {
        var priceSpan = priceEl.querySelector('.pub-auc-price-currency');
        var priceText = parseFloat(s.current_price || 0).toFixed(2) + ' ';
        if (priceSpan) {
          priceEl.childNodes[0].textContent = priceText;
        } else {
          priceEl.textContent = priceText;
        }
      }
      var bidsEl = document.getElementById('aucTotalBids');
      if (bidsEl) bidsEl.textContent = s.total_bids || 0;
      var bdrsEl = document.getElementById('aucBidders');
      if (bdrsEl) bdrsEl.textContent = s.total_bidders || 0;
      // Update min bid input
      var bidInput = document.getElementById('aucBidInput');
      if (bidInput) {
        var inc = <?= (float)$aIncrement ?>;
        var newMin = parseFloat(s.current_price || 0) + inc;
        bidInput.min = newMin;
        if (parseFloat(bidInput.value) < newMin) bidInput.value = newMin.toFixed(2);
      }
      // Also refresh bid history on every poll
      aucRefreshBidHistory();
    }).catch(function(){});
}

// Reload bid history via API (called after placing a bid)
function aucRefreshBidHistory() {
  fetch('/api/public/auctions/' + AUC_ID, {credentials:'include'})
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.success || !d.data || !d.data.auction || !Array.isArray(d.data.bids)) return;
      var tbody = document.getElementById('aucBidTableBody');
      if (!tbody) return;
      var rows = '';
      d.data.bids.forEach(function(b) {
        rows += '<tr><td>' + (b.bidder || '') + (b.is_winning ? '<span class="pub-auc-winner-badge">🏆</span>' : '') + '</td>'
              + '<td>' + parseFloat(b.bid_amount || 0).toFixed(2) + '</td>'
              + '<td>' + (b.created_at ? b.created_at.substr(0,16).replace('T',' ') : '') + '</td></tr>';
      });
      if (rows) tbody.innerHTML = rows;
    }).catch(function(){});
}
if (AUC_ACTIVE) { setInterval(aucPollStatus, 8000); }

// ---- Place bid ----
function aucPlaceBid(e) {
  e.preventDefault();
  var amount = parseFloat(document.getElementById('aucBidInput').value || 0);
  if (!amount) { aucShowMsg('<?= addslashes(t('auctions.enter_bid')) ?>', 'error'); return; }
  aucShowMsg('...', 'success');
  var body = new FormData();
  body.append('bid_amount', amount);
  fetch('/api/public/auctions/' + AUC_ID + '/bid', {method:'POST', credentials:'include', body: body})
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.success) {
        aucShowMsg('✅ <?= addslashes(t('auctions.bid_placed')) ?>', 'success');
        aucPollStatus();
        aucRefreshBidHistory();
      } else {
        aucShowMsg('❌ ' + (d.message || '<?= addslashes(t('auctions.bid_failed')) ?>'), 'error');
      }
    }).catch(function() { aucShowMsg('❌ <?= addslashes(t('auctions.network_error')) ?>', 'error'); });
}

// ---- Set auto-bid ----
function aucSetAutoBid(e) {
  e.preventDefault();
  var max = parseFloat(document.getElementById('aucMaxBidInput').value || 0);
  if (!max) return;
  var body = new FormData();
  body.append('max_bid_amount', max);
  fetch('/api/public/auctions/' + AUC_ID + '/auto-bid', {method:'POST', credentials:'include', body: body})
    .then(function(r) { return r.json(); })
    .then(function(d) {
      aucShowMsg(d.success ? '✅ <?= addslashes(t('auctions.auto_bid_set')) ?>' : '❌ ' + (d.message || ''), d.success ? 'success' : 'error');
    }).catch(function(){});
}

// ---- Buy now ----
function aucBuyNow() {
  if (!confirm('<?= addslashes(t('auctions.confirm_buynow')) . ' ' . number_format($aBuyNow, 2) . '?' ?>')) return;
  fetch('/api/public/auctions/' + AUC_ID + '/buy-now', {method:'POST', credentials:'include'})
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.success) {
        aucShowMsg('🎉 <?= addslashes(t('auctions.purchased')) ?>', 'success');
        setTimeout(function(){
          if (d.data && d.data.order_id) {
            window.location.href = '/frontend/public/orders.php?view=' + d.data.order_id;
          } else {
            location.reload();
          }
        }, 1500);
      } else {
        aucShowMsg('❌ ' + (d.message || ''), 'error');
      }
    }).catch(function(){});
}

// ---- Watch toggle ----
function aucToggleWatch() {
  fetch('/api/public/auctions/' + AUC_ID + '/watch', {method:'POST', credentials:'include'})
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.success) { aucShowMsg('❌ ' + (d.message||''), 'error'); return; }
      var btn = document.getElementById('aucWatchBtn');
      if (d.data && d.data.status && d.data.status.watching) {
        btn.className = 'pub-auc-watch-btn active';
        btn.innerHTML = '❤️ <?= addslashes(t('auctions.watching')) ?>';
      } else {
        btn.className = 'pub-auc-watch-btn';
        btn.innerHTML = '🤍 <?= addslashes(t('auctions.watch')) ?>';
      }
    }).catch(function(){});
}

// ---- Tab switcher ----
function aucTab(btn, id) {
  document.querySelectorAll('.pub-auc-tab-btn').forEach(function(b){ b.classList.remove('active'); });
  document.querySelectorAll('.pub-auc-tab-pane').forEach(function(p){ p.classList.remove('active'); });
  btn.classList.add('active');
  var pane = document.getElementById(id);
  if (pane) pane.classList.add('active');
}

// ---- Show message ----
function aucShowMsg(txt, type) {
  var el = document.getElementById('aucMsg');
  if (!el) return;
  el.textContent = txt;
  el.className = 'pub-auc-msg ' + type;
  el.style.display = 'block';
  clearTimeout(el._t);
  if (type === 'success') el._t = setTimeout(function(){ el.style.display='none'; }, 4000);
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>