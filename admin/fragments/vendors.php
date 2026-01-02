<?php
/**
 * admin/fragments/vendors.php
 *
 * Theme-integrated Vendors management fragment
 * - Uses bootstrap_admin_ui.php for theme, colors, buttons, settings
 * - Translations support via window.ADMIN_UI.strings
 * - RBAC permissions via window.ADMIN_UI.user.permissions
 * - Theme-aware UI using CSS variables
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Load bootstrap_admin_ui for theme, colors, buttons, etc.
$adminBootstrap = realpath(__DIR__ . '/../../api/bootstrap_admin_ui.php') ?: (__DIR__ . '/../../api/bootstrap_admin_ui.php');
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? null;
if (is_readable($adminBootstrap)) {
    try {
        require_once $adminBootstrap;
    } catch (Throwable $e) {
        // Fallback to defaults if bootstrap fails
    }
}

// Fallback defaults if bootstrap not available
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

// Get language and direction from ADMIN_UI
$lang = strtolower($ADMIN_UI_PAYLOAD['lang'] ?? 'en');
$dir = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';

// Ensure strings exists
if (!isset($ADMIN_UI_PAYLOAD['strings']) || !is_array($ADMIN_UI_PAYLOAD['strings'])) {
    $ADMIN_UI_PAYLOAD['strings'] = [];
}

// Helper function for strings with fallback
function s(string $key, $default = '') {
    global $ADMIN_UI_PAYLOAD;
    $strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
    return isset($strings[$key]) && is_scalar($strings[$key]) ? (string)$strings[$key] : $default;
}

// Safe escape helper
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Safe JSON encode
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
$csrfToken = $ADMIN_UI_PAYLOAD['csrf_token'] ?? $_SESSION['csrf_token'] ?? '';
if (!$csrfToken) {
    $csrfToken = bin2hex(random_bytes(16));
    $_SESSION['csrf_token'] = $csrfToken;
}
?>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<link rel="stylesheet" href="/admin/assets/css/pages/vendors.css">
<script>
// Inject ADMIN_UI payload for client-side theme access
try {
    window.ADMIN_UI = <?php echo $ADMIN_UI_JSON; ?>;
    window.ADMIN_LANG = window.ADMIN_UI.lang || 'en';
    window.ADMIN_DIR = window.ADMIN_UI.direction || 'ltr';
    window.CSRF_TOKEN = window.ADMIN_UI.csrf_token || '';
    window.ADMIN_USER = window.ADMIN_UI.user || {};
    window.CURRENT_USER = window.ADMIN_USER; // Legacy alias
    window.LANG_DIRECTION = window.ADMIN_DIR;
} catch (e) {
    console.error('ADMIN_UI init error', e);
    window.ADMIN_UI = {};
    window.CSRF_TOKEN = '<?php echo h($csrfToken); ?>';
}
</script>

<div id="adminVendors" class="admin-fragment" dir="<?php echo h($dir); ?>">
  <header>
    <h2 id="vendors_title" data-i18n="vendors_title"><?php echo h(s('vendors_title','Vendors')); ?></h2>
    <div><?php echo h($user['username'] ?? 'guest'); ?></div>
  </header>

  <!-- فلاتر البحث المتقدمة -->
  <div class="advanced-filters">
    <h4 data-i18n="advanced_filters"><?php echo h(s('advanced_filters','Advanced Filters')); ?></h4>
    <div>
      <label>
        <?php echo s('status','Status'); ?>
        <select id="filterStatus" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;">
          <option value="">-- <?php echo s('all','All'); ?> --</option>
          <option value="pending">pending</option>
          <option value="approved">approved</option>
          <option value="suspended">suspended</option>
          <option value="rejected">rejected</option>
        </select>
      </label>
      
      <label>
        <?php echo s('verified','Verified'); ?>
        <select id="filterVerified" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;">
          <option value="">-- <?php echo s('all','All'); ?> --</option>
          <option value="1"><?php echo s('yes','Yes'); ?></option>
          <option value="0"><?php echo s('no','No'); ?></option>
        </select>
      </label>
      
      <label>
        <?php echo s('country','Country'); ?>
        <select id="filterCountry" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;">
          <option value=""><?php echo s('loading','Loading...'); ?></option>
        </select>
      </label>
      
      <label>
        <?php echo s('city','City'); ?>
        <input id="filterCity" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;" placeholder="<?php echo s('city_name','City name'); ?>">
      </label>
      
      <label>
        <?php echo s('phone','Phone'); ?>
        <input id="filterPhone" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;" placeholder="<?php echo s('phone_number','Phone number'); ?>">
      </label>
      
      <label>
        <?php echo s('email','Email'); ?>
        <input id="filterEmail" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;" placeholder="<?php echo s('email_address','Email address'); ?>">
      </label>
      
      <div>
        <button id="filterClear" class="btn" type="button" style="width:100%;"><?php echo s('clear_filters','Clear Filters'); ?></button>
      </div>
    </div>
  </div>

  <div class="tools" style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
    <input id="vendorSearch" placeholder="<?php echo s('search_placeholder','Search store, email, slug...'); ?>" style="padding:8px;border:1px solid #e6eef0;border-radius:6px;width:320px;">
    <button id="vendorRefresh" class="btn" type="button"><?php echo s('refresh','Refresh'); ?></button>
    <button id="vendorNewBtn" class="btn primary" type="button"><?php echo s('new_vendor','New Vendor'); ?></button>
    <div style="margin-left:auto;color:#6b7280;"><?php echo s('total_label','Total:'); ?> <span id="vendorsCount">‑</span></div>
  </div>

  <div class="table-wrap" style="margin-bottom:18px;">
    <table id="vendorsTable" style="width:100%;border-collapse:collapse;">
      <thead style="background:#f8fafc;">
        <tr>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">ID</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo s('store_col','Store'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo s('email_col','Email'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo s('type_col','Type'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo s('status_col','Status'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo s('verified_col','Verified'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo s('actions_col','Actions'); ?></th>
        </tr>
      </thead>
      <tbody id="vendorsTbody"><tr><td colspan="7" style="text-align:center;color:#6b7280;padding:18px;"><?php echo s('loading','Loading...'); ?></td></tr></tbody>
    </table>
  </div>

  <!-- Embedded vendor form -->
  <section id="vendorFormSection" class="embedded-form" style="background:#fff;border:1px solid #eef2f7;padding:12px;border-radius:8px;">
    <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <h3 id="vendorFormTitle"><?php echo s('create_edit','Create / Edit Vendor'); ?></h3>
      <div>
        <button id="vendorSaveBtn" class="btn primary" type="button"><?php echo s('save','Save'); ?></button>
        <button id="vendorResetBtn" class="btn" type="button"><?php echo s('reset','Reset'); ?></button>
      </div>
    </header>

    <div id="vendorFormErrors" class="errors" style="display:none;color:#b91c1c;margin-bottom:8px;"></div>

    <form id="vendorForm" enctype="multipart/form-data" autocomplete="off" onsubmit="return false;">
      <input type="hidden" id="vendor_id" name="id" value="0">
      <input type="hidden" id="vendor_user_id" name="user_id" value="<?php echo (int)($user['id'] ?? 0); ?>">
      <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE); ?>">

      <div class="form-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
        <label><?php echo s('store_name_label','Store name'); ?>
          <input id="vendor_store_name" name="store_name" type="text" required>
        </label>

        <label><?php echo s('slug_label','Slug'); ?>
          <input id="vendor_slug" name="slug" type="text">
        </label>

        <label><?php echo s('type_label','Type'); ?>
          <select id="vendor_type" name="vendor_type">
            <option value="product_seller">product_seller</option>
            <option value="service_provider">service_provider</option>
            <option value="both">both</option>
          </select>
        </label>

        <label><?php echo s('store_type_label','Store type'); ?>
          <select id="vendor_store_type" name="store_type">
            <option value="individual">individual</option>
            <option value="company">company</option>
            <option value="brand">brand</option>
          </select>
        </label>

        <label style="display:flex;align-items:center;gap:8px;"><?php echo s('is_branch_label','Is branch'); ?>
          <input id="vendor_is_branch" name="is_branch" type="checkbox" value="1" style="margin-left:8px;">
        </label>

        <div id="parentVendorWrap" style="display:none;grid-column:1 / span 2;">
          <label><?php echo s('parent_vendor_label','Parent vendor'); ?>
            <select id="vendor_parent_id" name="parent_vendor_id">
              <option value=""><?php echo s('select_parent','-- select parent --'); ?></option>
            </select>
          </label>
        </div>

        <label><?php echo s('branch_code_label','Branch code'); ?>
          <input id="vendor_branch_code" name="branch_code" type="text">
        </label>

        <label>
          <?php echo s('inherit_settings_label','Inherit settings'); ?>
          <input id="inherit_settings" name="inherit_settings" type="checkbox" value="1" checked style="margin-left:8px;">
        </label>

        <label>
          <?php echo s('inherit_products_label','Inherit products'); ?>
          <input id="inherit_products" name="inherit_products" type="checkbox" value="1" checked style="margin-left:8px;">
        </label>

        <label>
          <?php echo s('inherit_commission_label','Inherit commission'); ?>
          <input id="inherit_commission" name="inherit_commission" type="checkbox" value="1" checked style="margin-left:8px;">
        </label>

        <label><?php echo s('phone_label','Phone'); ?>
          <input id="vendor_phone" name="phone" type="text" required>
        </label>

        <label><?php echo s('mobile_label','Mobile'); ?>
          <input id="vendor_mobile" name="mobile" type="text">
        </label>

        <label><?php echo s('email_label','Email'); ?>
          <input id="vendor_email" name="email" type="email" required>
        </label>

        <label><?php echo s('website_label','Website'); ?>
          <input id="vendor_website" name="website_url" type="text">
        </label>

        <label><?php echo s('registration_label','Registration number'); ?>
          <input id="vendor_registration_number" name="registration_number" type="text">
        </label>

        <label><?php echo s('tax_label','Tax number'); ?>
          <input id="vendor_tax_number" name="tax_number" type="text">
        </label>

        <label><?php echo s('country_label','Country'); ?>
          <select id="vendor_country" name="country_id" required>
            <option value=""><?php echo s('loading_countries','Loading countries...'); ?></option>
          </select>
        </label>

        <label><?php echo s('city_label','City'); ?>
          <select id="vendor_city" name="city_id">
            <option value=""><?php echo s('select_country_first','Select country first'); ?></option>
          </select>
        </label>

        <label><?php echo s('postal_label','Postal code'); ?>
          <input id="vendor_postal" name="postal_code" type="text">
        </label>

        <label style="grid-column:1 / span 2"><?php echo s('address_label','Address'); ?>
          <textarea id="vendor_address" name="address" rows="2"></textarea>
        </label>

        <label><?php echo s('latlng_label','Latitude / Longitude'); ?>
          <div style="display:flex;gap:8px;">
            <input id="vendor_latitude" name="latitude" type="text" placeholder="latitude" style="flex:1">
            <input id="vendor_longitude" name="longitude" type="text" placeholder="longitude" style="flex:1">
            <button id="btnGetCoords" type="button" class="btn"><?php echo s('get_coords','Get coords'); ?></button>
          </div>
          <small id="geocodeNote" style="color:#6b7280;"></small>
        </label>

        <label><?php echo s('commission_label','Commission rate'); ?>
          <input id="vendor_commission" name="commission_rate" type="text" value="10.00">
        </label>

        <label><?php echo s('radius_label','Service radius (KM)'); ?>
          <input id="vendor_radius" name="service_radius" type="number" value="0">
        </label>

        <label><?php echo s('accepts_booking_label','Accepts online booking'); ?>
          <input id="vendor_accepts_online_booking" name="accepts_online_booking" type="checkbox" value="1" style="margin-left:8px;">
        </label>

        <label><?php echo s('avg_response_label','Average response time (min)'); ?>
          <input id="vendor_average_response_time" name="average_response_time" type="number" value="0">
        </label>

        <label><?php echo s('logo_label','Logo'); ?>
          <input id="vendor_logo" name="logo" type="file" accept="image/*">
        </label>
        <div class="img-preview" id="preview_logo" style="grid-column:1 / span 2;"></div>

        <label><?php echo s('cover_label','Cover'); ?>
          <input id="vendor_cover" name="cover" type="file" accept="image/*">
        </label>
        <div class="img-preview" id="preview_cover" style="grid-column:1 / span 2;"></div>

        <label><?php echo s('banner_label','Banner'); ?>
          <input id="vendor_banner" name="banner" type="file" accept="image/*">
        </label>
        <div class="img-preview" id="preview_banner" style="grid-column:1 / span 2;"></div>

        <!-- Admin-only fields -->
        <div id="adminFields" style="display:<?php echo $isAdmin ? 'block' : 'none'; ?>;grid-column:1 / span 2;padding-top:8px;border-top:1px dashed #eef2f7;">
          <label><?php echo s('status_label','Status'); ?>
            <select id="vendor_status" name="status">
              <option value="pending">pending</option>
              <option value="approved">approved</option>
              <option value="suspended">suspended</option>
              <option value="rejected">rejected</option>
            </select>
          </label>
          <label><?php echo s('is_verified_label','Is verified'); ?>
            <input id="vendor_is_verified" name="is_verified" type="checkbox" value="1">
          </label>
          <label><?php echo s('is_featured_label','Is featured'); ?>
            <input id="vendor_is_featured" name="is_featured" type="checkbox" value="1">
          </label>
        </div>
      </div>

      <hr style="margin:12px 0;">
      <h4><?php echo s('translations_heading','Translations'); ?></h4>
      <div id="vendor_translations_area" style="max-height:260px;overflow:auto;border:1px dashed #e6eef0;padding:8px;border-radius:6px;"></div>
      <div style="margin-top:8px;"><button id="vendorAddLangBtn" type="button" class="btn"><?php echo s('add_language','Add Language'); ?></button></div>
    </form>
  </section>
</div>

<script src="/admin/assets/js/pages/vendors.js" defer></script>