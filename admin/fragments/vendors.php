<?php
declare(strict_types=1);

// Bootstrap Admin UI
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) {}
}

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$user = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];

// Permissions
$isAdmin = ($user['role_id'] ?? 0) === 1 || in_array('admin', $user['permissions'] ?? []);

// API Path
$apiPath = '/api/vendors';
$countriesApi = '/api/countries';
$citiesApi = '/api/cities';

// Load Vendor Languages
$langFile = __DIR__ . '/../../languages/vendors/' . $lang . '.json';
$vendorStrings = is_readable($langFile) ? json_decode(file_get_contents($langFile), true) : [];
$allStrings = array_merge($strings, $vendorStrings);

// Helper
function gs(string $key, array $allStrings): string {
    $keys = explode('.', $key);
    $current = $allStrings;
    foreach ($keys as $k) {
        if (!isset($current[$k])) return $key;
        $current = $current[$k];
    }
    return $current;
}

// CSRF
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);

// Theme Colors
$primaryColor = $theme['colors']['primary'] ?? '#3b82f6';
$secondaryColor = $theme['colors']['secondary'] ?? '#f8fafc';
$textPrimary = $theme['colors']['text_primary'] ?? '#000000';
$textSecondary = $theme['colors']['text_secondary'] ?? '#6b7280';
$borderColor = $theme['colors']['border'] ?? '#e2e8f0';
$errorColor = $theme['colors']['error'] ?? '#dc2626';
$successColor = $theme['colors']['success'] ?? '#10b981';
$warningColor = $theme['colors']['warning'] ?? '#f59e0b';
?>

