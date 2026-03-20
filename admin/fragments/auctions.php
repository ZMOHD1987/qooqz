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
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

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
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/auctions.css?v=<?= time() ?>">
<?php endif; ?>

<meta data-page="auctions">

<!-- Page Container -->
<div class="page-container" id="auctionsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="auctions.title"><?= __t('auctions.title', 'Auctions') ?></h1>
            <p class="page-subtitle" data-i18n="auctions.subtitle"><?= __t('auctions.subtitle', 'Manage your auction listings') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddAuction" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="auctions.add_new"><?= __t('auctions.add_new', 'Add Auction') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="auctionFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="auctionFormTitle"><?= __t('form.add_title', 'Add Auction') ?></h3>
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
                        <span data-i18n="tabs.general"><?= __t('tabs.general', 'General') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="pricing">
                        <i class="fas fa-tag"></i>
                        <span data-i18n="tabs.pricing"><?= __t('tabs.pricing', 'Pricing') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="schedule">
                        <i class="fas fa-calendar-alt"></i>
                        <span data-i18n="tabs.schedule"><?= __t('tabs.schedule', 'Schedule') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="bids">
                        <i class="fas fa-gavel"></i>
                        <span data-i18n="tabs.bids"><?= __t('tabs.bids', 'Bids') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="translations">
                        <i class="fas fa-language"></i>
                        <span data-i18n="tabs.translations"><?= __t('tabs.translations', 'Translations') ?></span>
                    </button>
                </div>

                <!-- Tab: General -->
                <div class="tab-content active" id="tab-general">
                    <div class="form-row">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="auctionTitle" class="required" data-i18n="form.fields.title.label">
                                <?= __t('form.fields.title.label', 'Auction Title') ?>
                            </label>
                            <input type="text" id="auctionTitle" name="title" class="form-control" required
                                   placeholder="<?= __t('form.fields.title.placeholder', 'Enter auction title') ?>">
                            <div class="invalid-feedback"><?= __t('form.fields.title.required', 'Title is required') ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionSlug" data-i18n="form.fields.slug.label">
                                <?= __t('form.fields.slug.label', 'Slug') ?>
                            </label>
                            <input type="text" id="auctionSlug" name="slug" class="form-control"
                                   placeholder="<?= __t('form.fields.slug.placeholder', 'auto-generated-if-empty') ?>">
                        </div>

                        <div class="form-group">
                            <label for="auctionProduct" data-i18n="form.fields.product_id.label">
                                <?= __t('form.fields.product_id.label', 'Product') ?>
                            </label>
                            <select id="auctionProduct" name="product_id" class="form-control">
                                <option value=""><?= __t('form.fields.product_id.select', 'Select product (optional)') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionEntity" class="required" data-i18n="form.fields.entity_id.label">
                                <?= __t('form.fields.entity_id.label', 'Entity') ?>
                            </label>
                            <select id="auctionEntity" name="entity_id" class="form-control" required>
                                <option value=""><?= __t('form.fields.entity_id.select', 'Select entity') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionType" data-i18n="form.fields.auction_type.label">
                                <?= __t('form.fields.auction_type.label', 'Auction Type') ?>
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
                                <?= __t('form.fields.status.label', 'Status') ?>
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
                                <?= __t('form.fields.condition_type.label', 'Condition') ?>
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
                                <?= __t('form.fields.quantity.label', 'Quantity') ?>
                            </label>
                            <input type="number" id="auctionQuantity" name="quantity" class="form-control" value="1" min="1">
                        </div>

                        <div class="form-group">
                            <label for="auctionIsFeatured" data-i18n="form.fields.is_featured.label">
                                <?= __t('form.fields.is_featured.label', 'Featured') ?>
                            </label>
                            <select id="auctionIsFeatured" name="is_featured" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionAutoBid" data-i18n="form.fields.auto_bid_enabled.label">
                                <?= __t('form.fields.auto_bid_enabled.label', 'Auto Bid') ?>
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
                                <?= __t('form.fields.notes.label', 'Notes') ?>
                            </label>
                            <textarea id="auctionNotes" name="notes" class="form-control" rows="3"
                                      placeholder="<?= __t('form.fields.notes.placeholder', 'Internal notes...') ?>"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Tab: Pricing -->
                <div class="tab-content" id="tab-pricing" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionStartingPrice" class="required" data-i18n="form.fields.starting_price.label">
                                <?= __t('form.fields.starting_price.label', 'Starting Price') ?>
                            </label>
                            <input type="number" id="auctionStartingPrice" name="starting_price" class="form-control"
                                   step="0.01" min="0" required>
                            <div class="invalid-feedback"><?= __t('form.fields.starting_price.required', 'Starting price is required') ?></div>
                        </div>

                        <div class="form-group">
                            <label for="auctionReservePrice" data-i18n="form.fields.reserve_price.label">
                                <?= __t('form.fields.reserve_price.label', 'Reserve Price') ?>
                            </label>
                            <input type="number" id="auctionReservePrice" name="reserve_price" class="form-control"
                                   step="0.01" min="0" placeholder="Optional">
                        </div>

                        <div class="form-group">
                            <label for="auctionBuyNowPrice" data-i18n="form.fields.buy_now_price.label">
                                <?= __t('form.fields.buy_now_price.label', 'Buy Now Price') ?>
                            </label>
                            <input type="number" id="auctionBuyNowPrice" name="buy_now_price" class="form-control"
                                   step="0.01" min="0" placeholder="Optional">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionBidIncrement" data-i18n="form.fields.bid_increment.label">
                                <?= __t('form.fields.bid_increment.label', 'Bid Increment') ?>
                            </label>
                            <input type="number" id="auctionBidIncrement" name="bid_increment" class="form-control"
                                   step="0.01" min="0.01" value="5.00">
                        </div>

                        <div class="form-group">
                            <label for="auctionCurrency" class="required" data-i18n="form.fields.currency_id.label">
                                <?= __t('form.fields.currency_id.label', 'Currency') ?>
                            </label>
                            <select id="auctionCurrency" name="currency_id" class="form-control" required>
                                <option value=""><?= __t('form.fields.currency_id.select', 'Select currency') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionShipping" data-i18n="form.fields.shipping_cost.label">
                                <?= __t('form.fields.shipping_cost.label', 'Shipping Cost') ?>
                            </label>
                            <input type="number" id="auctionShipping" name="shipping_cost" class="form-control"
                                   step="0.01" min="0" value="0.00">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionPaymentDeadline" data-i18n="form.fields.payment_deadline_hours.label">
                                <?= __t('form.fields.payment_deadline_hours.label', 'Payment Deadline (hours)') ?>
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
                                <?= __t('form.fields.start_date.label', 'Start Date & Time') ?>
                            </label>
                            <input type="datetime-local" id="auctionStartDate" name="start_date"
                                   class="form-control" required>
                            <div class="invalid-feedback"><?= __t('form.fields.start_date.required', 'Start date is required') ?></div>
                        </div>

                        <div class="form-group">
                            <label for="auctionEndDate" class="required" data-i18n="form.fields.end_date.label">
                                <?= __t('form.fields.end_date.label', 'End Date & Time') ?>
                            </label>
                            <input type="datetime-local" id="auctionEndDate" name="end_date"
                                   class="form-control" required>
                            <div class="invalid-feedback"><?= __t('form.fields.end_date.required', 'End date is required') ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="auctionAutoExtend" data-i18n="form.fields.auto_extend.label">
                                <?= __t('form.fields.auto_extend.label', 'Auto Extend') ?>
                            </label>
                            <select id="auctionAutoExtend" name="auto_extend" class="form-control">
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="auctionExtendMinutes" data-i18n="form.fields.extend_minutes.label">
                                <?= __t('form.fields.extend_minutes.label', 'Extend By (minutes)') ?>
                            </label>
                            <input type="number" id="auctionExtendMinutes" name="extend_minutes"
                                   class="form-control" value="5" min="1">
                        </div>

                        <div class="form-group">
                            <label for="auctionMinExtendBidTime" data-i18n="form.fields.min_extend_bid_time.label">
                                <?= __t('form.fields.min_extend_bid_time.label', 'Min. Time to Extend (minutes)') ?>
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
                            <h5><i class="fas fa-gavel"></i> <?= __t('bids.title', 'Bid History') ?></h5>
                            <button type="button" class="btn btn-sm btn-secondary" id="btnRefreshBids">
                                <i class="fas fa-sync-alt"></i> <?= __t('bids.refresh', 'Refresh') ?>
                            </button>
                        </div>

                        <div class="bids-stats" id="bidsStats">
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statTotalBids">0</div>
                                <div class="bid-stat-label"><?= __t('bids.total_bids', 'Total Bids') ?></div>
                            </div>
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statTotalBidders">0</div>
                                <div class="bid-stat-label"><?= __t('bids.total_bidders', 'Bidders') ?></div>
                            </div>
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statCurrentPrice">—</div>
                                <div class="bid-stat-label"><?= __t('bids.current_price', 'Current Price') ?></div>
                            </div>
                            <div class="bid-stat">
                                <div class="bid-stat-value" id="statWinningAmount">—</div>
                                <div class="bid-stat-label"><?= __t('bids.winning_amount', 'Winning Amount') ?></div>
                            </div>
                        </div>

                        <div id="bidsTableContainer" style="padding:0 14px 14px;">
                            <div id="bidsLoading" class="loading-state" style="display:none;">
                                <div class="spinner"></div>
                            </div>
                            <div id="bidsEmpty" class="empty-state" style="display:none;">
                                <div class="empty-icon">🔨</div>
                                <p><?= __t('bids.empty', 'No bids yet') ?></p>
                            </div>
                            <div id="bidsTableWrapper" class="table-responsive" style="display:none;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th><?= __t('bids.user', 'User') ?></th>
                                            <th><?= __t('bids.amount', 'Amount') ?></th>
                                            <th><?= __t('bids.type', 'Type') ?></th>
                                            <th><?= __t('bids.status', 'Status') ?></th>
                                            <th><?= __t('bids.time', 'Time') ?></th>
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
                        <h4 style="margin-bottom:12px; color:var(--text-primary,#fff); border-bottom:1px solid var(--border-color,#263044); padding-bottom:8px;">
                            <i class="fas fa-language"></i> <?= __t('translations.title', 'Translations') ?>
                        </h4>
                        <div id="auctionTranslations" class="translation-panels"></div>
                        <div class="form-group" style="margin-top:12px;">
                            <label for="auctionLangSelect"><?= __t('translations.select_lang', 'Add Language') ?></label>
                            <div style="display:flex; gap:8px; align-items:flex-end;">
                                <select id="auctionLangSelect" class="form-control" style="flex:1;">
                                    <option value=""><?= __t('translations.choose', 'Choose language') ?></option>
                                </select>
                                <button type="button" id="auctionAddLangBtn" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> <?= __t('translations.add', 'Add Translation') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitAuctionForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelAuctionForm">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteAuction" class="btn btn-danger" style="display:none; margin-left:auto;">
                        <i class="fas fa-trash"></i>
                        <span><?= __t('form.buttons.delete', 'Delete') ?></span>
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
                    <label for="auctionSearch" data-i18n="filters.search"><?= __t('filters.search', 'Search') ?></label>
                    <input type="text" id="auctionSearch" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search auctions...') ?>">
                </div>

                <?php if (is_super_admin()): ?>
                <div class="filter-group">
                    <label for="auctionTenantFilter" data-i18n="filters.tenant_id"><?= __t('filters.tenant_id', 'Tenant ID') ?></label>
                    <input type="number" id="auctionTenantFilter" class="form-control" value="<?= $tenantId ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="auctionStatusFilter" data-i18n="filters.status"><?= __t('filters.status', 'Status') ?></label>
                    <select id="auctionStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_status"><?= __t('filters.all_status', 'All Status') ?></option>
                        <option value="draft" data-i18n="form.fields.status.draft"><?= __t('form.fields.status.draft', 'Draft') ?></option>
                        <option value="scheduled" data-i18n="form.fields.status.scheduled"><?= __t('form.fields.status.scheduled', 'Scheduled') ?></option>
                        <option value="active" data-i18n="form.fields.status.active"><?= __t('form.fields.status.active', 'Active') ?></option>
                        <option value="paused" data-i18n="form.fields.status.paused"><?= __t('form.fields.status.paused', 'Paused') ?></option>
                        <option value="ended" data-i18n="form.fields.status.ended"><?= __t('form.fields.status.ended', 'Ended') ?></option>
                        <option value="cancelled" data-i18n="form.fields.status.cancelled"><?= __t('form.fields.status.cancelled', 'Cancelled') ?></option>
                        <option value="sold" data-i18n="form.fields.status.sold"><?= __t('form.fields.status.sold', 'Sold') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="auctionTypeFilter" data-i18n="filters.auction_type"><?= __t('filters.auction_type', 'Type') ?></label>
                    <select id="auctionTypeFilter" class="form-control">
                        <option value="" data-i18n="filters.all_types"><?= __t('filters.all_types', 'All Types') ?></option>
                        <option value="normal" data-i18n="form.fields.auction_type.normal"><?= __t('form.fields.auction_type.normal', 'Normal') ?></option>
                        <option value="reserve" data-i18n="form.fields.auction_type.reserve"><?= __t('form.fields.auction_type.reserve', 'Reserve') ?></option>
                        <option value="buy_now" data-i18n="form.fields.auction_type.buy_now"><?= __t('form.fields.auction_type.buy_now', 'Buy Now') ?></option>
                        <option value="dutch" data-i18n="form.fields.auction_type.dutch"><?= __t('form.fields.auction_type.dutch', 'Dutch') ?></option>
                        <option value="sealed_bid" data-i18n="form.fields.auction_type.sealed_bid"><?= __t('form.fields.auction_type.sealed_bid', 'Sealed Bid') ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="auctionFeaturedFilter" data-i18n="filters.is_featured"><?= __t('filters.is_featured', 'Featured') ?></label>
                    <select id="auctionFeaturedFilter" class="form-control">
                        <option value="" data-i18n="filters.all"><?= __t('filters.all', 'All') ?></option>
                        <option value="1" data-i18n="filters.featured"><?= __t('filters.featured', 'Featured') ?></option>
                        <option value="0" data-i18n="filters.not_featured"><?= __t('filters.not_featured', 'Not Featured') ?></option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyAuctionFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetAuctionFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="auctionResultsCount" style="padding:12px 16px; margin-bottom:12px; background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:8px; display:none;">
        <span style="color:var(--text-secondary,#94a3b8); font-size:0.9rem;">
            <i class="fas fa-gavel"></i>
            <span id="auctionResultsCountText"></span>
        </span>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="auctionTableLoading" class="loading-state">
                <div class="spinner"></div>
                <p><?= __t('auctions.loading', 'Loading auctions...') ?></p>
            </div>

            <div id="auctionTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="auctionsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id"><?= __t('table.headers.id', 'ID') ?></th>
                                <?php if (is_super_admin()): ?>
                                <th data-i18n="table.headers.tenant"><?= __t('table.headers.tenant', 'Tenant') ?></th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.entity"><?= __t('table.headers.entity', 'Entity') ?></th>
                                <th data-i18n="table.headers.title"><?= __t('table.headers.title', 'Title') ?></th>
                                <th data-i18n="table.headers.type"><?= __t('table.headers.type', 'Type') ?></th>
                                <th data-i18n="table.headers.status"><?= __t('table.headers.status', 'Status') ?></th>
                                <th data-i18n="table.headers.current_price"><?= __t('table.headers.current_price', 'Current Price') ?></th>
                                <th data-i18n="table.headers.bids"><?= __t('table.headers.bids', 'Bids') ?></th>
                                <th data-i18n="table.headers.end_date"><?= __t('table.headers.end_date', 'End Date') ?></th>
                                <th data-i18n="table.headers.actions"><?= __t('table.headers.actions', 'Actions') ?></th>
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
                <h3><?= __t('table.empty.title', 'No Auctions Found') ?></h3>
                <p><?= __t('table.empty.message', 'Start by adding your first auction') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Auctions)window.Auctions.add()">
                    <i class="fas fa-plus"></i> <?= __t('table.empty.add_first', 'Add First Auction') ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="auctionErrorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3><?= __t('messages.error.load_failed', 'Error Loading Data') ?></h3>
                <p id="auctionErrorMessage"></p>
                <button id="btnAuctionRetry" class="btn btn-secondary">Retry</button>
            </div>
        </div>
    </div>

