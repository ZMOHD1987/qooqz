<?php
/**
 * admin/fragments/vendors.php
 * Theme-integrated Vendors management fragment
 */
declare(strict_types=1);

// Start session if not started
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Load bootstrap_admin_ui
$adminBootstrap = realpath(__DIR__ . '/../../api/bootstrap_admin_ui.php') ?: (__DIR__ . '/../../api/bootstrap_admin_ui.php');
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? null;
if (is_readable($adminBootstrap)) {
    try {
        require_once $adminBootstrap;
    } catch (Throwable $e) {
        // Fallback to defaults
    }
}

// Fallback defaults
if (!isset($ADMIN_UI_PAYLOAD) || !is_array($ADMIN_UI_PAYLOAD)) {
    $ADMIN_UI_PAYLOAD = [
        'lang' => 'en',
        'direction' => 'ltr',
        'strings' => [],
        'user' => ['id' => 0, 'username' => 'guest', 'permissions' => []],
        'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)),
        'theme' => ['colors' => [], 'buttons' => [], 'cards' => [], 'fonts' => [], 'designs' => []]
    ];
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Update CSRF token in payload
$ADMIN_UI_PAYLOAD['csrf_token'] = $_SESSION['csrf_token'];

// Ensure user structure
if (!isset($ADMIN_UI_PAYLOAD['user']) || !is_array($ADMIN_UI_PAYLOAD['user'])) {
    $ADMIN_UI_PAYLOAD['user'] = ['id' => 0, 'username' => 'guest', 'permissions' => []];
}
if (!empty($_SESSION['user_id'])) {
    $sessionUser = [
        'id' => (int)($_SESSION['user_id'] ?? 0),
        'username' => $_SESSION['username'] ?? $ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest',
        'permissions' => $_SESSION['permissions'] ?? $ADMIN_UI_PAYLOAD['user']['permissions'] ?? [],
        'role_id' => $_SESSION['role_id'] ?? null
    ];
    $ADMIN_UI_PAYLOAD['user'] = array_merge($ADMIN_UI_PAYLOAD['user'], $sessionUser);
}

$user = $ADMIN_UI_PAYLOAD['user'];
if (empty($user['permissions'])) {
    if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        $user['permissions'] = $_SESSION['permissions'];
    } elseif (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) {
        $user['permissions'] = array_keys(array_filter($_SESSION['permissions_map']));
    } else {
        $user['permissions'] = [];
    }
}

$isAdmin = isset($user['role_id']) && (int)$user['role_id'] === 1;

// Get language and direction
$lang = strtolower($ADMIN_UI_PAYLOAD['lang'] ?? 'en');
$dir = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';

// Ensure strings exists
if (!isset($ADMIN_UI_PAYLOAD['strings']) || !is_array($ADMIN_UI_PAYLOAD['strings'])) {
    $ADMIN_UI_PAYLOAD['strings'] = [];
}

