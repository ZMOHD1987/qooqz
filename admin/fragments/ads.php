<?php
declare(strict_types=1);

/**
 * /admin/fragments/ads.php
 * Ads Management - Campaigns + Ad Units
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax      = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded  = isset($_GET['embedded']) || isset($_POST['embedded']);
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
// AUTH
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// USER / TENANT CONTEXT
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// PERMISSIONS
// ════════════════════════════════════════════════════════════
$canManageAds = can('manage_ads') || is_super_admin();
$canCreate    = $canManageAds;
$canEdit      = $canManageAds;
$canDelete    = $canManageAds;

if (!$canManageAds) {
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// TRANSLATIONS
// ════════════════════════════════════════════════════════════
$_adsStrings    = [];
$_allowedLangs  = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es', 'fa', 'he', 'hi', 'zh', 'ja', 'ko', 'pt', 'ru', 'it', 'nl', 'sv', 'pl', 'th', 'vi', 'id', 'ms'];
$_safeLang      = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile      = __DIR__ . '/../../languages/Ads/' . $_safeLang . '.json';
if (!file_exists($_langFile)) {
    $_langFile = __DIR__ . '/../../languages/Ads/en.json';
}
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_adsStrings = $_json['strings'];
    }
}

if (!function_exists('_adst')) {
    function _adst(string $key, string $fallback = ''): string {
        global $_adsStrings;
        $keys = explode('.', $key);
        $val  = $_adsStrings;
        foreach ($keys as $k) {
            if (is_array($val) && isset($val[$k])) {
                $val = $val[$k];
            } else {
                return $fallback ?: $key;
            }
        }
        return is_string($val) ? $val : ($fallback ?: $key);
    }
}

$apiBase = '/api';
?>

<link rel="stylesheet" href="/admin/assets/css/pages/ads.css?v=<?= time() ?>">
<meta data-page="ads"
      data-i18n-files="/languages/Ads/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="adsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 data-i18n="title"><?= htmlspecialchars(_adst('title', 'Ads Management'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p data-i18n="subtitle"><?= htmlspecialchars(_adst('subtitle', 'Manage ad campaigns and advertising units'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="page-header-actions" id="adsHeaderActions">
            <?php if ($canCreate): ?>
                <button id="btnAddCampaign" class="btn btn-primary" data-i18n="add_campaign" style="display:none;">
                    <?= htmlspecialchars(_adst('add_campaign', 'Add Campaign'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button id="btnAddAd" class="btn btn-primary" data-i18n="add_ad" style="display:none;">
                    <?= htmlspecialchars(_adst('add_ad', 'Add Ad Unit'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button id="btnAddPlacement" class="btn btn-primary" data-i18n="add_placement" style="display:none;">
                    <?= htmlspecialchars(_adst('add_placement', 'Add Placement'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="ads-tabs">
        <button class="ads-tab-btn active" data-tab="campaigns" data-i18n="tab_campaigns">
            <?= htmlspecialchars(_adst('tab_campaigns', 'Campaigns'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button class="ads-tab-btn" data-tab="ads" data-i18n="tab_ads">
            <?= htmlspecialchars(_adst('tab_ads', 'Ad Units'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button class="ads-tab-btn" data-tab="placements" data-i18n="tab_placements">
            <?= htmlspecialchars(_adst('tab_placements', 'Placements'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <!-- ══════════════════════════════════════
         CAMPAIGNS TAB
    ══════════════════════════════════════ -->
    <div id="tabCampaigns" class="ads-tab-panel">

        <!-- Filter Bar -->
        <div class="card">
            <div class="card-body filter-bar">
                <input type="text" id="filterCampaignSearch" class="form-control"
                       placeholder="<?= htmlspecialchars(_adst('filter.search_campaigns_placeholder', 'Search campaigns...'), ENT_QUOTES, 'UTF-8') ?>"
                       data-i18n-placeholder="filter.search_campaigns_placeholder">

                <select id="filterCampaignStatus" class="form-control">
                    <option value=""><?= htmlspecialchars(_adst('filter.all_statuses', 'All Statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="draft"><?= htmlspecialchars(_adst('status.draft', 'Draft'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="paused"><?= htmlspecialchars(_adst('status.paused', 'Paused'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="completed"><?= htmlspecialchars(_adst('status.completed', 'Completed'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>

                <select id="filterCampaignPricingModel" class="form-control">
                    <option value=""><?= htmlspecialchars(_adst('filter.all_pricing_models', 'All Pricing Models'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="fixed"><?= htmlspecialchars(_adst('pricing_model.fixed', 'Fixed'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="cpm"><?= htmlspecialchars(_adst('pricing_model.cpm', 'CPM'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="cpc"><?= htmlspecialchars(_adst('pricing_model.cpc', 'CPC'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>

                <button id="btnCampaignFilter" class="btn btn-primary" data-i18n="filter.apply">
                    <?= htmlspecialchars(_adst('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button id="btnClearCampaignFilters" class="btn btn-secondary" data-i18n="filter.clear">
                    <?= htmlspecialchars(_adst('filter.clear', 'Clear Filters'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>

        <!-- Campaigns Table -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <table class="data-table" id="campaignsTable">
                    <thead>
                        <tr>
                            <th data-i18n="campaigns_table.id"><?= htmlspecialchars(_adst('campaigns_table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.name"><?= htmlspecialchars(_adst('campaigns_table.name', 'Name'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.budget"><?= htmlspecialchars(_adst('campaigns_table.budget', 'Budget'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.currency"><?= htmlspecialchars(_adst('campaigns_table.currency', 'Currency'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.pricing_model"><?= htmlspecialchars(_adst('campaigns_table.pricing_model', 'Pricing Model'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.start_date"><?= htmlspecialchars(_adst('campaigns_table.start_date', 'Start Date'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.end_date"><?= htmlspecialchars(_adst('campaigns_table.end_date', 'End Date'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.status"><?= htmlspecialchars(_adst('campaigns_table.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="campaigns_table.actions"><?= htmlspecialchars(_adst('campaigns_table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody id="campaignsTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <?= htmlspecialchars(_adst('campaigns_table.no_records', 'No campaigns found'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Campaigns Pagination -->
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    <span data-i18n="pagination.showing"><?= htmlspecialchars(_adst('pagination.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="campaignsPaginationInfo">0-0 <?= htmlspecialchars(_adst('pagination.of', 'of'), ENT_QUOTES, 'UTF-8') ?> 0</span>
                </div>
                <div class="pagination" id="campaignsPagination"></div>
            </div>
        </div>

    </div><!-- /tabCampaigns -->

    <!-- ══════════════════════════════════════
         ADS TAB
    ══════════════════════════════════════ -->
    <div id="tabAds" class="ads-tab-panel" style="display:none;">

        <!-- Filter Bar -->
        <div class="card">
            <div class="card-body filter-bar">
                <input type="text" id="filterSearch" class="form-control"
                       placeholder="<?= htmlspecialchars(_adst('filter.search_placeholder', 'Search ads...'), ENT_QUOTES, 'UTF-8') ?>"
                       data-i18n-placeholder="filter.search_placeholder">

                <select id="filterStatus" class="form-control">
                    <option value=""><?= htmlspecialchars(_adst('filter.all_statuses', 'All Statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="paused"><?= htmlspecialchars(_adst('status.paused', 'Paused'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="rejected"><?= htmlspecialchars(_adst('status.rejected', 'Rejected'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>

                <select id="filterTargetType" class="form-control">
                    <option value=""><?= htmlspecialchars(_adst('filter.all_target_types', 'All Target Types'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="url"><?= htmlspecialchars(_adst('target_type.url', 'URL'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="entity"><?= htmlspecialchars(_adst('target_type.entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>

                <select id="filterCampaign" class="form-control">
                    <option value=""><?= htmlspecialchars(_adst('filter.all_campaigns', 'All Campaigns'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>

                <button id="btnFilter" class="btn btn-primary" data-i18n="filter.apply">
                    <?= htmlspecialchars(_adst('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button id="btnClearFilters" class="btn btn-secondary" data-i18n="filter.clear">
                    <?= htmlspecialchars(_adst('filter.clear', 'Clear Filters'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>

        <!-- Ads Data Table -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <table class="data-table" id="adsTable">
                    <thead>
                        <tr>
                            <th data-i18n="table.image"><?= htmlspecialchars(_adst('table.image', 'Image'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.id"><?= htmlspecialchars(_adst('table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.campaign"><?= htmlspecialchars(_adst('table.campaign', 'Campaign'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.target_type"><?= htmlspecialchars(_adst('table.target_type', 'Target Type'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.target_value"><?= htmlspecialchars(_adst('table.target_value', 'Target Value'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.status"><?= htmlspecialchars(_adst('table.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.views"><?= htmlspecialchars(_adst('table.views', 'Views'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.clicks"><?= htmlspecialchars(_adst('table.clicks', 'Clicks'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.ctr"><?= htmlspecialchars(_adst('table.ctr', 'CTR'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.created_at"><?= htmlspecialchars(_adst('table.created_at', 'Created At'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="table.actions"><?= htmlspecialchars(_adst('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody id="adsTableBody">
                        <tr>
                            <td colspan="11" class="text-center">
                                <?= htmlspecialchars(_adst('table.no_records', 'No ads found'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Ads Pagination -->
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    <span data-i18n="pagination.showing"><?= htmlspecialchars(_adst('pagination.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="adsPaginationInfo">0-0 <?= htmlspecialchars(_adst('pagination.of', 'of'), ENT_QUOTES, 'UTF-8') ?> 0</span>
                </div>
                <div class="pagination" id="adsPagination"></div>
            </div>
        </div>

    </div><!-- /tabAds -->

    <!-- ══════════════════════════════════════
         PLACEMENTS TAB
    ══════════════════════════════════════ -->
    <div id="tabPlacements" class="ads-tab-panel" style="display:none;">

        <!-- Filter Bar -->
        <div class="card">
            <div class="card-body filter-bar">
                <input type="text" id="filterPlacementsSearch" class="form-control"
                       placeholder="<?= htmlspecialchars(_adst('filter.search_placeholder', 'Search placements...'), ENT_QUOTES, 'UTF-8') ?>"
                       data-i18n-placeholder="filter.search_placeholder">

                <select id="filterPlacementStatus" class="form-control">
                    <option value=""><?= htmlspecialchars(_adst('filter.all_statuses', 'All Statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="inactive"><?= htmlspecialchars(_adst('status.inactive', 'Inactive'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>

                <button id="btnPlacementFilter" class="btn btn-primary" data-i18n="filter.apply">
                    <?= htmlspecialchars(_adst('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button id="btnClearPlacementFilters" class="btn btn-secondary" data-i18n="filter.clear">
                    <?= htmlspecialchars(_adst('filter.clear', 'Clear Filters'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>

        <!-- Placements Table -->
        <div class="card">
            <div class="card-body" style="padding:0;">
                <table class="data-table" id="placementsTable">
                    <thead>
                        <tr>
                            <th data-i18n="placements_table.id"><?= htmlspecialchars(_adst('placements_table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="placements_table.name"><?= htmlspecialchars(_adst('placements_table.name', 'Name'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="placements_table.placement_key"><?= htmlspecialchars(_adst('placements_table.placement_key', 'Key'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="placements_table.status"><?= htmlspecialchars(_adst('placements_table.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="placements_table.created_at"><?= htmlspecialchars(_adst('placements_table.created_at', 'Created At'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th data-i18n="placements_table.actions"><?= htmlspecialchars(_adst('placements_table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody id="placementsTableBody">
                        <tr>
                            <td colspan="6" class="text-center">
                                <?= htmlspecialchars(_adst('placements_table.no_records', 'No placements found'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Placements Pagination -->
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    <span data-i18n="pagination.showing"><?= htmlspecialchars(_adst('pagination.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="placementsPaginationInfo">0-0 <?= htmlspecialchars(_adst('pagination.of', 'of'), ENT_QUOTES, 'UTF-8') ?> 0</span>
                </div>
                <div class="pagination" id="placementsPagination"></div>
            </div>
        </div>

        <!-- Placement Items Sub-section -->
        <div id="placementItemsSection" style="display:none;">
            <div class="card">
                <div class="card-body">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
                        <h4 id="placementItemsTitle" data-i18n="placement_items_title">
                            <?= htmlspecialchars(_adst('placement_items_title', 'Placement Items'), ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <?php if ($canCreate): ?>
                        <button id="btnAddPlacementItemInline" class="btn btn-primary btn-sm" data-i18n="add_placement_item">
                            <?= htmlspecialchars(_adst('add_placement_item', 'Add Item'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div style="padding:0;">
                        <table class="data-table" id="placementItemsTable">
                            <thead>
                                <tr>
                                    <th data-i18n="placement_items_table.id"><?= htmlspecialchars(_adst('placement_items_table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th data-i18n="placement_items_table.ad"><?= htmlspecialchars(_adst('placement_items_table.ad', 'Ad'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th data-i18n="placement_items_table.priority"><?= htmlspecialchars(_adst('placement_items_table.priority', 'Priority'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th data-i18n="placement_items_table.weight"><?= htmlspecialchars(_adst('placement_items_table.weight', 'Weight'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th data-i18n="placement_items_table.start_date"><?= htmlspecialchars(_adst('placement_items_table.start_date', 'Start Date'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th data-i18n="placement_items_table.end_date"><?= htmlspecialchars(_adst('placement_items_table.end_date', 'End Date'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th data-i18n="placement_items_table.actions"><?= htmlspecialchars(_adst('placement_items_table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                                </tr>
                            </thead>
                            <tbody id="placementItemsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <?= htmlspecialchars(_adst('placement_items_table.no_records', 'No placement items found'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tabPlacements -->

    <!-- ══════════════════════════════════════
         CAMPAIGN Add / Edit Modal
    ══════════════════════════════════════ -->
    <div id="campaignModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="campaignModalTitle" data-i18n="modal.add_campaign_title">
                <?= htmlspecialchars(_adst('modal.add_campaign_title', 'Add Campaign'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <form id="campaignForm" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="campaignId" name="id" value="">

                <!-- Name -->
                <div class="form-group">
                    <label for="campaignName" data-i18n="form.name">
                        <?= htmlspecialchars(_adst('form.name', 'Campaign Name'), ENT_QUOTES, 'UTF-8') ?> *
                    </label>
                    <input type="text" id="campaignName" name="name" class="form-control"
                           required maxlength="255">
                </div>

                <!-- Budget -->
                <div class="form-group">
                    <label for="campaignBudget" data-i18n="form.budget">
                        <?= htmlspecialchars(_adst('form.budget', 'Budget'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="number" id="campaignBudget" name="budget" class="form-control"
                           value="0" min="0" step="0.01">
                </div>

                <!-- Currency -->
                <div class="form-group">
                    <label for="campaignCurrencyId" data-i18n="form.currency_id">
                        <?= htmlspecialchars(_adst('form.currency_id', 'Currency'), ENT_QUOTES, 'UTF-8') ?> *
                    </label>
                    <select id="campaignCurrencyId" name="currency_id" class="form-control" required>
                        <option value="">
                            <?= htmlspecialchars(_adst('form.select_currency', '-- Select Currency --'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>

                <!-- Pricing Model -->
                <div class="form-group">
                    <label for="campaignPricingModel" data-i18n="form.pricing_model">
                        <?= htmlspecialchars(_adst('form.pricing_model', 'Pricing Model'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="campaignPricingModel" name="pricing_model" class="form-control">
                        <option value="fixed"><?= htmlspecialchars(_adst('pricing_model.fixed', 'Fixed'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="cpm"><?= htmlspecialchars(_adst('pricing_model.cpm', 'CPM'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="cpc"><?= htmlspecialchars(_adst('pricing_model.cpc', 'CPC'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

                <!-- Start Date -->
                <div class="form-group">
                    <label for="campaignStartDate" data-i18n="form.start_date">
                        <?= htmlspecialchars(_adst('form.start_date', 'Start Date'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="date" id="campaignStartDate" name="start_date" class="form-control">
                </div>

                <!-- End Date -->
                <div class="form-group">
                    <label for="campaignEndDate" data-i18n="form.end_date">
                        <?= htmlspecialchars(_adst('form.end_date', 'End Date'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="date" id="campaignEndDate" name="end_date" class="form-control">
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label for="campaignStatus" data-i18n="form.status">
                        <?= htmlspecialchars(_adst('form.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="campaignStatus" name="status" class="form-control">
                        <option value="draft"><?= htmlspecialchars(_adst('status.draft', 'Draft'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="paused"><?= htmlspecialchars(_adst('status.paused', 'Paused'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="completed"><?= htmlspecialchars(_adst('status.completed', 'Completed'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" id="campaignSaveBtn" class="btn btn-primary" data-i18n="form.save">
                        <?= htmlspecialchars(_adst('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="btn btn-secondary btn-close-ads-modal"
                            data-modal="campaignModal" data-i18n="form.cancel">
                        <?= htmlspecialchars(_adst('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         AD UNIT Add / Edit Modal
    ══════════════════════════════════════ -->
    <div id="adModal" class="modal" style="display:none;">
        <div class="modal-content modal-content-wide">
            <div class="modal-header">
                <h3 id="adModalTitle" data-i18n="modal.add_title">
                    <?= htmlspecialchars(_adst('modal.add_title', 'Add Ad Unit'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <button type="button" class="btn-close-ads-modal" data-modal="adModal" aria-label="Close">&times;</button>
            </div>

            <!-- Modal Tabs -->
            <div class="ad-modal-tabs">
                <button type="button" class="ad-modal-tab-btn active" data-modal-tab="basic">
                    <?= htmlspecialchars(_adst('tabs.basic', 'Basic'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="ad-modal-tab-btn" data-modal-tab="translations">
                    <?= htmlspecialchars(_adst('tabs.translations', 'Translations'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="ad-modal-tab-btn" data-modal-tab="images">
                    <?= htmlspecialchars(_adst('tabs.images', 'Images'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <form id="adForm" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="adId" name="id" value="">

                <!-- ── TAB: Basic ── -->
                <div id="adTab-basic" class="ad-modal-tab-content active">

                    <!-- Campaign -->
                    <div class="form-group">
                        <label for="adCampaignId" data-i18n="form.campaign_id">
                            <?= htmlspecialchars(_adst('form.campaign_id', 'Campaign'), ENT_QUOTES, 'UTF-8') ?> *
                        </label>
                        <select id="adCampaignId" name="campaign_id" class="form-control" required>
                            <option value="">
                                <?= htmlspecialchars(_adst('form.select_campaign', '-- Select Campaign --'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>

                    <!-- Target Type -->
                    <div class="form-group">
                        <label for="adTargetType" data-i18n="form.target_type">
                            <?= htmlspecialchars(_adst('form.target_type', 'Target Type'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="adTargetType" name="target_type" class="form-control">
                            <option value="url"><?= htmlspecialchars(_adst('target_type.url', 'URL'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="entity"><?= htmlspecialchars(_adst('target_type.entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>

                    <!-- Target Value -->
                    <div class="form-group">
                        <label for="adTargetValue" data-i18n="form.target_value">
                            <?= htmlspecialchars(_adst('form.target_value', 'Target Value'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="text" id="adTargetValue" name="target_value" class="form-control"
                               placeholder="<?= htmlspecialchars(_adst('form.target_value_placeholder_url', 'https://example.com'), ENT_QUOTES, 'UTF-8') ?>"
                               maxlength="500">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="adStatus" data-i18n="form.status">
                            <?= htmlspecialchars(_adst('form.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="adStatus" name="status" class="form-control">
                            <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="paused"><?= htmlspecialchars(_adst('status.paused', 'Paused'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="rejected"><?= htmlspecialchars(_adst('status.rejected', 'Rejected'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>



                    <!-- English Translation (Required) -->
                    <div class="form-group">
                        <label for="adEnTitle" data-i18n="en_title_label">
                            <?= htmlspecialchars(_adst('en_title_label', 'English Title'), ENT_QUOTES, 'UTF-8') ?> *
                        </label>
                        <input type="text" id="adEnTitle" name="en_title" class="form-control"
                               placeholder="<?= htmlspecialchars(_adst('en_title_placeholder', 'English title...'), ENT_QUOTES, 'UTF-8') ?>"
                               data-i18n-placeholder="en_title_placeholder"
                               maxlength="500">
                    </div>
                    <div class="form-group">
                        <label for="adEnDescription" data-i18n="en_description_label">
                            <?= htmlspecialchars(_adst('en_description_label', 'English Description'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <textarea id="adEnDescription" name="en_description" class="form-control" rows="2"
                                  placeholder="<?= htmlspecialchars(_adst('en_description_placeholder', 'English description...'), ENT_QUOTES, 'UTF-8') ?>"
                                  data-i18n-placeholder="en_description_placeholder"></textarea>
                    </div>
                    <p class="ad-translations-info" data-i18n="en_translation_note">
                        <?= htmlspecialchars(_adst('en_translation_note', 'English translation is required and will be saved automatically.'), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                </div><!-- /adTab-basic -->

                <!-- ── TAB: Translations ── -->
                <div id="adTab-translations" class="ad-modal-tab-content" style="display:none;">
                    <div class="ad-translations-info">
                        <i class="fas fa-info-circle"></i>
                        <?= htmlspecialchars(_adst('translations.info', 'Add title and description for each language. English is the default.'), ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <!-- Add translation form -->
                    <div class="ad-translation-add-row">
                        <select id="adTransLang" class="form-control">
                            <option value=""><?= htmlspecialchars(_adst('translations.select_language', '-- Select Language --'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="en">English (en)</option>
                            <option value="ar">العربية (ar)</option>
                            <option value="fr">Français (fr)</option>
                            <option value="tr">Türkçe (tr)</option>
                            <option value="ur">اردو (ur)</option>
                            <option value="de">Deutsch (de)</option>
                            <option value="es">Español (es)</option>
                            <option value="fa">فارسی (fa)</option>
                            <option value="he">עברית (he)</option>
                            <option value="hi">हिन्दी (hi)</option>
                            <option value="zh">中文 (zh)</option>
                            <option value="ja">日本語 (ja)</option>
                            <option value="ko">한국어 (ko)</option>
                            <option value="pt">Português (pt)</option>
                            <option value="ru">Русский (ru)</option>
                            <option value="it">Italiano (it)</option>
                            <option value="nl">Nederlands (nl)</option>
                        </select>
                        <input type="text" id="adTransTitle" class="form-control"
                               placeholder="<?= htmlspecialchars(_adst('translations.ad_title_placeholder', 'Ad title...'), ENT_QUOTES, 'UTF-8') ?>">
                        <textarea id="adTransDesc" class="form-control" rows="2"
                                  placeholder="<?= htmlspecialchars(_adst('translations.description_placeholder', 'Ad description...'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                        <button type="button" id="btnAddAdTranslation" class="btn btn-primary">
                            <?= htmlspecialchars(_adst('translations.add', 'Add / Update Translation'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>

                    <!-- Existing translations list -->
                    <div id="adTranslationsList"></div>
                </div><!-- /adTab-translations -->

                <!-- ── TAB: Images ── -->
                <div id="adTab-images" class="ad-modal-tab-content" style="display:none;">
                    <!-- Image Type Selector (required before opening studio) -->
                    <div class="form-group">
                        <label for="adImageType" data-i18n="images.image_type_label">
                            <?= htmlspecialchars(_adst('images.image_type_label', 'Image Type'), ENT_QUOTES, 'UTF-8') ?> *
                        </label>
                        <select id="adImageType" class="form-control">
                            <option value="">
                                <?= htmlspecialchars(_adst('images.image_type_placeholder', '-- Select Image Type --'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="13"><?= htmlspecialchars(_adst('images.types.ad_homepage_banner', 'Homepage Banner (1440×400)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="14"><?= htmlspecialchars(_adst('images.types.ad_section_banner', 'Section Banner (1200×300)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="15"><?= htmlspecialchars(_adst('images.types.ad_square', 'Square Ad (400×400)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="16"><?= htmlspecialchars(_adst('images.types.ad_store_banner', 'Store Banner (1200×300)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="17"><?= htmlspecialchars(_adst('images.types.ad_small', 'Small Ad (300×250)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="18"><?= htmlspecialchars(_adst('images.types.ad_search_banner', 'Search Banner (1200×200)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="19"><?= htmlspecialchars(_adst('images.types.ad_mobile_banner', 'Mobile Banner (768×250)'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="20"><?= htmlspecialchars(_adst('images.types.ad_thumb', 'Thumbnail (300×150)'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= htmlspecialchars(_adst('images.label', 'Ad Images'), ENT_QUOTES, 'UTF-8') ?></label>
                        <div class="image-upload-section">
                            <button type="button" id="adSelectImageBtn" class="btn btn-secondary"
                                    style="width:100%; margin-bottom:15px;">
                                <i class="fas fa-images"></i>
                                <?= htmlspecialchars(_adst('images.select_from_studio', 'Open Image Studio'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <div id="adImagesPreview" class="ad-images-grid"></div>
                        </div>
                    </div>
                </div><!-- /adTab-images -->

                <div class="form-actions">
                    <button type="button" id="adSaveBtn" class="btn btn-primary" data-i18n="form.save">
                        <?= htmlspecialchars(_adst('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="btn btn-secondary btn-close-ads-modal"
                            data-modal="adModal" data-i18n="form.cancel">
                        <?= htmlspecialchars(_adst('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         PLACEMENT Add / Edit Modal
    ══════════════════════════════════════ -->
    <div id="placementModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="placementModalTitle" data-i18n="add_placement">
                <?= htmlspecialchars(_adst('add_placement', 'Add Placement'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <form id="placementForm" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="placementId" name="id" value="">

                <!-- Name -->
                <div class="form-group">
                    <label for="placementName" data-i18n="placement_form.name">
                        <?= htmlspecialchars(_adst('placement_form.name', 'Placement Name'), ENT_QUOTES, 'UTF-8') ?> *
                    </label>
                    <input type="text" id="placementName" name="name" class="form-control"
                           required maxlength="255">
                </div>

                <!-- Placement Key -->
                <div class="form-group">
                    <label for="placementKey" data-i18n="placement_form.placement_key">
                        <?= htmlspecialchars(_adst('placement_form.placement_key', 'Placement Key'), ENT_QUOTES, 'UTF-8') ?> *
                    </label>
                    <input type="text" id="placementKey" name="placement_key" class="form-control"
                           required maxlength="100" pattern="[a-zA-Z0-9_-]+">
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="placementDescription" data-i18n="placement_form.description">
                        <?= htmlspecialchars(_adst('placement_form.description', 'Description'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <textarea id="placementDescription" name="description" class="form-control" rows="3"></textarea>
                </div>

                <!-- Code -->
                <div class="form-group">
                    <label for="placementCode" data-i18n="placement_form.code">
                        <?= htmlspecialchars(_adst('placement_form.code', 'Code'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text" id="placementCode" name="code" class="form-control"
                           maxlength="100" pattern="[a-zA-Z0-9_-]*">
                </div>

                <!-- Page -->
                <div class="form-group">
                    <label for="placementPage" data-i18n="placement_form.page">
                        <?= htmlspecialchars(_adst('placement_form.page', 'Page'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text" id="placementPage" name="page" class="form-control" maxlength="100">
                </div>

                <!-- Width / Height -->
                <div class="form-group" style="display:flex;gap:1rem;">
                    <div style="flex:1;">
                        <label for="placementWidth" data-i18n="placement_form.width">
                            <?= htmlspecialchars(_adst('placement_form.width', 'Width (px)'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="number" id="placementWidth" name="width" class="form-control" min="1">
                    </div>
                    <div style="flex:1;">
                        <label for="placementHeight" data-i18n="placement_form.height">
                            <?= htmlspecialchars(_adst('placement_form.height', 'Height (px)'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="number" id="placementHeight" name="height" class="form-control" min="1">
                    </div>
                </div>

                <!-- Max Ads -->
                <div class="form-group">
                    <label for="placementMaxAds" data-i18n="placement_form.max_ads">
                        <?= htmlspecialchars(_adst('placement_form.max_ads', 'Max Ads'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="number" id="placementMaxAds" name="max_ads" class="form-control" min="1" value="1">
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label for="placementStatus" data-i18n="placement_form.status">
                        <?= htmlspecialchars(_adst('placement_form.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="placementStatus" name="status" class="form-control">
                        <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="inactive"><?= htmlspecialchars(_adst('status.inactive', 'Inactive'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="draft"><?= htmlspecialchars(_adst('status.draft', 'Draft'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" id="placementSaveBtn" class="btn btn-primary" data-i18n="form.save">
                        <?= htmlspecialchars(_adst('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="btn btn-secondary btn-close-ads-modal"
                            data-modal="placementModal" data-i18n="form.cancel">
                        <?= htmlspecialchars(_adst('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         PLACEMENT ITEM Add / Edit Modal
    ══════════════════════════════════════ -->
    <div id="placementItemModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="placementItemModalTitle" data-i18n="add_placement_item">
                <?= htmlspecialchars(_adst('add_placement_item', 'Add Item'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <form id="placementItemForm" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="placementItemId"          name="id"           value="">
                <input type="hidden" id="placementItemPlacementId" name="placement_id" value="">

                <!-- Ad Unit -->
                <div class="form-group">
                    <label for="placementItemAdId" data-i18n="placement_item_form.ad_id">
                        <?= htmlspecialchars(_adst('placement_item_form.ad_id', 'Ad Unit'), ENT_QUOTES, 'UTF-8') ?> *
                    </label>
                    <select id="placementItemAdId" name="ad_id" class="form-control" required>
                        <option value="">
                            <?= htmlspecialchars(_adst('placement_item_form.select_ad', '-- Select Ad --'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>

                <!-- Priority -->
                <div class="form-group">
                    <label for="placementItemPriority" data-i18n="placement_item_form.priority">
                        <?= htmlspecialchars(_adst('placement_item_form.priority', 'Priority'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="number" id="placementItemPriority" name="priority"
                           class="form-control" value="1" min="1">
                </div>

                <!-- Weight -->
                <div class="form-group">
                    <label for="placementItemWeight" data-i18n="placement_item_form.weight">
                        <?= htmlspecialchars(_adst('placement_item_form.weight', 'Weight'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="number" id="placementItemWeight" name="weight"
                           class="form-control" value="1" min="1">
                </div>

                <!-- Start Date -->
                <div class="form-group">
                    <label for="placementItemStartDate" data-i18n="placement_item_form.start_date">
                        <?= htmlspecialchars(_adst('placement_item_form.start_date', 'Start Date'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="date" id="placementItemStartDate" name="start_date" class="form-control">
                </div>

                <!-- End Date -->
                <div class="form-group">
                    <label for="placementItemEndDate" data-i18n="placement_item_form.end_date">
                        <?= htmlspecialchars(_adst('placement_item_form.end_date', 'End Date'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="date" id="placementItemEndDate" name="end_date" class="form-control">
                </div>

                <div class="form-actions">
                    <button type="button" id="placementItemSaveBtn" class="btn btn-primary" data-i18n="form.save">
                        <?= htmlspecialchars(_adst('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="btn btn-secondary btn-close-ads-modal"
                            data-modal="placementItemModal" data-i18n="form.cancel">
                        <?= htmlspecialchars(_adst('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         Media Studio Overlay (for Ad Images)
    ══════════════════════════════════════ -->
    <div id="adMediaStudioModal" class="ad-media-studio-overlay" style="display:none;" role="dialog" aria-modal="true">
        <div class="ad-media-studio-container">
            <div class="ad-media-studio-header">
                <h4>
                    <i class="fas fa-images" style="margin-inline-end:8px;"></i>
                    <?= htmlspecialchars(_adst('images.select_from_studio', 'Select Images from Studio'), ENT_QUOTES, 'UTF-8') ?>
                </h4>
                <button type="button" id="adMediaStudioClose" class="btn btn-secondary btn-sm" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <iframe id="adMediaStudioFrame" class="ad-media-studio-frame" src="about:blank" title="Media Studio"></iframe>
        </div>
    </div>

</div>

<script>
window.ADS_CONFIG = {
    apiBase:           <?= json_encode($apiBase) ?>,
    csrfToken:         <?= json_encode($csrf) ?>,
    tenantId:          <?= (int)$tenantId ?>,
    lang:              <?= json_encode($_safeLang) ?>,
    dir:               <?= json_encode($dir) ?>,
    strings:           <?= json_encode($_adsStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:         <?= json_encode($canCreate) ?>,
    canEdit:           <?= json_encode($canEdit) ?>,
    canDelete:         <?= json_encode($canDelete) ?>,
    imagesApi:         <?= json_encode($apiBase . '/images') ?>,
    translationsApi:   <?= json_encode($apiBase . '/ad_translations') ?>,
    placementsApi:     <?= json_encode($apiBase . '/ad_placements') ?>,
    placementItemsApi: <?= json_encode($apiBase . '/ad_placement_items') ?>,
    statsApi:          '/api/get_ad_stats.php',
    trackViewApi:      '/api/track_view.php',
    trackClickApi:     '/api/track_click.php',
    adImageTypeId:     20
};
</script>
<script src="/admin/assets/js/pages/ads.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>