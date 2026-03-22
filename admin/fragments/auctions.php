<?php
declare(strict_types=1);

/**
 * /admin/fragments/auctions.php
 * Auctions Management - Production Version
 *
 * ✅ Role-based + resource-based permission system
 * ✅ Full multi-language translation support (auction_translations)
 * ✅ Bids viewer (auction_bids)
 * ✅ Schedule management (start/end date, auto-extend)
 * ✅ Pricing (starting, reserve, buy-now, bid increment)
 * ✅ Production-ready with all APIs integrated
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment  = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// Resource-based permissions
$canViewAll    = can_view_all('auctions');
$canViewOwn    = can_view_own('auctions');
$canViewTenant = can_view_tenant('auctions');
$canCreate     = can_create('auctions');
$canEditAll    = can_edit_all('auctions');
$canEditOwn    = can_edit_own('auctions');
$canDeleteAll  = can_delete_all('auctions');
$canDeleteOwn  = can_delete_own('auctions');

// Fallback to role-based
$canManage = can('auctions.manage') || can('auctions.create');
$canView   = $canViewAll || $canViewOwn || $canViewTenant || $canManage;
$canEdit   = $canEditAll  || $canEditOwn  || $canManage;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManage;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied: You do not have permission to view auctions');
}

// ════════════════════════════════════════════════════════════
// TRANSLATIONS (server-side — injected via AUCTIONS_CONFIG.strings)
// ════════════════════════════════════════════════════════════
$_aucStrings     = [];
$_aucAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
    'vi','id','ms','bn','sw','tl',
];
$_aucSafeLang = in_array($lang, $_aucAllowedLangs, true) ? $lang : 'en';
$_aucLangFile = __DIR__ . '/../../languages/Auctions/' . $_aucSafeLang . '.json';

if (file_exists($_aucLangFile)) {
    $_aucJson = json_decode(file_get_contents($_aucLangFile), true);
    if (is_array($_aucJson)) {
        $_aucStrings = isset($_aucJson['strings']) ? $_aucJson['strings'] : $_aucJson;
    }
}

/**
 * Translate dot-notation key — PHP fallback only.
 * Runtime translations handled by data-i18n attributes via admin_core.js.
 */
function _auct(string $key, string $fallback = ''): string
{
    global $_aucStrings;
    $parts = explode('.', $key);
    $val   = $_aucStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback !== '' ? $fallback : $key;
        }
    }
    return is_string($val) ? $val : ($fallback !== '' ? $fallback : $key);
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

// assetVer() مُعرَّفة في header.php — نُعرِّفها هنا فقط عند fragment مستقل
if (!function_exists('assetVer')) {
    function assetVer(string $path): string
    {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full         = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string) filemtime($full) : '0';
        }
        return $cache[$path];
    }
}
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/auctions.css?v=<?= assetVer('/admin/assets/css/pages/auctions.css') ?>">

