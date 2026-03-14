<?php
declare(strict_types=1);

/**
 * /admin/fragments/addresses.php
 * Production-ready Addresses Management
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
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
// USER CONTEXT
// ════════════════════════════════════════════════════════════
$user      = admin_user();
$isSuperAdmin = function_exists('is_super_admin') && is_super_admin();
$lang      = $_GET['lang'] ?? (function_exists('admin_lang') ? admin_lang() : 'ar');
$dir       = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf      = function_exists('admin_csrf') ? admin_csrf() : bin2hex(random_bytes(16));
$tenantId  = (int)($_GET['tenant_id'] ?? (function_exists('admin_tenant_id') ? admin_tenant_id() : 1));

// owner context with fallback to current user (unless super admin viewing all)
// Super Admin can view all addresses without owner filter, or filter by specific owner
if ($isSuperAdmin && !isset($_GET['owner_type']) && !isset($_GET['owner_id'])) {
    // Super Admin viewing all addresses
    $ownerType = null;
    $ownerId   = null;
    $showAllAddresses = true;
} else {
    // Normal user or Super Admin filtering by owner
    $ownerType = $_GET['owner_type'] ?? 'user';
    $ownerId   = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : (int)($user['id'] ?? 1);
    $showAllAddresses = false;
}

// ════════════════════════════════════════════════════════════
// PERMISSIONS
// ════════════════════════════════════════════════════════════
$canView   = $isSuperAdmin || (function_exists('can') && can('manage_addresses'));
$canCreate = $isSuperAdmin || (function_exists('can') && can('manage_addresses'));
$canEdit   = $isSuperAdmin || (function_exists('can') && can('manage_addresses'));
$canDelete = $isSuperAdmin || (function_exists('can') && can('manage_addresses'));
$canEditAllFields = $isSuperAdmin; // Super Admin can edit owner_type, owner_id, etc.

if (!$canView) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER
// ════════════════════════════════════════════════════════════
$_addrStrings = [];
$_allowedLangs = ['en', 'ar', 'fa', 'he', 'ur', 'tr', 'fr', 'de', 'es'];
$_safeLang = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile = __DIR__ . '/../../languages/Addresses/' . $_safeLang . '.json';
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_addrStrings = $_json['strings'];
    }
}

if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        global $_addrStrings;
        $keys = explode('.', $key);
        $val = $_addrStrings;
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

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

?>
<?php if ($isFragment): ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/admin/assets/css/pages/addresses.css?v=<?= time() ?>">
</head>
<body dir="<?= htmlspecialchars($dir) ?>" style="margin:0;padding:0;">
<?php endif; ?>

<meta data-page="addresses">

<div class="page-container" id="addressesPage" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><?= __t('title', 'Addresses') ?></h1>
            <p><?= __t('subtitle', 'Manage addresses') ?></p>
        </div>

        <?php if ($canCreate): ?>
        <button id="btnAddAddress" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <?= __t('add_address', 'Add Address') ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <div class="card form-card" id="addressFormCard" style="display:none">
        <div class="card-header">
            <h3 id="addressFormTitle"><?= __t('add_address', 'Add Address') ?></h3>
            <button type="button" id="btnCloseForm">&times;</button>
        </div>

        <div class="card-body">
            <form id="addressForm" novalidate>

                <input type="hidden" name="id" id="addressId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id" value="<?= $tenantId ?>">
                <?php if (!$canEditAllFields): ?>
                <input type="hidden" name="owner_type" id="ownerTypeHidden" value="<?= htmlspecialchars($ownerType ?? 'user') ?>">
                <input type="hidden" name="owner_id" id="ownerIdHidden" value="<?= $ownerId ?? '' ?>">
                <?php else: ?>
                <!-- Super Admin Fields -->
                <div class="alert-info" style="padding: 12px; background: #dbeafe; border: 1px solid #3b82f6; border-radius: 6px; margin-bottom: 16px;">
                    <i class="fas fa-crown"></i> <?= __t('super_admin_mode', 'Super Admin Mode - Full Control') ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= __t('owner_type', 'Owner Type') ?> <span class="required">*</span></label>
                        <select name="owner_type" id="ownerTypeSelect" class="form-control" required>
                            <option value="user"><?= __t('owner_user', 'User') ?></option>
                            <option value="entity"><?= __t('owner_entity', 'Entity') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?= __t('owner_id', 'Owner ID') ?> <span class="required">*</span></label>
                        <input type="number" name="owner_id" id="ownerIdInput" class="form-control" required min="1">
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= __t('country', 'Country') ?> <span class="required">*</span></label>
                        <select id="countrySelect" name="country_id" required>
                            <option value=""><?= __t('select', 'Select...') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?= __t('city', 'City') ?> <span class="required">*</span></label>
                        <select id="citySelect" name="city_id" required disabled>
                            <option value=""><?= __t('select', 'Select...') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label><?= __t('address_line1', 'Address Line 1') ?> <span class="required">*</span></label>
                    <input type="text" name="address_line1" class="form-control" required>
                </div>

                <div class="form-group">
                    <label><?= __t('address_line2', 'Address Line 2') ?></label>
                    <input type="text" name="address_line2" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= __t('postal_code', 'Postal Code') ?></label>
                        <input type="text" name="postal_code" class="form-control">
                    </div>

                    <div class="form-group">
                        <label><?= __t('is_primary', 'Primary Address') ?></label>
                        <select name="is_primary" class="form-control">
                            <option value="0"><?= __t('no', 'No') ?></option>
                            <option value="1"><?= __t('yes', 'Yes') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <?= __t('coordinates', 'Coordinates') ?>
                        <button type="button" id="btnGetLocation" class="btn btn-sm btn-outline" title="<?= __t('get_my_location', 'Get My Location') ?>">
                            <i class="fas fa-map-marker-alt"></i> <?= __t('get_location', 'Get Location') ?>
                        </button>
                    </label>
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="latitude" id="latitude" class="form-control" placeholder="<?= __t('latitude', 'Latitude') ?>" step="any">
                        </div>
                        <div class="form-group">
                            <input type="text" name="longitude" id="longitude" class="form-control" placeholder="<?= __t('longitude', 'Longitude') ?>" step="any">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= __t('save', 'Save') ?>
                    </button>

                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteAddress" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i> <?= __t('delete', 'Delete') ?>
                    </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <table class="data-table" id="addressesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= __t('country', 'Country') ?></th>
                        <th><?= __t('city', 'City') ?></th>
                        <th><?= __t('address', 'Address') ?></th>
                        <th><?= __t('postal_code', 'Postal Code') ?></th>
                        <th><?= __t('primary', 'Primary') ?></th>
                        <th><?= __t('actions', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" style="text-align:center"><?= __t('loading', 'Loading...') ?></td></tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>

</div>

<script>
window.ADDRESSES_CONFIG = {
    apiUrl: '<?= $apiBase ?>/addresses',
    countriesApi: '<?= $apiBase ?>/countries',
    citiesApi: '<?= $apiBase ?>/cities',
    tenantId: <?= $tenantId ?>,
    ownerType: <?= $ownerType !== null ? "'".addslashes($ownerType)."'" : 'null' ?>,
    ownerId: <?= $ownerId ?? 'null' ?>,
    lang: '<?= addslashes($lang) ?>',
    csrf: '<?= addslashes($csrf) ?>',
    isSuperAdmin: <?= json_encode($isSuperAdmin) ?>,
    canEditAllFields: <?= json_encode($canEditAllFields) ?>,
    showAllAddresses: <?= json_encode($showAllAddresses ?? false) ?>,
    permissions: {
        canCreate: <?= json_encode($canCreate) ?>,
        canEdit: <?= json_encode($canEdit) ?>,
        canDelete: <?= json_encode($canDelete) ?>
    },
    strings: <?= json_encode($_addrStrings, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/addresses.js?v=<?= time() ?>"></script>
</body>
</html>
<?php else: ?>
<script src="/admin/assets/js/pages/addresses.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}