</div>

<!-- Client-side config -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE     = window.APP_CONFIG.API_BASE     || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID    = window.APP_CONFIG.TENANT_ID    || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN   = window.APP_CONFIG.CSRF_TOKEN   || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID      = window.APP_CONFIG.USER_ID      || <?= admin_user_id() ?>;

window.USER_LANGUAGE = window.USER_LANGUAGE || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN    = window.CSRF_TOKEN    || '<?= addslashes($csrf) ?>';

window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate'   => $canCreate,
    'canEdit'     => $canEdit,
    'canDelete'   => $canDelete,
    'canViewAll'  => $canViewAll,
    'canViewOwn'  => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll'  => $canEditAll,
    'canEditOwn'  => $canEditOwn,
    'canDeleteAll'=> $canDeleteAll,
    'canDeleteOwn'=> $canDeleteOwn,
    'isSuperAdmin'=> is_super_admin(),
], JSON_UNESCAPED_UNICODE) ?>;

window.AUCTIONS_CONFIG = {
    apiUrl:       '<?= $apiBase ?>/auctions',
    bidsApi:      '<?= $apiBase ?>/auction_bids',
    translationsApi: '<?= $apiBase ?>/auction_translations',
    productsApi:  '<?= $apiBase ?>/products',
    currenciesApi:'<?= $apiBase ?>/currencies',
    languagesApi: '<?= $apiBase ?>/languages',
    csrfToken:    '<?= addslashes($csrf) ?>',
    lang:         '<?= addslashes($lang) ?>',
    itemsPerPage: 25
};
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/auctions.js?v=<?= time() ?>"></script>
<script>
(function(){
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function(){
        attempts++;
        if (window.Auctions && typeof window.Auctions.init === 'function') {
            clearInterval(interval);
            var p = window.Auctions.init();
            if (p && typeof p.then === 'function') {
                p.then(function(){ console.log('[Auctions] ✓ Initialized'); })
                 .catch(function(e){ console.error('[Auctions] Init failed:', e); });
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Auctions] Timeout waiting for module');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/auctions.js?v=<?= time() ?>"></script>
<script>
(function(){
    function tryInit() {
        if (window.Auctions && typeof window.Auctions.init === 'function') {
            window.Auctions.init().catch(function(e){ console.error('[Auctions] Init failed', e); });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php endif; ?>

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>