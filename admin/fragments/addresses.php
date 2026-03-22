<?php
declare(strict_types=1);

/**
 * /admin/fragments/addresses.php
 * Addresses Management — Production v2.0
 *
 * ─ المبادئ ────────────────────────────────────────────────────
 * • لا إعادة حقن :root — header.php هو المصدر الوحيد للـ CSS vars
 * • assetVer() لـ cache-busting صحيح
 * • filters-grid / filter-group / filter-buttons
 * • loading / empty / error states موحّدة
 * • لا iframe mode — الصفحة تُحمَّل دائماً داخل admin layout
 * ─────────────────────────────────────────────────────────────
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

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

// ── Context ──────────────────────────────────────────────────
$user         = admin_user();
$isSuperAdmin = is_super_admin();
$lang         = admin_lang();
$dir          = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf         = admin_csrf();
$tenantId     = admin_tenant_id();

// ── Owner resolution ─────────────────────────────────────────
$tenantMode = false;

if ($isSuperAdmin && !isset($_GET['owner_type']) && !isset($_GET['owner_id'])) {
    $ownerType        = null;
    $ownerId          = null;
    $showAllAddresses = true;
} elseif (isset($_GET['owner_type']) && $_GET['owner_type'] === 'entity' && !isset($_GET['owner_id'])) {
    $ownerType        = 'entity';
    $ownerId          = null;
    $showAllAddresses = false;
    $tenantMode       = true;
} else {
    $ownerType        = $_GET['owner_type'] ?? 'user';
    $ownerId          = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : (int)($user['id'] ?? 1);
    $showAllAddresses = false;
}

// ── Permissions ──────────────────────────────────────────────
$canView          = $isSuperAdmin || can('manage_addresses');
$canCreate        = $isSuperAdmin || can('manage_addresses');
$canEdit          = $isSuperAdmin || can('manage_addresses');
$canDelete        = $isSuperAdmin || can('manage_addresses');
$canEditAllFields = $isSuperAdmin;

if (!$canView) {
    http_response_code(403);
    exit('Access denied');
}

$apiBase = '/api';

// ── Translations ─────────────────────────────────────────────
$_addrStrings  = [];
$_allowedLangs = ['en','ar','fa','he','ur','tr','fr','de','es','hi','zh','pt','ru','it','nl'];
$_safeLang     = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile     = __DIR__ . '/../../languages/Addresses/' . $_safeLang . '.json';

if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_addrStrings = $_json['strings'];
    }
}

function _addr_t(string $key, string $fallback = ''): string
{
    global $_addrStrings;
    $parts = explode('.', $key);
    $val   = $_addrStrings;
    foreach ($parts as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback !== '' ? $fallback : $key;
        }
    }
    return is_string($val) ? $val : ($fallback !== '' ? $fallback : $key);
}

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
      href="/admin/assets/css/pages/addresses.css?v=<?= assetVer('/admin/assets/css/pages/addresses.css') ?>">

<meta data-page="addresses"
      data-i18n-files="/languages/Addresses/<?= rawurlencode($_safeLang) ?>.json">

<div class="page-container" id="addressesPage" dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ═══════════════════════════════════════════
         PAGE HEADER
    ════════════════════════════════════════════ -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="title">
                <?= htmlspecialchars(_addr_t('title', 'Addresses'), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="page-subtitle" data-i18n="subtitle">
                <?= htmlspecialchars(_addr_t('subtitle', 'Manage addresses'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddAddress" class="btn btn-primary" data-i18n="add_address">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <?= htmlspecialchars(_addr_t('add_address', 'Add Address'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         FORM CARD
    ════════════════════════════════════════════ -->
    <div class="card addr-form-card" id="addressFormCard" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="addressFormTitle" data-i18n="add_address">
                <?= htmlspecialchars(_addr_t('add_address', 'Add Address'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <button type="button" id="btnCloseForm" class="icon-btn" aria-label="Close">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="addressForm" novalidate>
                <input type="hidden" name="id"         id="addressId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="tenant_id"  value="<?= $tenantId ?>">

                <?php if ($isSuperAdmin): ?>
                <!-- Super Admin: يختار owner type + id بحرية -->
                <div class="addr-super-notice">
                    <i class="fas fa-crown" aria-hidden="true"></i>
                    <?= htmlspecialchars(_addr_t('super_admin_mode', 'Super Admin Mode — Full Control'), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="ownerTypeSelect" data-i18n="owner_type">
                            <?= htmlspecialchars(_addr_t('owner_type', 'Owner Type'), ENT_QUOTES, 'UTF-8') ?>
                            <span class="required-star">*</span>
                        </label>
                        <select name="owner_type" id="ownerTypeSelect" class="form-control" required>
                            <option value="user"   data-i18n="owner_user">
                                <?= htmlspecialchars(_addr_t('owner_user',   'User'),   ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="entity" data-i18n="owner_entity">
                                <?= htmlspecialchars(_addr_t('owner_entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ownerIdInput" data-i18n="owner_id">
                            <?= htmlspecialchars(_addr_t('owner_id', 'Owner ID'), ENT_QUOTES, 'UTF-8') ?>
                            <span class="required-star">*</span>
                        </label>
                        <input type="number" name="owner_id" id="ownerIdInput"
                               class="form-control" required min="1">
                    </div>
                </div>

                <?php elseif ($tenantMode): ?>
                <!-- Tenant Mode: اختيار entity -->
                <input type="hidden" name="owner_type" value="entity">
                <div class="form-group">
                    <label for="entitySelect" data-i18n="entity">
                        <?= htmlspecialchars(_addr_t('entity', 'Entity / Branch'), ENT_QUOTES, 'UTF-8') ?>
                        <span class="required-star">*</span>
                    </label>
                    <select name="owner_id" id="entitySelect" class="form-control" required>
                        <option value="">
                            <?= htmlspecialchars(_addr_t('select', 'Select...'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>

                <?php else: ?>
                <!-- Normal Mode: owner مُحدَّد مسبقاً -->
                <input type="hidden" name="owner_type" id="ownerTypeHidden"
                       value="<?= htmlspecialchars($ownerType ?? 'user', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="owner_id"   id="ownerIdHidden"
                       value="<?= (int)($ownerId ?? 0) ?>">
                <?php endif; ?>

                <!-- Country / City -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="countrySelect" data-i18n="country">
                            <?= htmlspecialchars(_addr_t('country', 'Country'), ENT_QUOTES, 'UTF-8') ?>
                            <span class="required-star">*</span>
                        </label>
                        <select id="countrySelect" name="country_id" class="form-control" required>
                            <option value="">
                                <?= htmlspecialchars(_addr_t('select', 'Select...'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="citySelect" data-i18n="city">
                            <?= htmlspecialchars(_addr_t('city', 'City'), ENT_QUOTES, 'UTF-8') ?>
                            <span class="required-star">*</span>
                        </label>
                        <select id="citySelect" name="city_id" class="form-control" required disabled>
                            <option value="">
                                <?= htmlspecialchars(_addr_t('select', 'Select...'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Address Lines -->
                <div class="form-group">
                    <label for="addressLine1" data-i18n="address_line1">
                        <?= htmlspecialchars(_addr_t('address_line1', 'Address Line 1'), ENT_QUOTES, 'UTF-8') ?>
                        <span class="required-star">*</span>
                    </label>
                    <input type="text" name="address_line1" id="addressLine1"
                           class="form-control" required autocomplete="address-line1">
                </div>
                <div class="form-group">
                    <label for="addressLine2" data-i18n="address_line2">
                        <?= htmlspecialchars(_addr_t('address_line2', 'Address Line 2'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text" name="address_line2" id="addressLine2"
                           class="form-control" autocomplete="address-line2">
                </div>

                <!-- Postal / Primary -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="postalCode" data-i18n="postal_code">
                            <?= htmlspecialchars(_addr_t('postal_code', 'Postal Code'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <input type="text" name="postal_code" id="postalCode"
                               class="form-control" autocomplete="postal-code">
                    </div>
                    <div class="form-group">
                        <label for="isPrimary" data-i18n="is_primary">
                            <?= htmlspecialchars(_addr_t('is_primary', 'Primary Address'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select name="is_primary" id="isPrimary" class="form-control">
                            <option value="0" data-i18n="no">
                                <?= htmlspecialchars(_addr_t('no', 'No'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <option value="1" data-i18n="yes">
                                <?= htmlspecialchars(_addr_t('yes', 'Yes'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Coordinates -->
                <div class="form-group">
                    <label data-i18n="coordinates">
                        <?= htmlspecialchars(_addr_t('coordinates', 'Coordinates'), ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" id="btnGetLocation" class="btn btn-secondary btn-sm addr-loc-btn">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <?= htmlspecialchars(_addr_t('get_location', 'Get Location'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </label>
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="latitude" id="latitude" class="form-control"
                                   placeholder="<?= htmlspecialchars(_addr_t('latitude', 'Latitude'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group">
                            <input type="text" name="longitude" id="longitude" class="form-control"
                                   placeholder="<?= htmlspecialchars(_addr_t('longitude', 'Longitude'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-i18n="save">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <?= htmlspecialchars(_addr_t('save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" id="btnCancelForm" class="btn btn-secondary" data-i18n="cancel">
                        <?= htmlspecialchars(_addr_t('cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteAddress"
                            class="btn btn-danger addr-delete-btn" style="display:none;" data-i18n="delete">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <?= htmlspecialchars(_addr_t('delete', 'Delete'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         DATA TABLE
    ════════════════════════════════════════════ -->
    <div class="card">
        <div class="card-body">

            <div id="addressesLoading" class="loading-state" style="display:none;">
                <div class="spinner" role="status"></div>
                <p data-i18n="loading">
                    <?= htmlspecialchars(_addr_t('loading', 'Loading...'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <div id="addressesEmpty" class="empty-state" style="display:none;">
                <div class="empty-icon"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></div>
                <h3 data-i18n="no_addresses">
                    <?= htmlspecialchars(_addr_t('no_addresses', 'No Addresses Found'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p data-i18n="add_first">
                    <?= htmlspecialchars(_addr_t('add_first', 'Add your first address'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if ($canCreate): ?>
                <button id="btnAddAddressEmpty" class="btn btn-primary" data-i18n="add_address">
                    <?= htmlspecialchars(_addr_t('add_address', 'Add Address'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <?php endif; ?>
            </div>

            <div id="addressesError" class="error-state" style="display:none;">
                <div class="error-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
                <h3 data-i18n="error.title">
                    <?= htmlspecialchars(_addr_t('error.title', 'Something went wrong'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p id="addressesErrorMsg"></p>
                <button id="btnRetry" class="btn btn-primary" data-i18n="retry">
                    <?= htmlspecialchars(_addr_t('retry', 'Retry'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div id="addressesTableContainer" class="table-responsive" style="display:none;">
                <table class="data-table" id="addressesTable" aria-label="Addresses">
                    <thead>
                        <tr>
                            <th data-i18n="table.id">ID</th>
                            <th data-i18n="country">
                                <?= htmlspecialchars(_addr_t('country',     'Country'),     ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="city">
                                <?= htmlspecialchars(_addr_t('city',        'City'),        ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="address">
                                <?= htmlspecialchars(_addr_t('address',     'Address'),     ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="postal_code">
                                <?= htmlspecialchars(_addr_t('postal_code', 'Postal Code'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="primary">
                                <?= htmlspecialchars(_addr_t('primary',     'Primary'),     ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <th data-i18n="table.actions">
                                <?= htmlspecialchars(_addr_t('actions',     'Actions'),     ENT_QUOTES, 'UTF-8') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="addressesTableBody"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-wrapper">
            <div class="pagination-info" id="paginationInfo" aria-live="polite"></div>
            <div class="pagination" id="pagination" role="navigation" aria-label="Pagination"></div>
        </div>
    </div>

</div><!-- /.page-container -->

<script>
window.ADDRESSES_CONFIG = {
    apiUrl:           <?= json_encode($apiBase . '/addresses',  JSON_UNESCAPED_SLASHES) ?>,
    countriesApi:     <?= json_encode($apiBase . '/countries',  JSON_UNESCAPED_SLASHES) ?>,
    citiesApi:        <?= json_encode($apiBase . '/cities',     JSON_UNESCAPED_SLASHES) ?>,
    entitiesApi:      <?= json_encode($apiBase . '/entities',   JSON_UNESCAPED_SLASHES) ?>,
    tenantId:         <?= (int)$tenantId ?>,
    ownerType:        <?= $ownerType !== null ? json_encode($ownerType) : 'null' ?>,
    ownerId:          <?= $ownerId !== null   ? (int)$ownerId            : 'null' ?>,
    tenantMode:       <?= json_encode($tenantMode) ?>,
    lang:             <?= json_encode($_safeLang) ?>,
    dir:              <?= json_encode($dir) ?>,
    csrf:             <?= json_encode($csrf) ?>,
    isSuperAdmin:     <?= json_encode($isSuperAdmin) ?>,
    canEditAllFields: <?= json_encode($canEditAllFields) ?>,
    showAllAddresses: <?= json_encode($showAllAddresses ?? false) ?>,
    permissions: {
        canCreate: <?= json_encode($canCreate) ?>,
        canEdit:   <?= json_encode($canEdit) ?>,
        canDelete: <?= json_encode($canDelete) ?>
    },
    strings: <?= json_encode($_addrStrings, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="/admin/assets/js/pages/addresses.js?v=<?= assetVer('/admin/assets/js/pages/addresses.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>