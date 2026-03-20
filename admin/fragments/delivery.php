<?php
declare(strict_types=1);
/**
 * /admin/fragments/delivery.php
 * Complete Delivery Management Workspace
 * Includes: Zones (with Leaflet map), Providers, Orders, Driver Locations, Tracking, Provider Zones
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }
    else { header('Location: /admin/login.php'); exit; }
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = function_exists('admin_dir') ? admin_dir() : (in_array($lang, ['ar','he','fa','ur']) ? 'rtl' : 'ltr');
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canCreate = can_create('delivery')   || can('delivery.manage');
$canEdit   = can_edit_all('delivery') || can('delivery.manage');
$canDelete = can_delete_all('delivery') || can('delivery.manage');
$canView   = can_view_all('delivery') || can('delivery.manage');

if (!$canView && !is_super_admin()) {
    if ($isFragment) { http_response_code(403); echo json_encode(['error' => 'Access denied']); exit; }
    http_response_code(403); die('Access denied');
}

if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string {
        if (function_exists('i18n_get')) { $v = i18n_get($key); return $v ?? ($fallback ?: $key); }
        static $trans = null;
        if ($trans === null) {
            $langCode = function_exists('admin_lang') ? admin_lang() : 'ar';
            $file = __DIR__ . '/../../languages/Delivery/' . $langCode . '.json';
            if (!is_readable($file)) {
                $file = __DIR__ . '/../../languages/Delivery/ar.json';
            }
            $raw   = is_readable($file) ? file_get_contents($file) : false;
            $trans = $raw !== false ? (json_decode($raw, true) ?? []) : [];
        }
        $parts = explode('.', $key);
        $val   = $trans;
        foreach ($parts as $part) {
            $val = (is_array($val) && array_key_exists($part, $val)) ? $val[$part] : null;
            if ($val === null) { break; }
        }
        return is_string($val) ? $val : ($fallback ?: $key);
    }
}
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/delivery.css?v=<?= time() ?>">
<?php endif; ?>
<meta data-page="delivery" data-i18n-files="/languages/Delivery/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="deliveryPageContainer" dir="<?= htmlspecialchars($dir) ?>">

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title" data-i18n="delivery.title"><?= __t('delivery.title','Delivery Management') ?></h1>
        <p class="page-subtitle" data-i18n="delivery.subtitle"><?= __t('delivery.subtitle','Manage zones, providers, orders and tracking') ?></p>
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
            <h3 class="card-title" id="zoneFormTitle"><?= __t('delivery.zone.add','Add Zone') ?></h3>
            <button class="btn btn-sm btn-outline" id="zoneCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="zoneForm" novalidate>
                <input type="hidden" id="zoneId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.zone.name','Zone Name') ?></label>
                        <input type="text" id="zoneName" name="zone_name" class="form-control" required>
                    </div>
                    <div class="form-group col-3">
                        <label class="required"><?= __t('delivery.zone.type','Type') ?></label>
                        <select id="zoneType" name="zone_type" class="form-control" required>
                            <option value="city">City</option>
                            <option value="district">District</option>
                            <option value="radius">Radius</option>
                            <option value="polygon">Polygon</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label><?= __t('delivery.zone.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="zoneProviderId" name="provider_id" class="form-control" placeholder="<?= __t('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="zoneProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-4">
                        <label><?= __t('delivery.zone.country','Country') ?></label>
                        <select id="zoneCountryId" name="_country_id" class="form-control">
                            <option value="">-- <?= __t('delivery.zone.select_country','Select Country') ?> --</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.zone.city','City') ?></label>
                        <select id="zoneCityId" name="city_id" class="form-control">
                            <option value="">-- <?= __t('delivery.zone.select_city','Select City') ?> --</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.zone.fee','Delivery Fee') ?></label>
                        <input type="number" id="zoneFee" name="delivery_fee" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.zone.est_time','Est. Time (min)') ?></label>
                        <input type="number" id="zoneTime" name="estimated_minutes" class="form-control" value="45" min="0">
                    </div>
                </div>

                <!-- Radius-specific fields -->
                <div id="radiusFields" class="form-row" style="display:none">
                    <div class="form-group col-4">
                        <label><?= __t('delivery.zone.center_lat','Center Lat') ?></label>
                        <div class="coord-input-wrap">
                            <input type="text" id="zoneLat" name="center_lat" class="form-control">
                            <button type="button" class="btn btn-sm btn-outline btn-pick-map" data-lat="zoneLat" data-lng="zoneLng" title="<?= __t('delivery.location.pick_map','Pick on map') ?>" aria-label="<?= __t('delivery.location.pick_map','Pick on map') ?>"><i class="fas fa-map-pin" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.zone.center_lng','Center Lng') ?></label>
                        <input type="text" id="zoneLng" name="center_lng" class="form-control">
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.zone.radius_km','Radius (km)') ?></label>
                        <input type="number" id="zoneRadius" name="radius_km" class="form-control" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label><?= __t('delivery.zone.min_order','Min Order Value') ?></label>
                        <input type="number" id="zoneMinOrder" name="min_order_value" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group col-6">
                        <label><?= __t('delivery.zone.free_delivery','Free Delivery Over') ?></label>
                        <input type="number" id="zoneFreeDelivery" name="free_delivery_over" class="form-control" step="0.01" min="0">
                    </div>
                </div>

                <!-- GeoJSON boundary (populated by map drawing) -->
                <div class="form-group">
                    <label><?= __t('delivery.zone.boundary','Zone Boundary (GeoJSON)') ?>
                        <small class="text-muted"> — <?= __t('delivery.zone.draw_hint','draw on map or paste JSON') ?></small>
                    </label>
                    <textarea id="zoneGeoJson" name="zone_value" class="form-control zone-geojson" rows="3" placeholder='{"type":"Polygon","coordinates":[...]}'></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="zoneActive" name="is_active" value="1" checked>
                        <span><?= __t('common.active','Active') ?></span>
                    </label>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= __t('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="zoneCancelBtn"><?= __t('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Zones workspace: sidebar list + Leaflet map -->
    <div class="zones-workspace">
        <aside class="zones-sidebar">
            <div class="zones-sidebar-header">
                <input type="text" id="zonesSearch" class="form-control" placeholder="<?= __t('common.search','Search') ?>...">
                <select id="zonesTypeFilter" class="form-control">
                    <option value=""><?= __t('common.all_types','All Types') ?></option>
                    <option value="city">City</option><option value="district">District</option>
                    <option value="radius">Radius</option><option value="polygon">Polygon</option>
                </select>
                <select id="zonesActiveFilter" class="form-control">
                    <option value=""><?= __t('common.all','All') ?></option>
                    <option value="1"><?= __t('common.active','Active') ?></option>
                    <option value="0"><?= __t('common.inactive','Inactive') ?></option>
                </select>
                <div class="sidebar-filter-actions">
                    <button id="zonesApplyFilter" class="btn btn-secondary btn-sm"><?= __t('common.apply','Apply') ?></button>
                    <button id="zonesResetFilter" class="btn btn-outline btn-sm"><?= __t('common.reset','Reset') ?></button>
                </div>
            </div>
            <div id="zonesList" class="zones-list">
                <div id="zonesListLoading" class="loading-state"><div class="spinner"></div></div>
                <div id="zonesListItems"></div>
                <div id="zonesListEmpty" class="empty-state" style="display:none"><div class="empty-icon">🗺️</div><p><?= __t('delivery.zone.empty','No zones found') ?></p></div>
            </div>
            <div class="zones-sidebar-footer">
                <div class="pagination-wrapper">
                    <span id="zonesPaginationInfo"></span>
                    <div id="zonesPagination" class="pagination"></div>
                </div>
                <?php if ($canCreate): ?>
                <button id="zonesAddBtn" class="btn btn-primary btn-block"><i class="fas fa-plus"></i> <?= __t('delivery.zone.add','Add Zone') ?></button>
                <?php endif; ?>
            </div>
        </aside>

        <div class="zones-map-panel">
            <div id="zonesMap" class="delivery-map" style="height:540px;width:100%"></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: PROVIDERS
══════════════════════════════════════════ -->
<div id="providersTab" class="ws-panel" style="display:none">
    <div id="providerFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="providerFormTitle"><?= __t('delivery.provider.details','Provider Details') ?></h3>
            <button class="btn btn-sm btn-outline" id="providerCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="providerForm" novalidate>
                <input type="hidden" id="providerId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="required"><?= __t('delivery.provider.type','Type') ?></label>
                        <select id="providerType" name="provider_type" class="form-control" required>
                            <option value="company">Company</option>
                            <option value="entity_driver">Entity Driver</option>
                            <option value="independent_driver">Independent Driver</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.provider.vehicle','Vehicle') ?></label>
                        <select id="providerVehicle" name="vehicle_type" class="form-control">
                            <option value="bike">Bike</option>
                            <option value="car">Car</option>
                            <option value="van">Van</option>
                            <option value="truck">Truck</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.provider.license','License No.') ?></label>
                        <input type="text" id="providerLicense" name="license_number" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label><?= __t('delivery.provider.tenant_user','Tenant User') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="providerTenantUserId" name="tenant_user_id" class="form-control" placeholder="<?= __t('delivery.provider.type_id','Type user ID') ?>..." min="1">
                            <span id="providerTenantUserName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <label><?= __t('delivery.provider.entity','Entity') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="providerEntityId" name="entity_id" class="form-control" placeholder="<?= __t('delivery.provider.type_id','Type entity ID') ?>..." min="1">
                            <span id="providerEntityName" class="provider-name-badge"></span>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="checkbox-label"><input type="checkbox" id="providerOnline" name="is_online" value="1"> <?= __t('delivery.provider.online','Online') ?></label>
                    </div>
                    <div class="form-group col-6">
                        <label class="checkbox-label"><input type="checkbox" id="providerActive" name="is_active" value="1" checked> <?= __t('common.active','Active') ?></label>
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= __t('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="providerCancelBtn"><?= __t('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= __t('common.search','Search') ?></label><input type="text" id="providersSearch" class="form-control"></div>
                <div class="filter-group"><label><?= __t('delivery.provider.type','Type') ?></label>
                    <select id="providersTypeFilter" class="form-control">
                        <option value=""><?= __t('common.all','All') ?></option>
                        <option value="company">Company</option>
                        <option value="entity_driver">Entity Driver</option>
                        <option value="independent_driver">Independent Driver</option>
                    </select>
                </div>
                <div class="filter-group"><label><?= __t('delivery.provider.vehicle','Vehicle') ?></label>
                    <select id="providersVehicleFilter" class="form-control">
                        <option value=""><?= __t('common.all','All') ?></option>
                        <option value="bike">Bike</option><option value="car">Car</option>
                        <option value="van">Van</option><option value="truck">Truck</option>
                    </select>
                </div>
                <div class="filter-group"><label><?= __t('delivery.provider.status','Status') ?></label>
                    <select id="providersActiveFilter" class="form-control">
                        <option value=""><?= __t('common.all','All') ?></option>
                        <option value="1"><?= __t('common.active','Active') ?></option>
                        <option value="0"><?= __t('common.inactive','Inactive') ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="providersApplyFilter" class="btn btn-secondary"><?= __t('common.apply','Apply') ?></button>
                    <button id="providersResetFilter" class="btn btn-outline"><?= __t('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.provider.title','Delivery Providers') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="providersAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= __t('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="providersTableLoading" class="loading-state"><div class="spinner"></div><p><?= __t('common.loading','Loading...') ?></p></div>
            <div id="providersTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= __t('delivery.provider.type','Type') ?></th><th><?= __t('delivery.provider.vehicle','Vehicle') ?></th>
                        <th><?= __t('delivery.provider.online','Online') ?></th><th><?= __t('delivery.provider.rating','Rating') ?></th>
                        <th><?= __t('delivery.provider.deliveries','Deliveries') ?></th><th><?= __t('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="providersTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="providersPaginationInfo"></span><div id="providersPagination" class="pagination"></div></div>
            </div>
            <div id="providersEmptyState" class="empty-state" style="display:none"><div class="empty-icon">🚴</div><h3><?= __t('delivery.provider.empty','No providers found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: ORDERS
══════════════════════════════════════════ -->
<div id="ordersTab" class="ws-panel" style="display:none">
    <div id="orderFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="orderFormTitle"><?= __t('delivery.order.details','Order Details') ?></h3>
            <button class="btn btn-sm btn-outline" id="orderCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="orderForm" novalidate>
                <input type="hidden" id="orderId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="required"><?= __t('delivery.order.order_id','Order ID') ?></label>
                        <input type="number" id="orderOrderId" name="order_id" class="form-control" required min="1">
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.order.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="orderProviderId" name="provider_id" class="form-control" placeholder="<?= __t('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="orderProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-4">
                        <label><?= __t('delivery.order.status','Status') ?></label>
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
                        <label class="required"><?= __t('delivery.order.pickup','Pickup Address ID') ?></label>
                        <input type="number" id="orderPickup" name="pickup_address_id" class="form-control" required min="1">
                    </div>
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.order.dropoff','Dropoff Address ID') ?></label>
                        <input type="number" id="orderDropoff" name="dropoff_address_id" class="form-control" required min="1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <label><?= __t('delivery.order.fee','Delivery Fee') ?></label>
                        <input type="number" id="orderFee" name="delivery_fee" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-3">
                        <label><?= __t('delivery.order.calc_fee','Calculated Fee') ?></label>
                        <input type="number" id="orderCalcFee" name="calculated_fee" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-3">
                        <label><?= __t('delivery.order.payout','Provider Payout') ?></label>
                        <input type="number" id="orderPayout" name="provider_payout" class="form-control" step="0.01" value="0.00" min="0">
                    </div>
                    <div class="form-group col-3">
                        <label><?= __t('delivery.order.zone','Delivery Zone') ?></label>
                        <select id="orderZoneId" name="delivery_zone_id" class="form-control"><option value="">-- <?= __t('common.none','None') ?> --</option></select>
                    </div>
                </div>
                <div id="cancelFields" class="form-row" style="display:none">
                    <div class="form-group col-6">
                        <label><?= __t('delivery.order.cancelled_by','Cancelled By') ?></label>
                        <select id="orderCancelledBy" name="cancelled_by" class="form-control">
                            <option value="">--</option><option value="customer">Customer</option>
                            <option value="provider">Provider</option><option value="admin">Admin</option><option value="system">System</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label><?= __t('delivery.order.cancel_reason','Cancellation Reason') ?></label>
                        <input type="text" id="orderCancelReason" name="cancellation_reason" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= __t('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="orderCancelBtn"><?= __t('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= __t('delivery.order.status','Status') ?></label>
                    <select id="ordersStatusFilter" class="form-control">
                        <option value=""><?= __t('common.all','All') ?></option>
                        <option value="pending">Pending</option><option value="assigned">Assigned</option>
                        <option value="accepted">Accepted</option><option value="picked_up">Picked Up</option>
                        <option value="on_the_way">On The Way</option><option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-group"><label><?= __t('delivery.order.provider','Provider') ?></label>
                    <select id="ordersProviderFilter" class="form-control"><option value=""><?= __t('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= __t('delivery.order.zone','Zone') ?></label>
                    <select id="ordersZoneFilter" class="form-control"><option value=""><?= __t('common.all','All') ?></option></select>
                </div>
                <div class="filter-actions">
                    <button id="ordersApplyFilter" class="btn btn-secondary"><?= __t('common.apply','Apply') ?></button>
                    <button id="ordersResetFilter" class="btn btn-outline"><?= __t('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.order.title','Delivery Orders') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="ordersAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= __t('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="ordersTableLoading" class="loading-state"><div class="spinner"></div><p><?= __t('common.loading','Loading...') ?></p></div>
            <div id="ordersTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= __t('delivery.order.order_id','Order') ?></th><th><?= __t('delivery.order.provider','Provider') ?></th>
                        <th><?= __t('delivery.order.status','Status') ?></th><th><?= __t('delivery.order.fee','Fee') ?></th>
                        <th><?= __t('delivery.order.zone','Zone') ?></th><th><?= __t('common.created','Created') ?></th><th><?= __t('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="ordersTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="ordersPaginationInfo"></span><div id="ordersPagination" class="pagination"></div></div>
            </div>
            <div id="ordersEmptyState" class="empty-state" style="display:none"><div class="empty-icon">📦</div><h3><?= __t('delivery.order.empty','No orders found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: LOCATIONS (Driver Locations)
══════════════════════════════════════════ -->
<div id="locationsTab" class="ws-panel" style="display:none">
    <div id="locationFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.location.update','Update Location') ?></h3>
            <button class="btn btn-sm btn-outline" id="locationCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="locationForm" novalidate>
                <input type="hidden" id="locationId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.location.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="locationProviderId" name="provider_id" class="form-control" required placeholder="<?= __t('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="locationProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <label class="required"><?= __t('delivery.location.lat','Latitude') ?></label>
                        <div class="coord-input-wrap">
                            <input type="text" id="locationLat" name="latitude" class="form-control" required placeholder="24.7136">
                            <button type="button" class="btn btn-sm btn-outline btn-pick-map" data-lat="locationLat" data-lng="locationLng" title="<?= __t('delivery.location.pick_map','Pick on map') ?>" aria-label="<?= __t('delivery.location.pick_map','Pick on map') ?>"><i class="fas fa-map-pin" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline btn-use-gps" data-lat="locationLat" data-lng="locationLng" title="<?= __t('delivery.location.use_gps','Use my location') ?>" aria-label="<?= __t('delivery.location.use_gps','Use my location') ?>"><i class="fas fa-crosshairs" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <label class="required"><?= __t('delivery.location.lng','Longitude') ?></label>
                        <input type="text" id="locationLng" name="longitude" class="form-control" required placeholder="46.6753">
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= __t('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="locationCancelBtn"><?= __t('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= __t('delivery.location.provider','Provider') ?></label>
                    <select id="locationsProviderFilter" class="form-control"><option value=""><?= __t('common.all','All') ?></option></select>
                </div>
                <div class="filter-actions">
                    <button id="locationsApplyFilter" class="btn btn-secondary"><?= __t('common.apply','Apply') ?></button>
                    <button id="locationsResetFilter" class="btn btn-outline"><?= __t('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.location.title','Driver Locations') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="locationsAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= __t('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="locationsTableLoading" class="loading-state"><div class="spinner"></div><p><?= __t('common.loading','Loading...') ?></p></div>
            <div id="locationsTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= __t('delivery.location.provider','Provider') ?></th>
                        <th><?= __t('delivery.location.lat','Lat') ?></th><th><?= __t('delivery.location.lng','Lng') ?></th>
                        <th><?= __t('common.updated','Updated') ?></th><th><?= __t('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="locationsTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="locationsPaginationInfo"></span><div id="locationsPagination" class="pagination"></div></div>
            </div>
            <div id="locationsEmptyState" class="empty-state" style="display:none"><div class="empty-icon">📍</div><h3><?= __t('delivery.location.empty','No locations found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: TRACKING
══════════════════════════════════════════ -->
<div id="trackingTab" class="ws-panel" style="display:none">
    <div id="trackingFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.tracking.log','Tracking Log') ?></h3>
            <button class="btn btn-sm btn-outline" id="trackingCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="trackingForm" novalidate>
                <input type="hidden" id="trackingId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.tracking.order','Delivery Order') ?></label>
                        <select id="trackingOrderId" name="delivery_order_id" class="form-control" required>
                            <option value="">-- <?= __t('common.select','Select') ?> --</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label><?= __t('delivery.tracking.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="trackingProviderId" name="provider_id" class="form-control" placeholder="<?= __t('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="trackingProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.tracking.lat','Latitude') ?></label>
                        <div class="coord-input-wrap">
                            <input type="text" id="trackingLat" name="latitude" class="form-control" required placeholder="24.7136">
                            <button type="button" class="btn btn-sm btn-outline btn-pick-map" data-lat="trackingLat" data-lng="trackingLng" title="<?= __t('delivery.location.pick_map','Pick on map') ?>" aria-label="<?= __t('delivery.location.pick_map','Pick on map') ?>"><i class="fas fa-map-pin" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline btn-use-gps" data-lat="trackingLat" data-lng="trackingLng" title="<?= __t('delivery.location.use_gps','Use my location') ?>" aria-label="<?= __t('delivery.location.use_gps','Use my location') ?>"><i class="fas fa-crosshairs" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.tracking.lng','Longitude') ?></label>
                        <input type="text" id="trackingLng" name="longitude" class="form-control" required placeholder="46.6753">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __t('delivery.tracking.note','Status Note') ?></label>
                    <input type="text" id="trackingNote" name="status_note" class="form-control" maxlength="255">
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= __t('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="trackingCancelBtn"><?= __t('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= __t('delivery.tracking.order','Order') ?></label>
                    <select id="trackingOrderFilter" class="form-control"><option value=""><?= __t('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= __t('delivery.tracking.provider','Provider') ?></label>
                    <select id="trackingProviderFilter" class="form-control"><option value=""><?= __t('common.all','All') ?></option></select>
                </div>
                <div class="filter-actions">
                    <button id="trackingApplyFilter" class="btn btn-secondary"><?= __t('common.apply','Apply') ?></button>
                    <button id="trackingResetFilter" class="btn btn-outline"><?= __t('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.tracking.title','Order Tracking') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="trackingAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= __t('common.add','Add') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="trackingTableLoading" class="loading-state"><div class="spinner"></div><p><?= __t('common.loading','Loading...') ?></p></div>
            <div id="trackingTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th>ID</th><th><?= __t('delivery.tracking.order','Order') ?></th><th><?= __t('delivery.tracking.provider','Provider') ?></th>
                        <th><?= __t('delivery.tracking.lat','Lat') ?></th><th><?= __t('delivery.tracking.lng','Lng') ?></th>
                        <th><?= __t('delivery.tracking.note','Note') ?></th><th><?= __t('common.time','Time') ?></th>
                        <th><?= __t('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="trackingTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="trackingPaginationInfo"></span><div id="trackingPagination" class="pagination"></div></div>
            </div>
            <div id="trackingEmptyState" class="empty-state" style="display:none"><div class="empty-icon">🛣️</div><h3><?= __t('delivery.tracking.empty','No tracking logs') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: PROVIDER ZONES
══════════════════════════════════════════ -->
<div id="provider_zonesTab" class="ws-panel" style="display:none">
    <div id="pzoneFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.pzone.assign','Assign Zone to Provider') ?></h3>
            <button class="btn btn-sm btn-outline" id="pzoneCloseForm" type="button"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="pzoneForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.pzone.provider','Provider') ?></label>
                        <div class="provider-lookup">
                            <input type="number" id="pzoneProviderId" name="provider_id" class="form-control" required placeholder="<?= __t('delivery.provider.type_id','Type provider ID') ?>..." min="1">
                            <span id="pzoneProviderName" class="provider-name-badge"></span>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <label class="required"><?= __t('delivery.pzone.zone','Zone') ?></label>
                        <select id="pzoneZoneId" name="zone_id" class="form-control" required>
                            <option value="">-- <?= __t('common.select','Select') ?> --</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="pzoneActive" name="is_active" value="1" checked>
                        <span><?= __t('common.active','Active') ?></span>
                    </label>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= __t('common.save','Save') ?></button>
                    <button type="button" class="btn btn-outline" id="pzoneCancelBtn"><?= __t('common.cancel','Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label><?= __t('delivery.pzone.provider','Provider') ?></label>
                    <select id="pzonesProviderFilter" class="form-control"><option value=""><?= __t('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= __t('delivery.pzone.zone','Zone') ?></label>
                    <select id="pzonesZoneFilter" class="form-control"><option value=""><?= __t('common.all','All') ?></option></select>
                </div>
                <div class="filter-group"><label><?= __t('common.status','Status') ?></label>
                    <select id="pzonesActiveFilter" class="form-control">
                        <option value=""><?= __t('common.all','All') ?></option>
                        <option value="1"><?= __t('common.active','Active') ?></option>
                        <option value="0"><?= __t('common.inactive','Inactive') ?></option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="pzonesApplyFilter" class="btn btn-secondary"><?= __t('common.apply','Apply') ?></button>
                    <button id="pzonesResetFilter" class="btn btn-outline"><?= __t('common.reset','Reset') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title"><?= __t('delivery.pzone.title','Provider Zones') ?></h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="pzonesAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= __t('delivery.pzone.assign','Assign') ?></button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="pzonesTableLoading" class="loading-state"><div class="spinner"></div><p><?= __t('common.loading','Loading...') ?></p></div>
            <div id="pzonesTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table"><thead><tr>
                        <th><?= __t('delivery.pzone.provider','Provider') ?></th><th><?= __t('delivery.pzone.zone','Zone') ?></th>
                        <th><?= __t('common.active','Active') ?></th><th><?= __t('delivery.pzone.assigned_at','Assigned') ?></th>
                        <th><?= __t('common.actions','Actions') ?></th>
                    </tr></thead><tbody id="pzonesTableBody"></tbody></table>
                </div>
                <div class="pagination-wrapper"><span id="pzonesPaginationInfo"></span><div id="pzonesPagination" class="pagination"></div></div>
            </div>
            <div id="pzonesEmptyState" class="empty-state" style="display:none"><div class="empty-icon">🔗</div><h3><?= __t('delivery.pzone.empty','No assignments found') ?></h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     COORDINATE PICKER MODAL
══════════════════════════════════════════ -->
<div id="coordPickerModal" class="coord-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="coordPickerModalTitle">
    <div class="coord-modal-inner">
        <div class="coord-modal-header">
            <h3 id="coordPickerModalTitle"><?= __t('delivery.map.pick_coords','Pick Location on Map') ?></h3>
            <button type="button" id="coordModalClose" class="btn btn-sm btn-outline"><i class="fas fa-times"></i></button>
        </div>
        <div class="coord-modal-search">
            <input type="text" id="coordSearchInput" class="form-control" placeholder="<?= __t('delivery.map.search_place','Search place...') ?>">
            <button type="button" id="coordSearchBtn" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
        </div>
        <div id="coordPickerMap" style="height:400px;width:100%;"></div>
        <div class="coord-modal-footer">
            <span><?= __t('delivery.map.click_to_select','Click on the map to select a location') ?></span>
            <span id="coordDisplay" class="coord-display"></span>
            <button type="button" id="coordConfirmBtn" class="btn btn-primary" disabled><?= __t('delivery.map.confirm','Confirm') ?></button>
        </div>
    </div>
</div>

<!-- Error Toast -->
<div id="deliveryToast" class="delivery-toast" style="display:none"></div>

</div><!-- /page-container -->

<?php $deliveryJsVer = '3'; ?>
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.TENANT_ID = <?= (int)$tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = '<?= addslashes($csrf) ?>';
window.USER_LANGUAGE = '<?= addslashes($lang) ?>';

window.DELIVERY_CONFIG = {
    lang: '<?= addslashes($lang) ?>',
    dir: '<?= addslashes($dir) ?>',
    tenantId: <?= (int)$tenantId ?>,
    csrfToken: '<?= addslashes($csrf) ?>',
    userId: <?= (int)$userId ?>,
    mapCenter: [24.7136, 46.6753],
    mapZoom: 5,
    canCreate: <?= $canCreate ? 'true' : 'false' ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    canDelete: <?= $canDelete ? 'true' : 'false' ?>,
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

window.PAGE_PERMISSIONS = <?= json_encode(['canCreate'=>$canCreate, 'canEdit'=>$canEdit, 'canDelete'=>$canDelete], JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- delivery.js self-loads Leaflet JS + CSS (same pattern as test_map.php / DeliveryZone.js) -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/delivery.js?v=<?= $deliveryJsVer ?>"></script>
<script>(function(){
    // If delivery.js was already loaded on a prior navigation, Delivery.reinit() resets maps
    // and re-runs init() on the fresh DOM.  On the very first load delivery.js auto-inits.
    if (window.Delivery && typeof window.Delivery.reinit === 'function') {
        window.Delivery.reinit();
        return;
    }
    // delivery.js not yet loaded — it will self-init via its own IIFE.
    // Just watch for load timeout as a safety net.
    var i = 0;
    var iv = setInterval(function(){
        if (window.Delivery) { clearInterval(iv); }
        else if (++i > 120) { clearInterval(iv); console.error('Delivery init timeout'); }
    }, 100);
})();</script>
<?php else: ?>
<script src="/admin/assets/js/pages/delivery.js?v=<?= $deliveryJsVer ?>"></script>
<?php endif; ?>

<?php if (!$isFragment): require_once __DIR__ . '/../includes/footer.php'; endif; ?>