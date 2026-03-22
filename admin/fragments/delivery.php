<?php
declare(strict_types=1);

/**
 * /admin/fragments/delivery.php
 * Delivery Management Workspace — Production v2.0
 * ─ متوافق مع header.php v3.0 (single source of truth)
 * ─ لا hardcoded styles — كل التنسيق من CSS variables
 * ─ assetVer() بدل time() لـ cache-busting صحيح
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

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
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS (resource = 'delivery')
// ════════════════════════════════════════════════════════════
$canCreate = can_create('delivery')   || can('delivery.manage');
$canEdit   = can_edit_all('delivery') || can('delivery.manage');
$canDelete = can_delete_all('delivery') || can('delivery.manage');
$canView   = can_view_all('delivery') || can('delivery.manage');

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// TRANSLATIONS (server-side — injected via DELIVERY_CONFIG.strings)
// ════════════════════════════════════════════════════════════
$_dlStrings     = [];
$_dlAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
    'vi','id','ms','bn','sw','tl',
];
$_dlSafeLang = in_array($lang, $_dlAllowedLangs, true) ? $lang : 'en';
$_dlLangFile = __DIR__ . '/../../languages/Delivery/' . $_dlSafeLang . '.json';

if (file_exists($_dlLangFile)) {
    $_dlJson = json_decode(file_get_contents($_dlLangFile), true);
    if (is_array($_dlJson)) {
        $_dlStrings = isset($_dlJson['strings']) ? $_dlJson['strings'] : $_dlJson;
    }
}

/**
 * Translate dot-notation key — PHP fallback only.
 * Runtime translations handled by data-i18n attributes via admin_core.js.
 */
function _dlt(string $key, string $fallback = ''): string
{
    global $_dlStrings;
    $parts = explode('.', $key);
    $val   = $_dlStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback !== '' ? $fallback : $key;
        }
    }
    return is_string($val) ? $val : ($fallback !== '' ? $fallback : $key);
}

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
      href="/admin/assets/css/pages/delivery.css?v=<?= assetVer('/admin/assets/css/pages/delivery.css') ?>">

<meta data-page="delivery"
      data-assets-css="/admin/assets/css/pages/delivery.css"
      data-assets-js="/admin/assets/js/pages/delivery.js"
      data-i18n-files="/languages/Delivery/<?= rawurlencode($_dlSafeLang) ?>.json">

<div class="page-container" id="deliveryPageContainer" dir="<?= htmlspecialchars($dir) ?>">

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title" data-i18n="delivery.title"><?= _dlt('delivery.title','Delivery Management') ?></h1>
        <p class="page-subtitle" data-i18n="delivery.subtitle"><?= _dlt('delivery.subtitle','Manage zones, providers, orders and tracking') ?></p>
    </div>
</div>

<!-- ═══ WORKSPACE TABS ═══ -->
<div class="workspace-tabs" id="workspaceTabs">
    <button class="tab-btn active" data-tab="zones"><i class="fas fa-map-marked-alt"></i> <span data-i18n="workspace.tabs.zones">Zones</span></button>
    <button class="tab-btn" data-tab="providers"><i class="fas fa-motorcycle"></i> <span data-i18n="workspace.tabs.providers">Providers</span></button>
    <button class="tab-btn" data-tab="orders"><i class="fas fa-box"></i> <span data-i18n="workspace.tabs.orders">Orders</span></button>
    <button class="tab-btn" data-tab="locations"><i class="fas fa-location-arrow"></i> <span data-i18n="workspace.tabs.locations">Locations</span></button>
    <button class="tab-btn" data-tab="tracking"><i class="fas fa-route"></i> <span data-i18n="workspace.tabs.tracking">Tracking</span></button>
    <button class="tab-btn" data-tab="provider_zones"><i class="fas fa-link"></i> <span data-i18n="workspace.tabs.provider_zones">Provider Zones</span></button>
</div>