// Helper functions
function s(string $key, $default = '') {
    global $ADMIN_UI_PAYLOAD;
    $strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
    return isset($strings[$key]) && is_scalar($strings[$key]) ? (string)$strings[$key] : $default;
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function safe_json($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($s === false) {
        array_walk_recursive($v, function (&$item) {
            if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
    return $s;
}

$ADMIN_UI_JSON = safe_json($ADMIN_UI_PAYLOAD);
$GLOBALS['ADMIN_UI'] = $ADMIN_UI_PAYLOAD;

// CSRF Token
$csrfToken = $ADMIN_UI_PAYLOAD['csrf_token'];
?>
<!DOCTYPE html>
<html lang="<?php echo h($lang); ?>" dir="<?php echo h($dir); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(s('vendors_title', 'Vendors Management')); ?></title>
    <style>
        /* Basic styles for the vendors page */
        .admin-fragment {
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .advanced-filters {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .advanced-filters label {
            display: block;
            margin-bottom: 8px;
        }
        .errors {
            background: #fee;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 16px;
        }
        .field-error {
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
        }
        .field-invalid {
            border-color: #dc2626 !important;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-suspended { background: #fee2e2; color: #991b1b; }
        .status-rejected { background: #f3f4f6; color: #374151; }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background: white;
            cursor: pointer;
            font-size: 14px;
        }
        .btn.primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        .btn.danger {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }
        .btn.small {
            padding: 4px 8px;
            font-size: 12px;
        }
    </style>
</head>
<body>
<script>
// Inject ADMIN_UI payload
try {
    window.ADMIN_UI = <?php echo $ADMIN_UI_JSON; ?>;
    window.ADMIN_LANG = window.ADMIN_UI.lang || 'en';
    window.ADMIN_DIR = window.ADMIN_UI.direction || 'ltr';
    window.CSRF_TOKEN = window.ADMIN_UI.csrf_token || '<?php echo h($csrfToken); ?>';
    window.ADMIN_USER = window.ADMIN_UI.user || {};
    window.CURRENT_USER = window.ADMIN_USER;
    window.LANG_DIRECTION = window.ADMIN_DIR;
    console.log('CSRF Token loaded:', window.CSRF_TOKEN ? 'Yes' : 'No');
} catch (e) {
    console.error('ADMIN_UI init error', e);
    window.ADMIN_UI = {};
    window.CSRF_TOKEN = '<?php echo h($csrfToken); ?>';
}
</script>

<div id="adminVendors" class="admin-fragment" dir="<?php echo h($dir); ?>">
  <header style="margin-bottom: 20px;">
    <h2 id="vendors_title"><?php echo h(s('vendors_title','Vendors Management')); ?></h2>
    <div style="color: #6b7280;"><?php echo h($user['username'] ?? 'guest'); ?></div>
  </header>

  <!-- Advanced Filters -->
  <div class="advanced-filters">
    <h4 style="margin-top: 0; margin-bottom: 12px;"><?php echo h(s('advanced_filters','Advanced Filters')); ?></h4>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;">
      <label>
        <span style="display: block; margin-bottom: 4px; font-size: 14px;"><?php echo s('status','Status'); ?></span>
        <select id="filterStatus" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;font-size:14px;">
          <option value="">-- <?php echo s('all','All'); ?> --</option>
          <option value="pending">pending</option>
          <option value="approved">approved</option>
          <option value="suspended">suspended</option>
          <option value="rejected">rejected</option>
        </select>
      </label>
      
      <label>
        <span style="display: block; margin-bottom: 4px; font-size: 14px;"><?php echo s('verified','Verified'); ?></span>
        <select id="filterVerified" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;font-size:14px;">
          <option value="">-- <?php echo s('all','All'); ?> --</option>
          <option value="1"><?php echo s('yes','Yes'); ?></option>
          <option value="0"><?php echo s('no','No'); ?></option>
        </select>
      </label>
      
      <label>
        <span style="display: block; margin-bottom: 4px; font-size: 14px;"><?php echo s('country','Country'); ?></span>
        <select id="filterCountry" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;font-size:14px;">
          <option value=""><?php echo s('loading','Loading...'); ?></option>
        </select>
      </label>
      
      <label>
        <span style="display: block; margin-bottom: 4px; font-size: 14px;"><?php echo s('city','City'); ?></span>
        <input id="filterCity" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;font-size:14px;" placeholder="<?php echo s('city_name','City name'); ?>">
      </label>
      
      <label>
        <span style="display: block; margin-bottom: 4px; font-size: 14px;"><?php echo s('phone','Phone'); ?></span>
        <input id="filterPhone" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;font-size:14px;" placeholder="<?php echo s('phone_number','Phone number'); ?>">
      </label>
      
      <label>
        <span style="display: block; margin-bottom: 4px; font-size: 14px;"><?php echo s('email','Email'); ?></span>
        <input id="filterEmail" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;font-size:14px;" placeholder="<?php echo s('email_address','Email address'); ?>">
      </label>
      
      <div style="grid-column: 1 / -1;">
        <button id="filterClear" class="btn" type="button" style="width:100%;"><?php echo s('clear_filters','Clear Filters'); ?></button>
      </div>
    </div>
  </div>

  <!-- Tools -->
  <div class="tools" style="display:flex;gap:8px;align-items:center;margin-bottom:16px;">
    <input id="vendorSearch" placeholder="<?php echo s('search_placeholder','Search store, email, slug...'); ?>" style="padding:8px 12px;border:1px solid #e6eef0;border-radius:6px;width:320px;font-size:14px;">
    <button id="vendorRefresh" class="btn" type="button"><?php echo s('refresh','Refresh'); ?></button>
    <button id="vendorNewBtn" class="btn primary" type="button"><?php echo s('new_vendor','New Vendor'); ?></button>
    <div style="margin-left:auto;color:#6b7280;font-size:14px;"><?php echo s('total_label','Total:'); ?> <span id="vendorsCount">‑</span></div>
  </div>

  <!-- Vendors Table -->
  <div class="table-wrap" style="margin-bottom:24px;overflow-x:auto;">
    <table id="vendorsTable" style="width:100%;border-collapse:collapse;font-size:14px;">
      <thead style="background:#f8fafc;">
        <tr>
          <th style="padding:12px;border-bottom:1px solid #eef2f7;text-align:left;">ID</th>
          <th style="padding:12px;border-bottom:1px solid #eef2f7;text-align:left;"><?php echo s('store_col','Store'); ?></th>
          <th style="padding:12px;border-bottom:1px solid #eef2f7;text-align:left;"><?php echo s('email_col','Email'); ?></th>
          <th style="padding:12px;border-bottom:1px solid #eef2f7;text-align:left;"><?php echo s('type_col','Type'); ?></th>
          <th style="padding:12px;border-bottom:1px solid #eef2f7;text-align:left;"><?php echo s('status_col','Status'); ?></th>
          <th style="padding:12px;border-bottom:1px solid #eef2f7;text-align:left;"><?php echo s('verified_col','Verified'); ?></th>
          <th style="padding:12px;border-bottom:1px solid #eef2f7;text-align:left;"><?php echo s('actions_col','Actions'); ?></th>
        </tr>
      </thead>
      <tbody id="vendorsTbody"><tr><td colspan="7" style="text-align:center;color:#6b7280;padding:40px;"><?php echo s('loading','Loading...'); ?></td></tr></tbody>
    </table>
  </div>

  <!-- Vendor Form -->
  <section id="vendorFormSection" class="embedded-form" style="background:#fff;border:1px solid #eef2f7;padding:20px;border-radius:8px;margin-top:20px;">
    <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 id="vendorFormTitle" style="margin:0;font-size:18px;"><?php echo s('create_edit','Create / Edit Vendor'); ?></h3>
      <div style="display:flex;gap:8px;">
        <button id="vendorSaveBtn" class="btn primary" type="button"><?php echo s('save','Save'); ?></button>
        <button id="vendorResetBtn" class="btn" type="button"><?php echo s('reset','Reset'); ?></button>
      </div>
    </header>

    <div id="vendorFormErrors" class="errors" style="display:none;"></div>

    <form id="vendorForm" enctype="multipart/form-data" autocomplete="off" onsubmit="return false;">
      <input type="hidden" id="vendor_id" name="id" value="0">
      <input type="hidden" id="vendor_user_id" name="user_id" value="<?php echo (int)($user['id'] ?? 0); ?>">
      <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo h($csrfToken); ?>">

      <div class="form-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
        <!-- Store Name -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('store_name_label','Store name'); ?> *</span>
          <input id="vendor_store_name" name="store_name" type="text" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Slug -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('slug_label','Slug'); ?></span>
          <input id="vendor_slug" name="slug" type="text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Vendor Type -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('type_label','Type'); ?></span>
          <select id="vendor_type" name="vendor_type" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
            <option value="product_seller">product_seller</option>
            <option value="service_provider">service_provider</option>
            <option value="both">both</option>
          </select>
        </label>

        <!-- Store Type -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('store_type_label','Store type'); ?></span>
          <select id="vendor_store_type" name="store_type" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
            <option value="individual">individual</option>
            <option value="company">company</option>
            <option value="brand">brand</option>
          </select>
        </label>

        <!-- Is Branch -->
        <label style="margin-bottom:12px;display:flex;align-items:center;gap:8px;">
          <input id="vendor_is_branch" name="is_branch" type="checkbox" value="1" style="width:18px;height:18px;">
          <span style="font-weight:500;"><?php echo s('is_branch_label','Is branch'); ?></span>
        </label>

        <!-- Branch Code -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('branch_code_label','Branch code'); ?></span>
          <input id="vendor_branch_code" name="branch_code" type="text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Parent Vendor -->
        <div id="parentVendorWrap" style="display:none;grid-column:1 / span 2;margin-bottom:12px;">
          <label style="margin-bottom:12px;">
            <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('parent_vendor_label','Parent vendor'); ?></span>
            <select id="vendor_parent_id" name="parent_vendor_id" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
              <option value=""><?php echo s('select_parent','-- select parent --'); ?></option>
            </select>
          </label>
        </div>

        <!-- Inherit Settings -->
        <div style="grid-column:1 / span 2;display:flex;gap:24px;margin-bottom:12px;">
          <label style="display:flex;align-items:center;gap:8px;">
            <input id="inherit_settings" name="inherit_settings" type="checkbox" value="1" checked style="width:18px;height:18px;">
            <span><?php echo s('inherit_settings_label','Inherit settings'); ?></span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;">
            <input id="inherit_products" name="inherit_products" type="checkbox" value="1" checked style="width:18px;height:18px;">
            <span><?php echo s('inherit_products_label','Inherit products'); ?></span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;">
            <input id="inherit_commission" name="inherit_commission" type="checkbox" value="1" checked style="width:18px;height:18px;">
            <span><?php echo s('inherit_commission_label','Inherit commission'); ?></span>
          </label>
        </div>

        <!-- Phone -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('phone_label','Phone'); ?> *</span>
          <input id="vendor_phone" name="phone" type="text" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Mobile -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('mobile_label','Mobile'); ?></span>
          <input id="vendor_mobile" name="mobile" type="text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Email -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('email_label','Email'); ?> *</span>
          <input id="vendor_email" name="email" type="email" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Website -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('website_label','Website'); ?></span>
          <input id="vendor_website" name="website_url" type="text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Registration Number -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('registration_label','Registration number'); ?></span>
          <input id="vendor_registration_number" name="registration_number" type="text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Tax Number -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('tax_label','Tax number'); ?></span>
          <input id="vendor_tax_number" name="tax_number" type="text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Country -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('country_label','Country'); ?> *</span>
          <select id="vendor_country" name="country_id" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
            <option value=""><?php echo s('loading_countries','Loading countries...'); ?></option>
          </select>
        </label>

        <!-- City -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('city_label','City'); ?></span>
          <select id="vendor_city" name="city_id" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
            <option value=""><?php echo s('select_country_first','Select country first'); ?></option>
          </select>
        </label>

        <!-- Postal Code -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('postal_label','Postal code'); ?></span>
          <input id="vendor_postal" name="postal_code" type="text" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Address -->
        <label style="grid-column:1 / span 2; margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('address_label','Address'); ?></span>
          <textarea id="vendor_address" name="address" rows="2" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;resize:vertical;"></textarea>
        </label>

        <!-- Coordinates -->
        <label style="grid-column:1 / span 2; margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('latlng_label','Latitude / Longitude'); ?></span>
          <div style="display:flex;gap:8px;">
            <input id="vendor_latitude" name="latitude" type="text" placeholder="latitude" style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
            <input id="vendor_longitude" name="longitude" type="text" placeholder="longitude" style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
            <button id="btnGetCoords" type="button" class="btn" style="white-space:nowrap;"><?php echo s('get_coords','Get coordinates'); ?></button>
          </div>
          <small style="color:#6b7280;display:block;margin-top:4px;"><?php echo s('coords_note','Optional: Get current location or enter manually'); ?></small>
        </label>

        <!-- Commission -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('commission_label','Commission rate'); ?></span>
          <input id="vendor_commission" name="commission_rate" type="text" value="10.00" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Service Radius -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('radius_label','Service radius (KM)'); ?></span>
          <input id="vendor_radius" name="service_radius" type="number" value="0" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Online Booking -->
        <label style="margin-bottom:12px;display:flex;align-items:center;gap:8px;">
          <input id="vendor_accepts_online_booking" name="accepts_online_booking" type="checkbox" value="1" style="width:18px;height:18px;">
          <span style="font-weight:500;"><?php echo s('accepts_booking_label','Accepts online booking'); ?></span>
        </label>

        <!-- Response Time -->
        <label style="margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('avg_response_label','Average response time (min)'); ?></span>
          <input id="vendor_average_response_time" name="average_response_time" type="number" value="0" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
        </label>

        <!-- Logo -->
        <label style="grid-column:1 / span 2; margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('logo_label','Logo'); ?></span>
          <input id="vendor_logo" name="logo" type="file" accept="image/*" style="width:100%;padding:8px 0;">
          <div class="img-preview" id="preview_logo" style="margin-top:8px;"></div>
        </label>

        <!-- Cover -->
        <label style="grid-column:1 / span 2; margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('cover_label','Cover'); ?></span>
          <input id="vendor_cover" name="cover" type="file" accept="image/*" style="width:100%;padding:8px 0;">
          <div class="img-preview" id="preview_cover" style="margin-top:8px;"></div>
        </label>

        <!-- Banner -->
        <label style="grid-column:1 / span 2; margin-bottom:12px;">
          <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('banner_label','Banner'); ?></span>
          <input id="vendor_banner" name="banner" type="file" accept="image/*" style="width:100%;padding:8px 0;">
          <div class="img-preview" id="preview_banner" style="margin-top:8px;"></div>
        </label>

        <!-- Admin-only fields -->
        <?php if ($isAdmin): ?>
        <div id="adminFields" style="grid-column:1 / span 2; padding-top:16px; border-top:1px dashed #eef2f7; margin-top:12px;">
          <h4 style="margin:0 0 12px 0;"><?php echo s('admin_settings','Admin Settings'); ?></h4>
          <div style="display:flex;gap:24px;flex-wrap:wrap;">
            <label style="margin-bottom:12px;">
              <span style="display:block;margin-bottom:4px;font-weight:500;"><?php echo s('status_label','Status'); ?></span>
              <select id="vendor_status" name="status" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
                <option value="pending">pending</option>
                <option value="approved">approved</option>
                <option value="suspended">suspended</option>
                <option value="rejected">rejected</option>
              </select>
            </label>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
              <input id="vendor_is_verified" name="is_verified" type="checkbox" value="1" style="width:18px;height:18px;">
              <span style="font-weight:500;"><?php echo s('is_verified_label','Is verified'); ?></span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
              <input id="vendor_is_featured" name="is_featured" type="checkbox" value="1" style="width:18px;height:18px;">
              <span style="font-weight:500;"><?php echo s('is_featured_label','Is featured'); ?></span>
            </label>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <hr style="margin:24px 0;border:none;border-top:1px solid #eef2f7;">
      
      <!-- Translations -->
      <h4 style="margin:0 0 12px 0;"><?php echo s('translations_heading','Translations'); ?></h4>
      <div id="vendor_translations_area" style="max-height:260px;overflow:auto;border:1px dashed #e6eef0;padding:12px;border-radius:6px;margin-bottom:12px;"></div>
      <div style="margin-top:8px;">
        <button id="vendorAddLangBtn" type="button" class="btn"><?php echo s('add_language','Add Language'); ?></button>
      </div>
    </form>
  </section>
</div>

<script src="/admin/assets/js/pages/vendors.js" defer></script>
</body>
</html>