<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(gs('page_title', $allStrings)) ?></title>
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --secondary: <?= $secondaryColor ?>;
            --text-primary: <?= $textPrimary ?>;
            --text-secondary: <?= $textSecondary ?>;
            --border: <?= $borderColor ?>;
            --error: <?= $errorColor ?>;
            --success: <?= $successColor ?>;
            --warning: <?= $warningColor ?>;
        }
        body { font-family: system-ui, sans-serif; background: var(--secondary); color: var(--text-primary); direction: <?= $direction ?>; }
        .admin-page { padding: 20px; max-width: 1400px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .filters-section { background: var(--secondary); border: 1px solid var(--border); padding: 16px; margin-bottom: 20px; border-radius: 8px; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .table-container { overflow-x: auto; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: 12px; border-bottom: 1px solid var(--border); text-align: left; }
        .form-section { background: var(--secondary); border: 1px solid var(--border); padding: 20px; border-radius: 8px; margin-top: 20px; display: none; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-input, .form-select { width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--secondary); color: var(--text-primary); }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn.primary { background: var(--primary); color: white; }
        .btn.outline { background: transparent; border: 1px solid var(--border); color: var(--text-primary); }
        .btn.danger { background: var(--error); color: white; }
        .btn.small { padding: 4px 8px; font-size: 12px; }
        .notification { position: fixed; top: 20px; right: 20px; background: var(--success); color: white; padding: 12px; border-radius: 8px; z-index: 1000; max-width: 400px; }
        .loading-row { text-align: center; padding: 40px; color: var(--text-secondary); }
        .img-preview img { max-width: 100px; max-height: 100px; border-radius: 4px; }
        .tr-lang-panel { border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px; background: var(--secondary); }
        .pagination-section { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .pagination-controls { display: flex; gap: 8px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid var(--border); border-top: 2px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
    </style>
</head>
<body>

<div id="adminVendors" class="admin-page">
    <div class="page-header">
        <h2><?= htmlspecialchars(gs('page_title', $allStrings)) ?></h2>
        <?php if ($isAdmin): ?>
        <button id="newVendorBtn" class="btn primary">
            <?= htmlspecialchars(gs('buttons.new', $allStrings)) ?>
        </button>
        <?php endif; ?>
    </div>

    <div id="notificationArea"></div>

    <div class="filters-section">
        <div class="search-box" style="display: flex; gap: 8px; margin-bottom: 16px;">
            <input id="searchInput" type="text" placeholder="<?= htmlspecialchars(gs('search_placeholder', $allStrings)) ?>" style="flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 4px;">
            <button id="clearSearchBtn" class="btn">×</button>
        </div>
        <div class="filters-grid">
            <div class="filter-control">
                <label><?= htmlspecialchars(gs('table.type', $allStrings)) ?></label>
                <select id="typeFilter" class="form-select">
                    <option value=""><?= htmlspecialchars(gs('filters.all_types', $allStrings)) ?></option>
                    <option value="product_seller">Product Seller</option>
                    <option value="service_provider">Service Provider</option>
                    <option value="both">Both</option>
                </select>
            </div>
            <div class="filter-control">
                <label><?= htmlspecialchars(gs('table.country', $allStrings)) ?></label>
                <select id="countryFilter" class="form-select">
                    <option value=""><?= htmlspecialchars(gs('filters.all_countries', $allStrings)) ?></option>
                </select>
            </div>
            <div class="filter-control">
                <label><?= htmlspecialchars(gs('table.city', $allStrings)) ?></label>
                <select id="cityFilter" class="form-select">
                    <option value=""><?= htmlspecialchars(gs('filters.all_cities', $allStrings)) ?></option>
                </select>
            </div>
            <div class="filter-control">
                <label><?= htmlspecialchars(gs('table.status', $allStrings)) ?></label>
                <select id="statusFilter" class="form-select">
                    <option value=""><?= htmlspecialchars(gs('filters.all_status', $allStrings)) ?></option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="suspended">Suspended</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="filter-control">
                <label><?= htmlspecialchars(gs('table.verified', $allStrings)) ?></label>
                <select id="verifiedFilter" class="form-select">
                    <option value=""><?= htmlspecialchars(gs('filters.all_verified', $allStrings)) ?></option>
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
        </div>
        <div class="filters-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
            <div id="totalInfo">Loading...</div>
            <div style="display: flex; gap: 8px;">
                <button id="clearFiltersBtn" class="btn outline">
                    <?= htmlspecialchars(gs('buttons.clear_filters', $allStrings)) ?>
                </button>
                <button id="refreshBtn" class="btn primary">
                    <?= htmlspecialchars(gs('buttons.refresh', $allStrings)) ?>
                </button>
            </div>
        </div>
    </div>

    <div class="table-section">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="60"><?= htmlspecialchars(gs('table.id', $allStrings)) ?></th>
                        <th><?= htmlspecialchars(gs('table.store_name', $allStrings)) ?></th>
                        <th><?= htmlspecialchars(gs('table.email', $allStrings)) ?></th>
                        <th width="100"><?= htmlspecialchars(gs('table.phone', $allStrings)) ?></th>
                        <th width="120"><?= htmlspecialchars(gs('table.type', $allStrings)) ?></th>
                        <th width="100"><?= htmlspecialchars(gs('table.country', $allStrings)) ?></th>
                        <th width="100"><?= htmlspecialchars(gs('table.city', $allStrings)) ?></th>
                        <th width="80"><?= htmlspecialchars(gs('table.status', $allStrings)) ?></th>
                        <th width="80"><?= htmlspecialchars(gs('table.verified', $allStrings)) ?></th>
                        <th width="140"><?= htmlspecialchars(gs('table.actions', $allStrings)) ?></th>
                    </tr>
                </thead>
                <tbody id="vendorsTableBody">
                    <tr>
                        <td colspan="10" class="loading-row">
                            <div class="spinner"></div>
                            <div><?= htmlspecialchars(gs('messages.loading', $allStrings)) ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination-section">
        <div id="pageInfo">Loading...</div>
        <div class="pagination-controls" id="paginationControls">
            <!-- Dynamic -->
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div id="vendorForm" class="form-section">
        <h3 id="formTitle">New Vendor</h3>
        <form id="vendorFormEl" enctype="multipart/form-data">
            <input type="hidden" name="id" id="vendorId">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.store_name_label', $allStrings)) ?> *</label>
                    <input name="store_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.slug_label', $allStrings)) ?></label>
                    <input name="slug" class="form-input">
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.vendor_type_label', $allStrings)) ?></label>
                    <select name="vendor_type" class="form-select">
                        <option value="product_seller">Product Seller</option>
                        <option value="service_provider">Service Provider</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.store_type_label', $allStrings)) ?></label>
                    <select name="store_type" class="form-select">
                        <option value="individual">Individual</option>
                        <option value="company">Company</option>
                        <option value="brand">Brand</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.is_branch_label', $allStrings)) ?></label>
                    <select name="is_branch" id="vendor_is_branch" class="form-select">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.branch_code_label', $allStrings)) ?></label>
                    <input name="branch_code" class="form-input">
                </div>
                <div id="parentVendorWrap" style="display: none;" class="form-group">
                    <label><?= htmlspecialchars(gs('form.parent_vendor_label', $allStrings)) ?></label>
                    <select name="parent_vendor_id" id="vendor_parent_id" class="form-select">
                        <option value=""><?= htmlspecialchars(gs('form.select_parent', $allStrings)) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.phone_label', $allStrings)) ?> *</label>
                    <input name="phone" class="form-input" required>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.mobile_label', $allStrings)) ?></label>
                    <input name="mobile" class="form-input">
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.email_label', $allStrings)) ?> *</label>
                    <input name="email" type="email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.website_label', $allStrings)) ?></label>
                    <input name="website_url" class="form-input">
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.registration_label', $allStrings)) ?></label>
                    <input name="registration_number" class="form-input">
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.tax_label', $allStrings)) ?></label>
                    <input name="tax_number" class="form-input">
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.country_label', $allStrings)) ?> *</label>
                    <select name="country_id" id="vendor_country" class="form-select" required>
                        <option value=""><?= htmlspecialchars(gs('form.select_country', $allStrings)) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.city_label', $allStrings)) ?></label>
                    <select name="city_id" id="vendor_city" class="form-select">
                        <option value=""><?= htmlspecialchars(gs('form.select_city', $allStrings)) ?></option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><?= htmlspecialchars(gs('form.address_label', $allStrings)) ?></label>
                    <textarea name="address" rows="2" class="form-input"></textarea>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.postal_label', $allStrings)) ?></label>
                    <input name="postal_code" class="form-input">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><?= htmlspecialchars(gs('form.latlng_label', $allStrings)) ?></label>
                    <div style="display: flex; gap: 8px;">
                        <input name="latitude" placeholder="Latitude" class="form-input">
                        <input name="longitude" placeholder="Longitude" class="form-input">
                        <button type="button" id="getCoordsBtn" class="btn">Get Coords</button>
                    </div>
                    <small style="color: var(--text-secondary);"><?= htmlspecialchars(gs('form.coords_note', $allStrings)) ?></small>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.commission_label', $allStrings)) ?></label>
                    <input name="commission_rate" class="form-input" value="10.00">
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.radius_label', $allStrings)) ?></label>
                    <input name="service_radius" type="number" class="form-input" value="0">
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.accepts_booking_label', $allStrings)) ?></label>
                    <select name="accepts_online_booking" class="form-select">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= htmlspecialchars(gs('form.avg_response_label', $allStrings)) ?></label>
                    <input name="average_response_time" type="number" class="form-input" value="0">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><?= htmlspecialchars(gs('form.logo_label', $allStrings)) ?></label>
                    <input name="logo" type="file" accept="image/*">
                    <div id="logoPreview" class="img-preview"></div>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><?= htmlspecialchars(gs('form.cover_label', $allStrings)) ?></label>
                    <input name="cover" type="file" accept="image/*">
                    <div id="coverPreview" class="img-preview"></div>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><?= htmlspecialchars(gs('form.banner_label', $allStrings)) ?></label>
                    <input name="banner" type="file" accept="image/*">
                    <div id="bannerPreview" class="img-preview"></div>
                </div>
                <div class="form-group" style="grid-column: span 2; border-top: 1px solid var(--border); padding-top: 16px; margin-top: 16px;">
                    <h4><?= htmlspecialchars(gs('messages.translations_heading', $allStrings)) ?></h4>
                    <div id="translationsContainer"></div>
                    <button type="button" id="addLangBtn" class="btn small" style="margin-top: 8px;">
                        <?= htmlspecialchars(gs('messages.add_language', $allStrings)) ?>
                    </button>
                </div>
                <div class="form-group" style="grid-column: span 2; border-top: 1px solid var(--border); padding-top: 16px; margin-top: 16px;">
                    <h4><?= htmlspecialchars(gs('messages.admin_settings', $allStrings)) ?></h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                        <div class="form-group">
                            <label><?= htmlspecialchars(gs('form.status_label', $allStrings)) ?></label>
                            <select name="status" class="form-select">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="suspended">Suspended</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= htmlspecialchars(gs('form.is_verified_label', $allStrings)) ?></label>
                            <select name="is_verified" class="form-select">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= htmlspecialchars(gs('form.is_featured_label', $allStrings)) ?></label>
                            <select name="is_featured" class="form-select">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= htmlspecialchars(gs('form.inherit_settings_label', $allStrings)) ?></label>
                            <select name="inherit_settings" class="form-select">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= htmlspecialchars(gs('form.inherit_products_label', $allStrings)) ?></label>
                            <select name="inherit_products" class="form-select">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= htmlspecialchars(gs('form.inherit_commission_label', $allStrings)) ?></label>
                            <select name="inherit_commission" class="form-select">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 24px; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" id="cancelBtn" class="btn outline">
                    <?= htmlspecialchars(gs('buttons.cancel', $allStrings)) ?>
                </button>
                <button type="submit" class="btn primary">
                    <?= htmlspecialchars(gs('buttons.save', $allStrings)) ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
window.VENDORS_CONFIG = {
    apiUrl: "<?= addslashes($apiPath) ?>",
    countriesApi: "<?= addslashes($countriesApi) ?>",
    citiesApi: "<?= addslashes($citiesApi) ?>",
    csrfToken: "<?= $csrf ?>",
    isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
    lang: "<?= htmlspecialchars($lang) ?>",
    direction: "<?= htmlspecialchars($direction) ?>"
};
window.STRINGS = <?= json_encode($allStrings) ?>;
window.USER_INFO = <?= json_encode($user) ?>;
window.THEME = <?= json_encode($theme) ?>;
</script>
<script src="/admin/assets/js/pages/vendors.js"></script>
</body>
</html>