<meta data-page="auctions"
      data-assets-css="/admin/assets/css/pages/auctions.css"
      data-assets-js="/admin/assets/js/pages/auctions.js"
      data-i18n-files="/languages/Auctions/<?= rawurlencode($_aucSafeLang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="auctionsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="auctions.title"><?= _auct('auctions.title', 'Auctions') ?></h1>
            <p class="page-subtitle" data-i18n="auctions.subtitle"><?= _auct('auctions.subtitle', 'Manage your auction listings') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddAuction" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="auctions.add_new"><?= _auct('auctions.add_new', 'Add Auction') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="auctionFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="auctionFormTitle"><?= _auct('form.add_title', 'Add Auction') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseAuctionForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="auctionForm" novalidate>
                <input type="hidden" id="auctionFormId"       name="id">
                <input type="hidden" name="csrf_token"         value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="auctionTenantId"     name="tenant_id" value="<?= $tenantId ?>">

                <!-- Tabs Navigation -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="general">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.general"><?= _auct('tabs.general', 'General') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="pricing">
                        <i class="fas fa-tag"></i>
                        <span data-i18n="tabs.pricing"><?= _auct('tabs.pricing', 'Pricing') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="schedule">
                        <i class="fas fa-calendar-alt"></i>
                        <span data-i18n="tabs.schedule"><?= _auct('tabs.schedule', 'Schedule') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="bids">
                        <i class="fas fa-gavel"></i>
                        <span data-i18n="tabs.bids"><?= _auct('tabs.bids', 'Bids') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="translations">
                        <i class="fas fa-language"></i>
                        <span data-i18n="tabs.translations"><?= _auct('tabs.translations', 'Translations') ?></span>
                    </button>
                </div>

                <!-- Tab: General -->
                <div class="tab-content active" id="tab-general">
                    <div class="form-row">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="auctionTitle" class="required" data-i18n="form.fields.title.label">
                                <?= _auct('form.fields.title.label', 'Auction Title') ?>
                            </label>
                            <input type="text" id="auctionTitle" name="title" class="form-control" required
                                   placeholder="<?= _auct('form.fields.title.placeholder', 'Enter auction title') ?>">
                            <div class="invalid-feedback"><?= _auct('form.fields.title.required', 'Title is required') ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionSlug" data-i18n="form.fields.slug.label">
                                <?= _auct('form.fields.slug.label', 'Slug') ?>
                            </label>
                            <input type="text" id="auctionSlug" name="slug" class="form-control"
                                   placeholder="<?= _auct('form.fields.slug.placeholder', 'auto-generated-if-empty') ?>">
                        </div>

                        <div class="form-group">
                            <label for="auctionProduct" data-i18n="form.fields.product_id.label">
                                <?= _auct('form.fields.product_id.label', 'Product') ?>
                            </label>
                            <select id="auctionProduct" name="product_id" class="form-control">
                                <option value=""><?= _auct('form.fields.product_id.select', 'Select product (optional)') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionEntity" class="required" data-i18n="form.fields.entity_id.label">
                                <?= _auct('form.fields.entity_id.label', 'Entity') ?>
                            </label>
                            <select id="auctionEntity" name="entity_id" class="form-control" required>
                                <option value=""><?= _auct('form.fields.entity_id.select', 'Select entity') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionType" data-i18n="form.fields.auction_type.label">
                                <?= _auct('form.fields.auction_type.label', 'Auction Type') ?>
                            </label>
                            <select id="auctionType" name="auction_type" class="form-control">
                                <option value="normal"    data-i18n="form.fields.auction_type.normal">Normal</option>
                                <option value="reserve"   data-i18n="form.fields.auction_type.reserve">Reserve</option>
                                <option value="buy_now"   data-i18n="form.fields.auction_type.buy_now">Buy Now</option>
                                <option value="dutch"     data-i18n="form.fields.auction_type.dutch">Dutch</option>
                                <option value="sealed_bid" data-i18n="form.fields.auction_type.sealed_bid">Sealed Bid</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionStatus" data-i18n="form.fields.status.label">
                                <?= _auct('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="auctionStatus" name="status" class="form-control">
                                <option value="draft"     data-i18n="form.fields.status.draft">Draft</option>
                                <option value="scheduled" data-i18n="form.fields.status.scheduled">Scheduled</option>
                                <option value="active"    data-i18n="form.fields.status.active">Active</option>
                                <option value="paused"    data-i18n="form.fields.status.paused">Paused</option>
                                <option value="ended"     data-i18n="form.fields.status.ended">Ended</option>
                                <option value="cancelled" data-i18n="form.fields.status.cancelled">Cancelled</option>
                                <option value="sold"      data-i18n="form.fields.status.sold">Sold</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionCondition" data-i18n="form.fields.condition_type.label">
                                <?= _auct('form.fields.condition_type.label', 'Condition') ?>
                            </label>
                            <select id="auctionCondition" name="condition_type" class="form-control">
                                <option value="new"        data-i18n="form.fields.condition_type.new">New</option>
                                <option value="like_new"   data-i18n="form.fields.condition_type.like_new">Like New</option>
                                <option value="very_good"  data-i18n="form.fields.condition_type.very_good">Very Good</option>
                                <option value="good"       data-i18n="form.fields.condition_type.good">Good</option>
                                <option value="acceptable" data-i18n="form.fields.condition_type.acceptable">Acceptable</option>
                                <option value="for_parts"  data-i18n="form.fields.condition_type.for_parts">For Parts</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionQuantity" data-i18n="form.fields.quantity.label">
                                <?= _auct('form.fields.quantity.label', 'Quantity') ?>
                            </label>
                            <input type="number" id="auctionQuantity" name="quantity" class="form-control" value="1" min="1">
                        </div>

                        <div class="form-group">
                            <label for="auctionIsFeatured" data-i18n="form.fields.is_featured.label">
                                <?= _auct('form.fields.is_featured.label', 'Featured') ?>
                            </label>
                            <select id="auctionIsFeatured" name="is_featured" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionAutoBid" data-i18n="form.fields.auto_bid_enabled.label">
                                <?= _auct('form.fields.auto_bid_enabled.label', 'Auto Bid') ?>
                            </label>
                            <select id="auctionAutoBid" name="auto_bid_enabled" class="form-control">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="auctionNotes" data-i18n="form.fields.notes.label">
                                <?= _auct('form.fields.notes.label', 'Notes') ?>
                            </label>
                            <textarea id="auctionNotes" name="notes" class="form-control" rows="3"
                                      placeholder="<?= _auct('form.fields.notes.placeholder', 'Internal notes...') ?>"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Tab: Pricing -->
                <div class="tab-content" id="tab-pricing" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionStartingPrice" class="required" data-i18n="form.fields.starting_price.label">
                                <?= _auct('form.fields.starting_price.label', 'Starting Price') ?>
                            </label>
                            <input type="number" id="auctionStartingPrice" name="starting_price" class="form-control"
                                   step="0.01" min="0" required>
                            <div class="invalid-feedback"><?= _auct('form.fields.starting_price.required', 'Starting price is required') ?></div>
                        </div>

                        <div class="form-group">
                            <label for="auctionReservePrice" data-i18n="form.fields.reserve_price.label">
                                <?= _auct('form.fields.reserve_price.label', 'Reserve Price') ?>
                            </label>
                            <input type="number" id="auctionReservePrice" name="reserve_price" class="form-control"
                                   step="0.01" min="0" placeholder="Optional">
                        </div>

                        <div class="form-group">
                            <label for="auctionBuyNowPrice" data-i18n="form.fields.buy_now_price.label">
                                <?= _auct('form.fields.buy_now_price.label', 'Buy Now Price') ?>
                            </label>
                            <input type="number" id="auctionBuyNowPrice" name="buy_now_price" class="form-control"
                                   step="0.01" min="0" placeholder="Optional">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionBidIncrement" data-i18n="form.fields.bid_increment.label">
                                <?= _auct('form.fields.bid_increment.label', 'Bid Increment') ?>
                            </label>
                            <input type="number" id="auctionBidIncrement" name="bid_increment" class="form-control"
                                   step="0.01" min="0.01" value="5.00">
                        </div>

                        <div class="form-group">
                            <label for="auctionCurrency" class="required" data-i18n="form.fields.currency_id.label">
                                <?= _auct('form.fields.currency_id.label', 'Currency') ?>
                            </label>
                            <select id="auctionCurrency" name="currency_id" class="form-control" required>
                                <option value=""><?= _auct('form.fields.currency_id.select', 'Select currency') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionShipping" data-i18n="form.fields.shipping_cost.label">
                                <?= _auct('form.fields.shipping_cost.label', 'Shipping Cost') ?>
                            </label>
                            <input type="number" id="auctionShipping" name="shipping_cost" class="form-control"
                                   step="0.01" min="0" value="0.00">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionPaymentDeadline" data-i18n="form.fields.payment_deadline_hours.label">
                                <?= _auct('form.fields.payment_deadline_hours.label', 'Payment Deadline (hours)') ?>
                            </label>
                            <input type="number" id="auctionPaymentDeadline" name="payment_deadline_hours"
                                   class="form-control" value="48" min="1">
                        </div>
                    </div>
                </div>

                <!-- Tab: Schedule -->
                <div class="tab-content" id="tab-schedule" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionStartDate" class="required" data-i18n="form.fields.start_date.label">
                                <?= _auct('form.fields.start_date.label', 'Start Date & Time') ?>
                            </label>
                            <input type="datetime-local" id="auctionStartDate" name="start_date"
                                   class="form-control" required>
                            <div class="invalid-feedback"><?= _auct('form.fields.start_date.required', 'Start date is required') ?></div>
                        </div>

                        <div class="form-group">
                            <label for="auctionEndDate" class="required" data-i18n="form.fields.end_date.label">
                                <?= _auct('form.fields.end_date.label', 'End Date & Time') ?>
                            </label>
                            <input type="datetime-local" id="auctionEndDate" name="end_date"
                                   class="form-control" required>
                            <div class="invalid-feedback"><?= _auct('form.fields.end_date.required', 'End date is required') ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionAutoExtend" data-i18n="form.fields.auto_extend.label">
                                <?= _auct('form.fields.auto_extend.label', 'Auto Extend') ?>
                            </label>
                            <select id="auctionAutoExtend" name="auto_extend" class="form-control">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionExtendMinutes" data-i18n="form.fields.extend_minutes.label">
                                <?= _auct('form.fields.extend_minutes.label', 'Extend By (minutes)') ?>
                            </label>
                            <input type="number" id="auctionExtendMinutes" name="extend_minutes"
                                   class="form-control" value="5" min="1">
                        </div>

                        <div class="form-group">
                            <label for="auctionMinExtendBidTime" data-i18n="form.fields.min_extend_bid_time.label">
                                <?= _auct('form.fields.min_extend_bid_time.label', 'Min. Time to Extend (minutes)') ?>
                            </label>
                            <input type="number" id="auctionMinExtendBidTime" name="min_extend_bid_time"
                                   class="form-control" value="5" min="1">
                        </div>
                    </div>
                </div>

                <!-- Tab: Bids (view-only inside form, loaded on edit) -->
                <div class="tab-content" id="tab-bids" style="display:none">
                    <div class="bids-panel">
                        <div class="bids-panel-header">
                            <h5><i class="fas fa-gavel"></i> <?= _auct('bids.title', 'Bid History') ?></h5>
                            <button type="button" class="btn btn-sm btn-secondary" id="btnRefreshBids">
                                <i class="fas fa-sync-alt"></i> <?= _auct('bids.refresh', 'Refresh') ?>
                            </button>
                        </div>

                        <div class="bids-stats" id="bidsStats">
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statTotalBids">0</div>
                                <div class="bid-stat-label"><?= _auct('bids.total_bids', 'Total Bids') ?></div>
                            </div>
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statTotalBidders">0</div>
                                <div class="bid-stat-label"><?= _auct('bids.total_bidders', 'Bidders') ?></div>
                            </div>
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statCurrentPrice">—</div>
                                <div class="bid-stat-label"><?= _auct('bids.current_price', 'Current Price') ?></div>
                            </div>
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statWinningAmount">—</div>
                                <div class="bid-stat-label"><?= _auct('bids.winning_amount', 'Winning Amount') ?></div>
                            </div>
                        </div>

                        <div id="bidsTableContainer" class="bids-table-body">
                            <div id="bidsLoading" class="loading-state" style="display:none;">
                                <div class="spinner"></div>
                            </div>
                            <div id="bidsEmpty" class="empty-state" style="display:none;">
                                <div class="empty-icon">🔨</div>
                                <p><?= _auct('bids.empty', 'No bids yet') ?></p>
                            </div>
                            <div id="bidsTableWrapper" class="table-responsive" style="display:none;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?= _auct('bids.user', 'User') ?></th>
                                            <th><?= _auct('bids.amount', 'Amount') ?></th>
                                            <th><?= _auct('bids.type', 'Type') ?></th>
                                            <th><?= _auct('bids.status', 'Status') ?></th>
                                            <th><?= _auct('bids.time', 'Time') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="bidsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Translations -->
                <div class="tab-content" id="tab-translations" style="display:none">
                    <div class="translations-section">
                        <h4 class="section-heading">
                            <i class="fas fa-language"></i> <?= _auct('translations.title', 'Translations') ?>
                        </h4>
                        <div id="auctionTranslations" class="translation-panels"></div>
                        <div class="form-group lang-select-group">
                            <label for="auctionLangSelect"><?= _auct('translations.select_lang', 'Add Language') ?></label>
                            <div class="lang-add-row">
                                <select id="auctionLangSelect" class="form-control lang-select">
                                    <option value=""><?= _auct('translations.choose', 'Choose language') ?></option>
                                </select>
                                <button type="button" id="auctionAddLangBtn" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> <?= _auct('translations.add', 'Add Translation') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitAuctionForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= _auct('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelAuctionForm">
                        <?= _auct('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteAuction" class="btn btn-danger btn-delete-end" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span><?= _auct('form.buttons.delete', 'Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="auctionSearch" data-i18n="filters.search"><?= _auct('filters.search', 'Search') ?></label>
                    <input type="text" id="auctionSearch" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= _auct('filters.search_placeholder', 'Search auctions...') ?>">
                </div>

                <?php if (is_super_admin()): ?>
                <div class="filter-group">
                    <label for="auctionTenantFilter" data-i18n="filters.tenant_id"><?= _auct('filters.tenant_id', 'Tenant ID') ?></label>
                    <input type="number" id="auctionTenantFilter" class="form-control" value="<?= $tenantId ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="auctionStatusFilter" data-i18n="filters.status"><?= _auct('filters.status', 'Status') ?></label>
                    <select id="auctionStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_status"><?= _auct('filters.all_status', 'All Status') ?></option>
                        <option value="draft" data-i18n="form.fields.status.draft"><?= _auct('form.fields.status.draft', 'Draft') ?></option>
                        <option value="scheduled" data-i18n="form.fields.status.scheduled"><?= _auct('form.fields.status.scheduled', 'Scheduled') ?></option>
                        <option value="active" data-i18n="form.fields.status.active"><?= _auct('form.fields.status.active', 'Active') ?></option>
                        <option value="paused" data-i18n="form.fields.status.paused"><?= _auct('form.fields.status.paused', 'Paused') ?></option>
                        <option value="ended" data-i18n="form.fields.status.ended"><?= _auct('form.fields.status.ended', 'Ended') ?></option>
                        <option value="cancelled" data-i18n="form.fields.status.cancelled"><?= _auct('form.fields.status.cancelled', 'Cancelled') ?></option>
                        <option value="sold" data-i18n="form.fields.status.sold"><?= _auct('form.fields.status.sold', 'Sold') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="auctionTypeFilter" data-i18n="filters.auction_type"><?= _auct('filters.auction_type', 'Type') ?></label>
                    <select id="auctionTypeFilter" class="form-control">
                        <option value="" data-i18n="filters.all_types"><?= _auct('filters.all_types', 'All Types') ?></option>
                        <option value="normal" data-i18n="form.fields.auction_type.normal"><?= _auct('form.fields.auction_type.normal', 'Normal') ?></option>
                        <option value="reserve" data-i18n="form.fields.auction_type.reserve"><?= _auct('form.fields.auction_type.reserve', 'Reserve') ?></option>
                        <option value="buy_now" data-i18n="form.fields.auction_type.buy_now"><?= _auct('form.fields.auction_type.buy_now', 'Buy Now') ?></option>
                        <option value="dutch" data-i18n="form.fields.auction_type.dutch"><?= _auct('form.fields.auction_type.dutch', 'Dutch') ?></option>
                        <option value="sealed_bid" data-i18n="form.fields.auction_type.sealed_bid"><?= _auct('form.fields.auction_type.sealed_bid', 'Sealed Bid') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="auctionFeaturedFilter" data-i18n="filters.is_featured"><?= _auct('filters.is_featured', 'Featured') ?></label>
                    <select id="auctionFeaturedFilter" class="form-control">
                        <option value="" data-i18n="filters.all"><?= _auct('filters.all', 'All') ?></option>
                        <option value="1" data-i18n="filters.featured"><?= _auct('filters.featured', 'Featured') ?></option>
                        <option value="0" data-i18n="filters.not_featured"><?= _auct('filters.not_featured', 'Not Featured') ?></option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyAuctionFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= _auct('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetAuctionFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= _auct('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="auctionResultsCount" class="results-count-bar" style="display:none">
        <span class="results-count-text">
            <i class="fas fa-gavel"></i>
            <span id="auctionResultsCountText"></span>
        </span>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="auctionTableLoading" class="loading-state">
                <div class="spinner"></div>
                <p><?= _auct('auctions.loading', 'Loading auctions...') ?></p>
            </div>

            <div id="auctionTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="auctionsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id"><?= _auct('table.headers.id', 'ID') ?></th>
                                <?php if (is_super_admin()): ?>
                                <th data-i18n="table.headers.tenant"><?= _auct('table.headers.tenant', 'Tenant') ?></th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.entity"><?= _auct('table.headers.entity', 'Entity') ?></th>
                                <th data-i18n="table.headers.title"><?= _auct('table.headers.title', 'Title') ?></th>
                                <th data-i18n="table.headers.type"><?= _auct('table.headers.type', 'Type') ?></th>
                                <th data-i18n="table.headers.status"><?= _auct('table.headers.status', 'Status') ?></th>
                                <th data-i18n="table.headers.current_price"><?= _auct('table.headers.current_price', 'Current Price') ?></th>
                                <th data-i18n="table.headers.bids"><?= _auct('table.headers.bids', 'Bids') ?></th>
                                <th data-i18n="table.headers.end_date"><?= _auct('table.headers.end_date', 'End Date') ?></th>
                                <th data-i18n="table.headers.actions"><?= _auct('table.headers.actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="auctionTableBody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <span id="auctionPaginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="auctionPagination"></div>
                </div>
            </div>

            <div id="auctionEmptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🔨</div>
                <h3><?= _auct('table.empty.title', 'No Auctions Found') ?></h3>
                <p><?= _auct('table.empty.message', 'Start by adding your first auction') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Auctions)window.Auctions.add()">
                    <i class="fas fa-plus"></i> <?= _auct('table.empty.add_first', 'Add First Auction') ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="auctionErrorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3><?= _auct('messages.error.load_failed', 'Error Loading Data') ?></h3>
                <p id="auctionErrorMessage"></p>
                <button id="btnAuctionRetry" class="btn btn-secondary">Retry</button>
            </div>
        </div>
    </div>

</div>

<script>
window.AUCTIONS_CONFIG = {
    apiBase:    <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES) ?>,
    lang:       <?= json_encode($_aucSafeLang) ?>,
    dir:        <?= json_encode($dir) ?>,
    tenantId:   <?= (int) $tenantId ?>,
    csrfToken:  <?= json_encode($csrf) ?>,
    userId:     <?= (int) $userId ?>,
    strings:    <?= json_encode($_aucStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:  <?= json_encode($canCreate) ?>,
    canEdit:    <?= json_encode($canEdit) ?>,
    canDelete:  <?= json_encode($canDelete) ?>,
    isSuperAdmin: <?= json_encode(is_super_admin()) ?>,
    urls: {
        auctions:     '<?= $apiBase ?>/auctions',
        bids:         '<?= $apiBase ?>/auction_bids',
        translations: '<?= $apiBase ?>/auction_translations',
        products:     '<?= $apiBase ?>/products',
        currencies:   '<?= $apiBase ?>/currencies',
        languages:    '<?= $apiBase ?>/languages',
        entities:     '<?= $apiBase ?>/entities'
    }
};
</script>
<script src="/admin/assets/js/pages/auctions.js?v=<?= assetVer('/admin/assets/js/pages/auctions.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>