<!-- ══════════════════════════════════════════
     TAB: ZONES  (Leaflet map + form + list)
══════════════════════════════════════════ -->
<div id="zonesTab" class="ws-panel active">

    <!-- Zone Form (slide-in card) -->
    <div id="zoneFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="zoneFormTitle"><?= _dlt('delivery.zone.add','Add Zone') ?></h3>
            <button class="btn btn-sm btn-outline" id="zoneCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="zoneForm" novalidate>
                <input type="hidden" id="zoneId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.zone.name','Zone Name') ?></label>
                        <input type="text" id="zoneName" name="zone_name" class="form-control" required>
                    </div>
                    <div class="form-group col-3">
                        <label class="required"><?= _dlt('delivery.zone.type','Type') ?></label>
                        <select id="zoneType" name="zone_type" class="form-control" required>
                            <option value="city">City</option>
                            <option value="district">District</option>
                            <option value="radius">Radius</option>
                            <option value="polygon">Polygon</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label><?= _dlt('delivery.zone.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="zoneProviderId" name="provider_id" class="form-control" placeholder="<?= _dlt('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="zoneProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.zone.country','Country') ?></label>
                        <select id="zoneCountryId" name="_country_id" class="form-control">
                            <option value="">-- <?= _dlt('delivery.zone.select_country','Select Country') ?> --</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.zone.city','City') ?></label>
                        <select id="zoneCityId" name="city_id" class="form-control">
                            <option value="">-- <?= _dlt('delivery.zone.select_city','Select City') ?> --</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.zone.fee','Delivery Fee') ?></label>
                        <input type="number" id="zoneFee" name="delivery_fee" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.zone.est_time','Est. Time (min)') ?></label>
                        <input type="number" id="zoneTime" name="estimated_minutes" class="form-control" value="45" min="0">
                    </div>
                </div>

                <!-- Radius-specific fields -->
                <div id="radiusFields" class="form-row" style="display:none">
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.zone.center_lat','Center Lat') ?></label>
                        <div class="coord-input-wrap">
                            <input type="text" id="zoneLat" name="center_lat" class="form-control">
                            <button type="button" class="btn btn-sm btn-outline btn-pick-map" data-lat="zoneLat" data-lng="zoneLng" title="<?= _dlt('delivery.location.pick_map','Pick on map') ?>" aria-label="<?= _dlt('delivery.location.pick_map','Pick on map') ?>"><i class="fas fa-map-pin" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.zone.center_lng','Center Lng') ?></label>
                        <input type="text" id="zoneLng" name="center_lng" class="form-control">
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.zone.radius_km','Radius (km)') ?></label>
                        <input type="number" id="zoneRadius" name="radius_km" class="form-control" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label><?= _dlt('delivery.zone.min_order','Min Order Value') ?></label>
                        <input type="number" id="zoneMinOrder" name="min_order_value" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group col-6">
                        <label><?= _dlt('delivery.zone.free_delivery','Free Delivery Over') ?></label>
                        <input type="number" id="zoneFreeDelivery" name="free_delivery_over" class="form-control" step="0.01" min="0">
                    </div>
                </div>

                <!-- GeoJSON boundary (populated by map drawing) -->
                <div class="form-group">
                    <label><?= _dlt('delivery.zone.boundary','Zone Boundary (GeoJSON)') ?>
                        <small class="text-muted"> — <?= _dlt('delivery.zone.draw_hint','draw on map or paste JSON') ?></small>
                    </label>
                    <textarea id="zoneGeoJson" name="zone_value" class="form-control zone-geojson" rows="3" placeholder='{"type":"Polygon","coordinates":[...]}'></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="zoneActive" name="is_active" value="1" checked>
                        <span><?= _dlt('common.active','Active') ?></span>
                    </label>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= _dlt('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="zoneCancelBtn"><?= _dlt('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Zones workspace: sidebar list + Leaflet map -->
    <div class="zones-workspace">
        <aside class="zones-sidebar">
            <div class="zones-sidebar-header">
                <input type="text" id="zonesSearch" class="form-control" placeholder="<?= _dlt('common.search','Search') ?>...">
                <select id="zonesTypeFilter" class="form-control">
                    <option value=""><?= _dlt('common.all_types','All Types') ?></option>
                    <option value="city">City</option><option value="district">District</option>
                    <option value="radius">Radius</option><option value="polygon">Polygon</option>
                </select>
                <select id="zonesActiveFilter" class="form-control">
                    <option value=""><?= _dlt('common.all','All') ?></option>
                    <option value="1"><?= _dlt('common.active','Active') ?></option>
                    <option value="0"><?= _dlt('common.inactive','Inactive') ?></option>
                </select>
                <div class="sidebar-filter-actions">
                    <button id="zonesApplyFilter" class="btn btn-secondary btn-sm"><?= _dlt('common.apply','Apply') ?></button>
                    <button id="zonesResetFilter" class="btn btn-outline btn-sm"><?= _dlt('common.reset','Reset') ?></button>
                </div>
            </div>
            <div id="zonesList" class="zones-list">
                <div id="zonesListLoading" class="loading-state"><div class="spinner"></div></div>
                <div id="zonesListItems"></div>
                <div id="zonesListEmpty" class="empty-state" style="display:none"><div class="empty-icon">🗺️</div><p><?= _dlt('delivery.zone.empty','No zones found') ?></p></div>
            </div>
            <div class="zones-sidebar-footer">
                <div class="pagination-wrapper">
                    <span id="zonesPaginationInfo"></span>
                    <div id="zonesPagination" class="pagination"></div>
                </div>
                <?php if ($canCreate): ?>
                <button id="zonesAddBtn" class="btn btn-primary btn-block"><i class="fas fa-plus"></i> <?= _dlt('delivery.zone.add','Add Zone') ?></button>
                <?php endif; ?>
            </div>
        </aside>

        <div class="zones-map-panel">
            <div class="zones-map-toolbar">
                <button type="button" id="zonesLocateBtn" class="btn btn-sm btn-outline" aria-label="<?= _dlt('delivery.location.use_gps','Use my location') ?>">
                    <i class="fas fa-crosshairs" aria-hidden="true"></i>
                    <span><?= _dlt('delivery.location.use_gps','Use my location') ?></span>
                </button>
                <button type="button" id="zonesFullscreenBtn" class="btn btn-sm btn-outline" aria-label="<?= _dlt('delivery.map.fullscreen','Fullscreen map') ?>">
                    <i class="fas fa-expand-arrows-alt" aria-hidden="true"></i>
                    <span><?= _dlt('delivery.map.fullscreen','Fullscreen map') ?></span>
                </button>
            </div>
            <div class="zones-map-canvas">
                <div id="zonesMap" class="delivery-map"></div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: PROVIDERS
══════════════════════════════════════════ -->
<div id="providersTab" class="ws-panel" style="display:none">
    <div id="providerFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="providerFormTitle"><?= _dlt('delivery.provider.details','Provider Details') ?></h3>
            <button class="btn btn-sm btn-outline" id="providerCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="providerForm" novalidate>
                <input type="hidden" id="providerId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="required"><?= _dlt('delivery.provider.type','Type') ?></label>
                        <select id="providerType" name="provider_type" class="form-control" required>
                            <option value="company">Company</option>
                            <option value="entity_driver">Entity Driver</option>
                            <option value="independent_driver">Independent Driver</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.provider.vehicle','Vehicle') ?></label>
                        <select id="providerVehicle" name="vehicle_type" class="form-control">
                            <option value="bike">Bike</option>
                            <option value="car">Car</option>
                            <option value="van">Van</option>
                            <option value="truck">Truck</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.provider.license','License No.') ?></label>
                        <input type="text" id="providerLicense" name="license_number" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label><?= _dlt('delivery.provider.tenant_user','Tenant User') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="providerTenantUserId" name="tenant_user_id" class="form-control" placeholder="<?= _dlt('delivery.provider.type_id','Type user ID') ?>..." min="1">
                            <span id="providerTenantUserName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <label><?= _dlt('delivery.provider.entity','Entity') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="providerEntityId" name="entity_id" class="form-control" placeholder="<?= _dlt('delivery.provider.type_id','Type entity ID') ?>..." min="1">
                            <span id="providerEntityName" class="provider-name-badge"></span>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="checkbox-label"><input type="checkbox" id="providerOnline" name="is_online" value="1"> <?= _dlt('delivery.provider.online','Online') ?></label>
                    </div>
                    <div class="form-group col-6">
                        <label class="checkbox-label"><input type="checkbox" id="providerActive" name="is_active" value="1" checked> <?= _dlt('common.active','Active') ?></label>
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= _dlt('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="providerCancelBtn"><?= _dlt('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group filter-group--search"><label><?= _dlt('common.search','Search') ?></label><input type="text" id="providersSearch" class="form-control"></div>
                <div class="filter-group"><label><?= _dlt('delivery.provider.type','Type') ?></label>
                    <select id="providersTypeFilter" class="form-control">
                        <option value=""><?= _dlt('common.all','All') ?></option>
                        <option value="company">Company</option>
                        <option value="entity_driver">Entity Driver</option>
                        <option value="independent_driver">Independent Driver</option>
                    </select>
                </div>
                <div class="filter-group"><label><?= _dlt('delivery.provider.vehicle','Vehicle') ?></label>
                    <select id="providersVehicleFilter" class="form-control">
                        <option value=""><?= _dlt('common.all','All') ?></option>
                        <option value="bike">Bike</option><option value="car">Car</option>
                        <option value="van">Van</option><option value="truck">Truck</option>
                    </select>
                </div>
                <div class="filter-group"><label><?= _dlt('delivery.provider.status','Status') ?></label>
                    <select id="providersActiveFilter" class="form-control">
                        <option value=""><?= _dlt('common.all','All') ?></option>
                        <option value="1"><?= _dlt('common.active','Active') ?></option>
                        <option value="0"><?= _dlt('common.inactive','Inactive') ?></option>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button id="providersApplyFilter" class="btn btn-secondary"><?= _dlt('common.apply','Apply') ?></button>
                    <button id="providersResetFilter" class="btn btn-outline"><?= _dlt('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.provider.title','Delivery Providers') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="providersAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= _dlt('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="providersTableLoading" class="loading-state"><div class="spinner"></div><p><?= _dlt('common.loading','Loading...') ?></p></div>
            <div id="providersTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= _dlt('delivery.provider.type','Type') ?></th><th><?= _dlt('delivery.provider.vehicle','Vehicle') ?></th>
                        <th><?= _dlt('delivery.provider.online','Online') ?></th><th><?= _dlt('delivery.provider.rating','Rating') ?></th>
                        <th><?= _dlt('delivery.provider.deliveries','Deliveries') ?></th><th><?= _dlt('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="providersTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="providersPaginationInfo"></span><div id="providersPagination" class="pagination"></div></div>
            </div>
            <div id="providersEmptyState" class="empty-state" style="display:none"><div class="empty-icon">🚴</div><h3><?= _dlt('delivery.provider.empty','No providers found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: ORDERS
══════════════════════════════════════════ -->
<div id="ordersTab" class="ws-panel" style="display:none">
    <div id="orderFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="orderFormTitle"><?= _dlt('delivery.order.details','Order Details') ?></h3>
            <button class="btn btn-sm btn-outline" id="orderCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="orderForm" novalidate>
                <input type="hidden" id="orderId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="required"><?= _dlt('delivery.order.order_id','Order ID') ?></label>
                        <input type="number" id="orderOrderId" name="order_id" class="form-control" required min="1">
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.order.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="orderProviderId" name="provider_id" class="form-control" placeholder="<?= _dlt('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="orderProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-4">
                        <label><?= _dlt('delivery.order.status','Status') ?></label>
                        <select id="orderStatus" name="delivery_status" class="form-control">
                            <option value="pending">Pending</option><option value="assigned">Assigned</option>
                            <option value="accepted">Accepted</option><option value="picked_up">Picked Up</option>
                            <option value="on_the_way">On The Way</option><option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.order.pickup','Pickup Address ID') ?></label>
                        <input type="number" id="orderPickup" name="pickup_address_id" class="form-control" required min="1">
                    </div>
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.order.dropoff','Dropoff Address ID') ?></label>
                        <input type="number" id="orderDropoff" name="dropoff_address_id" class="form-control" required min="1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <label><?= _dlt('delivery.order.fee','Delivery Fee') ?></label>
                        <input type="number" id="orderFee" name="delivery_fee" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-3">
                        <label><?= _dlt('delivery.order.calc_fee','Calculated Fee') ?></label>
                        <input type="number" id="orderCalcFee" name="calculated_fee" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-3">
                        <label><?= _dlt('delivery.order.payout','Provider Payout') ?></label>
                        <input type="number" id="orderPayout" name="provider_payout" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-3">
                        <label><?= _dlt('delivery.order.zone','Delivery Zone') ?></label>
                        <select id="orderZoneId" name="delivery_zone_id" class="form-control"><option value="">-- <?= _dlt('common.none','None') ?> --</option></select>
                    </div>
                </div>
                <div id="cancelFields" class="form-row" style="display:none">
                    <div class="form-group col-6">
                        <label><?= _dlt('delivery.order.cancelled_by','Cancelled By') ?></label>
                        <select id="orderCancelledBy" name="cancelled_by" class="form-control">
                            <option value="">--</option><option value="customer">Customer</option>
                            <option value="provider">Provider</option><option value="admin">Admin</option><option value="system">System</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label><?= _dlt('delivery.order.cancel_reason','Cancellation Reason') ?></label>
                        <input type="text" id="orderCancelReason" name="cancellation_reason" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= _dlt('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="orderCancelBtn"><?= _dlt('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= _dlt('delivery.order.status','Status') ?></label>
                    <select id="ordersStatusFilter" class="form-control">
                        <option value=""><?= _dlt('common.all','All') ?></option>
                        <option value="pending">Pending</option><option value="assigned">Assigned</option>
                        <option value="accepted">Accepted</option><option value="picked_up">Picked Up</option>
                        <option value="on_the_way">On The Way</option><option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-group"><label><?= _dlt('delivery.order.provider','Provider') ?></label>
                    <select id="ordersProviderFilter" class="form-control"><option value=""><?= _dlt('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= _dlt('delivery.order.zone','Zone') ?></label>
                    <select id="ordersZoneFilter" class="form-control"><option value=""><?= _dlt('common.all','All') ?></option></select>
                </div>
                <div class="filter-buttons">
                    <button id="ordersApplyFilter" class="btn btn-secondary"><?= _dlt('common.apply','Apply') ?></button>
                    <button id="ordersResetFilter" class="btn btn-outline"><?= _dlt('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.order.title','Delivery Orders') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="ordersAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= _dlt('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="ordersTableLoading" class="loading-state"><div class="spinner"></div><p><?= _dlt('common.loading','Loading...') ?></p></div>
            <div id="ordersTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= _dlt('delivery.order.order_id','Order') ?></th><th><?= _dlt('delivery.order.provider','Provider') ?></th>
                        <th><?= _dlt('delivery.order.status','Status') ?></th><th><?= _dlt('delivery.order.fee','Fee') ?></th>
                        <th><?= _dlt('delivery.order.zone','Zone') ?></th><th><?= _dlt('common.created','Created') ?></th><th><?= _dlt('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="ordersTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="ordersPaginationInfo"></span><div id="ordersPagination" class="pagination"></div></div>
            </div>
            <div id="ordersEmptyState" class="empty-state" style="display:none"><div class="empty-icon">📦</div><h3><?= _dlt('delivery.order.empty','No orders found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: LOCATIONS (Driver Locations)
══════════════════════════════════════════ -->
<div id="locationsTab" class="ws-panel" style="display:none">
    <div id="locationFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.location.update','Update Location') ?></h3>
            <button class="btn btn-sm btn-outline" id="locationCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="locationForm" novalidate>
                <input type="hidden" id="locationId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.location.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="locationProviderId" name="provider_id" class="form-control" required placeholder="<?= _dlt('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="locationProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <label class="required"><?= _dlt('delivery.location.lat','Latitude') ?></label>
                        <div class="coord-input-wrap">
                            <input type="text" id="locationLat" name="latitude" class="form-control" required placeholder="24.7136">
                            <button type="button" class="btn btn-sm btn-outline btn-pick-map" data-lat="locationLat" data-lng="locationLng" title="<?= _dlt('delivery.location.pick_map','Pick on map') ?>" aria-label="<?= _dlt('delivery.location.pick_map','Pick on map') ?>"><i class="fas fa-map-pin" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline btn-use-gps" data-lat="locationLat" data-lng="locationLng" title="<?= _dlt('delivery.location.use_gps','Use my location') ?>" aria-label="<?= _dlt('delivery.location.use_gps','Use my location') ?>"><i class="fas fa-crosshairs" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <label class="required"><?= _dlt('delivery.location.lng','Longitude') ?></label>
                        <input type="text" id="locationLng" name="longitude" class="form-control" required placeholder="46.6753">
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= _dlt('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="locationCancelBtn"><?= _dlt('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= _dlt('delivery.location.provider','Provider') ?></label>
                    <select id="locationsProviderFilter" class="form-control"><option value=""><?= _dlt('common.all','All') ?></option></select>
                </div>
                <div class="filter-buttons">
                    <button id="locationsApplyFilter" class="btn btn-secondary"><?= _dlt('common.apply','Apply') ?></button>
                    <button id="locationsResetFilter" class="btn btn-outline"><?= _dlt('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.location.title','Driver Locations') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="locationsAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= _dlt('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="locationsTableLoading" class="loading-state"><div class="spinner"></div><p><?= _dlt('common.loading','Loading...') ?></p></div>
            <div id="locationsTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= _dlt('delivery.location.provider','Provider') ?></th>
                        <th><?= _dlt('delivery.location.lat','Lat') ?></th><th><?= _dlt('delivery.location.lng','Lng') ?></th>
                        <th><?= _dlt('common.updated','Updated') ?></th><th><?= _dlt('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="locationsTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="locationsPaginationInfo"></span><div id="locationsPagination" class="pagination"></div></div>
            </div>
            <div id="locationsEmptyState" class="empty-state" style="display:none"><div class="empty-icon">📍</div><h3><?= _dlt('delivery.location.empty','No locations found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: TRACKING
══════════════════════════════════════════ -->
<div id="trackingTab" class="ws-panel" style="display:none">
    <div id="trackingFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.tracking.log','Tracking Log') ?></h3>
            <button class="btn btn-sm btn-outline" id="trackingCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="trackingForm" novalidate>
                <input type="hidden" id="trackingId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.tracking.order','Delivery Order') ?></label>
                        <select id="trackingOrderId" name="delivery_order_id" class="form-control" required>
                            <option value="">-- <?= _dlt('common.select','Select') ?> --</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label><?= _dlt('delivery.tracking.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="trackingProviderId" name="provider_id" class="form-control" placeholder="<?= _dlt('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="trackingProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.tracking.lat','Latitude') ?></label>
                        <div class="coord-input-wrap">
                            <input type="text" id="trackingLat" name="latitude" class="form-control" required placeholder="24.7136">
                            <button type="button" class="btn btn-sm btn-outline btn-pick-map" data-lat="trackingLat" data-lng="trackingLng" title="<?= _dlt('delivery.location.pick_map','Pick on map') ?>" aria-label="<?= _dlt('delivery.location.pick_map','Pick on map') ?>"><i class="fas fa-map-pin" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline btn-use-gps" data-lat="trackingLat" data-lng="trackingLng" title="<?= _dlt('delivery.location.use_gps','Use my location') ?>" aria-label="<?= _dlt('delivery.location.use_gps','Use my location') ?>"><i class="fas fa-crosshairs" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.tracking.lng','Longitude') ?></label>
                        <input type="text" id="trackingLng" name="longitude" class="form-control" required placeholder="46.6753">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= _dlt('delivery.tracking.note','Status Note') ?></label>
                    <input type="text" id="trackingNote" name="status_note" class="form-control" maxlength="255">
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= _dlt('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="trackingCancelBtn"><?= _dlt('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= _dlt('delivery.tracking.order','Order') ?></label>
                    <select id="trackingOrderFilter" class="form-control"><option value=""><?= _dlt('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= _dlt('delivery.tracking.provider','Provider') ?></label>
                    <select id="trackingProviderFilter" class="form-control"><option value=""><?= _dlt('common.all','All') ?></option></select>
                </div>
                <div class="filter-buttons">
                    <button id="trackingApplyFilter" class="btn btn-secondary"><?= _dlt('common.apply','Apply') ?></button>
                    <button id="trackingResetFilter" class="btn btn-outline"><?= _dlt('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.tracking.title','Order Tracking') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="trackingAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= _dlt('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="trackingTableLoading" class="loading-state"><div class="spinner"></div><p><?= _dlt('common.loading','Loading...') ?></p></div>
            <div id="trackingTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= _dlt('delivery.tracking.order','Order') ?></th><th><?= _dlt('delivery.tracking.provider','Provider') ?></th>
                        <th><?= _dlt('delivery.tracking.lat','Lat') ?></th><th><?= _dlt('delivery.tracking.lng','Lng') ?></th>
                        <th><?= _dlt('delivery.tracking.note','Note') ?></th><th><?= _dlt('common.time','Time') ?></th>
                        <th><?= _dlt('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="trackingTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="trackingPaginationInfo"></span><div id="trackingPagination" class="pagination"></div></div>
            </div>
            <div id="trackingEmptyState" class="empty-state" style="display:none"><div class="empty-icon">🛣️</div><h3><?= _dlt('delivery.tracking.empty','No tracking logs') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: PROVIDER ZONES
══════════════════════════════════════════ -->
<div id="provider_zonesTab" class="ws-panel" style="display:none">
    <div id="pzoneFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.pzone.assign','Assign Zone to Provider') ?></h3>
            <button class="btn btn-sm btn-outline" id="pzoneCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="pzoneForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.pzone.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="pzoneProviderId" name="provider_id" class="form-control" required placeholder="<?= _dlt('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="pzoneProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <label class="required"><?= _dlt('delivery.pzone.zone','Zone') ?></label>
                        <select id="pzoneZoneId" name="zone_id" class="form-control" required>
                            <option value="">-- <?= _dlt('common.select','Select') ?> --</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="pzoneActive" name="is_active" value="1" checked>
                        <span><?= _dlt('common.active','Active') ?></span>
                    </label>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= _dlt('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="pzoneCancelBtn"><?= _dlt('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= _dlt('delivery.pzone.provider','Provider') ?></label>
                    <select id="pzonesProviderFilter" class="form-control"><option value=""><?= _dlt('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= _dlt('delivery.pzone.zone','Zone') ?></label>
                    <select id="pzonesZoneFilter" class="form-control"><option value=""><?= _dlt('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= _dlt('common.status','Status') ?></label>
                    <select id="pzonesActiveFilter" class="form-control">
                        <option value=""><?= _dlt('common.all','All') ?></option>
                        <option value="1"><?= _dlt('common.active','Active') ?></option>
                        <option value="0"><?= _dlt('common.inactive','Inactive') ?></option>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button id="pzonesApplyFilter" class="btn btn-secondary"><?= _dlt('common.apply','Apply') ?></button>
                    <button id="pzonesResetFilter" class="btn btn-outline"><?= _dlt('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= _dlt('delivery.pzone.title','Provider Zones') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="pzonesAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= _dlt('delivery.pzone.assign','Assign') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="pzonesTableLoading" class="loading-state"><div class="spinner"></div><p><?= _dlt('common.loading','Loading...') ?></p></div>
            <div id="pzonesTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th><?= _dlt('delivery.pzone.provider','Provider') ?></th><th><?= _dlt('delivery.pzone.zone','Zone') ?></th>
                        <th><?= _dlt('common.active','Active') ?></th><th><?= _dlt('delivery.pzone.assigned_at','Assigned') ?></th>
                        <th><?= _dlt('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="pzonesTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="pzonesPaginationInfo"></span><div id="pzonesPagination" class="pagination"></div></div>
            </div>
            <div id="pzonesEmptyState" class="empty-state" style="display:none"><div class="empty-icon">🔗</div><h3><?= _dlt('delivery.pzone.empty','No assignments found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     COORDINATE PICKER MODAL
══════════════════════════════════════════ -->
<div id="coordPickerModal"
     class="dl-modal-backdrop"
     role="dialog"
     aria-modal="true"
     aria-labelledby="coordPickerModalTitle"
     style="display:none">
    <div class="dl-modal-panel dl-modal-panel--wide">
        <div class="dl-modal-header">
            <h3 id="coordPickerModalTitle"><?= _dlt('delivery.map.pick_coords','Pick Location on Map') ?></h3>
            <button type="button"
                    class="btn-close-modal icon-btn"
                    id="coordModalClose"
                    data-modal="coordPickerModal"
                    aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="dl-modal-body">
            <div class="dl-coord-search">
                <input type="text" id="coordSearchInput" class="form-control" placeholder="<?= _dlt('delivery.map.search_place','Search place...') ?>">
                <button type="button" id="coordSearchBtn" class="btn btn-secondary btn-sm"><i class="fas fa-search" aria-hidden="true"></i></button>
            </div>
            <div id="coordPickerMap" class="dl-coord-map"></div>
            <div class="dl-coord-footer">
                <span><?= _dlt('delivery.map.click_to_select','Click on the map to select a location') ?></span>
                <span id="coordDisplay" class="coord-display"></span>
                <button type="button" id="coordConfirmBtn" class="btn btn-primary" disabled><?= _dlt('delivery.map.confirm','Confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Error Toast -->
<div id="deliveryToast" class="dl-toast" style="display:none"></div>

</div><!-- /page-container -->

<script>
window.DELIVERY_CONFIG = {
    lang:      <?= json_encode($_dlSafeLang) ?>,
    dir:       <?= json_encode($dir) ?>,
    tenantId:  <?= (int) $tenantId ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    userId:    <?= (int) $userId ?>,
    strings:   <?= json_encode($_dlStrings, JSON_UNESCAPED_UNICODE) ?>,
    mapCenter: [24.7136, 46.6753],
    mapZoom:   5,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit:   <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>,
    isSuperAdmin: <?= json_encode(is_super_admin()) ?>,
    urls: {
        zones:          '/api/delivery_zones',
        providers:      '/api/delivery_providers',
        orders:         '/api/delivery_orders',
        locations:      '/api/driver_locations',
        tracking:       '/api/delivery_tracking',
        provider_zones: '/api/provider_zones',
        cities:         '/api/cities',
        entities:       '/api/entities',
        tenant_users:   '/api/tenant_users',
        tenants:        '/api/tenants',
        countries:      '/api/countries',
        currencies:     '/api/currencies'
    }
};
</script>
<script src="/admin/assets/js/pages/delivery.js?v=<?= assetVer('/admin/assets/js/pages/delivery.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>