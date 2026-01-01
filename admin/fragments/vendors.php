<?php
/**
 * admin/fragments/vendors.php
 *
 * Admin fragment for Vendors management with focus on:
 *  - Translations support (vendor_translations)
 *  - UI language strings loaded from /languages/admin/*.json (configurable via init.php)
 *  - Text direction (ltr/rtl) according to selected admin language (and language metadata)
 *
 * Expectations:
 *  - api/vendors.php provides endpoints used by admin/assets/js/pages/vendors.js
 *  - Language files are JSON structured like:
 *      {
 *        "name": "Arabic",
 *        "direction": "rtl",           // optional, 'rtl' or 'ltr'
 *        "strings": {
 *          "vendors_title": "المتاجر",
 *          ...
 *        }
 *      }
 *
 *  - init.php may define $langBase (relative path from document root) — default '/languages/admin'
 *
 * Security / notes:
 *  - This fragment exposes only non-sensitive session info (CURRENT_USER minimal).
 *  - Remove or reduce any debug logging you add later.
 */
echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">';
if (session_status() === PHP_SESSION_NONE) session_start();

// Optional site init that may define $langBase or other admin settings
$initPath = __DIR__ . '/includes/init.php';
if (is_readable($initPath)) {
    require_once $initPath;
}
// default language folder (relative to document root)
if (empty($langBase)) $langBase = '/languages/admin';

// Normalize user from session or auth helper
$user = [];
if (is_readable(__DIR__ . '/../api/helpers/auth_helper.php')) {
    require_once __DIR__ . '/../api/helpers/auth_helper.php';
    if (function_exists('start_session_safe')) start_session_safe();
    if (function_exists('get_authenticated_user_with_permissions')) {
        $user = get_authenticated_user_with_permissions();
    } else {
        $user = $_SESSION['user'] ?? [];
    }
} else {
    if ((empty($_SESSION['user']) || !is_array($_SESSION['user'])) && !empty($_SESSION['user_id'])) {
        $_SESSION['user'] = [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null,
            'preferred_language' => $_SESSION['preferred_language'] ?? null,
            'html_direction' => $_SESSION['html_direction'] ?? null,
        ];
    }
    $user = $_SESSION['user'] ?? [];
    if (empty($user['permissions'])) {
        if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) $user['permissions'] = $_SESSION['permissions'];
        elseif (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) $user['permissions'] = array_keys(array_filter($_SESSION['permissions_map']));
        else $user['permissions'] = [];
    }
}

$isAdmin = isset($user['role_id']) && (int)$user['role_id'] === 1;

// Compute available languages by scanning $langBase
$languages_for_js = [];
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$langDirPath = $docRoot . rtrim($langBase, '/\\');
if (is_dir($langDirPath)) {
    foreach (glob($langDirPath . '/*.json') as $file) {
        $code = pathinfo($file, PATHINFO_FILENAME);
        $json = @file_get_contents($file);
        $data = @json_decode($json, true);
        if (!is_array($data)) continue;
        $name = $data['name'] ?? strtoupper($code);
        $direction = isset($data['direction']) ? strtolower($data['direction']) : null;
        $strings = $data['strings'] ?? [];
        $languages_for_js[] = [
            'code' => $code,
            'name' => $name,
            'direction' => $direction,
            'strings' => $strings,
        ];
    }
}
if (empty($languages_for_js)) {
    // fallback minimal English
    $languages_for_js[] = ['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'strings' => []];
}

// Determine preferred admin language
$preferredLang = $user['preferred_language'] ?? ($languages_for_js[0]['code'] ?? 'en');

// Resolve language meta (strings, direction) for preferredLang
function find_lang_meta(array $langs, string $code) {
    foreach ($langs as $l) if (($l['code'] ?? '') === $code) return $l;
    return $langs[0] ?? null;
}
$langMeta = find_lang_meta($languages_for_js, $preferredLang);
$langDirection = $langMeta['direction'] ?? null;
// If direction not defined, infer RTL for common RTL codes
if (empty($langDirection)) {
    $rtlCodes = ['ar','he','fa','ur','ps','dv'];
    $langDirection = in_array(strtolower($preferredLang), $rtlCodes, true) ? 'rtl' : 'ltr';
}

// helper to get a UI string key with fallback
function ui_str(array $langs, $preferred, $key, $fallback = '') {
    $meta = find_lang_meta($langs, $preferred);
    if ($meta && !empty($meta['strings'][$key])) return $meta['strings'][$key];
    // try any language that has the key
    foreach ($langs as $l) {
        if (!empty($l['strings'][$key])) return $l['strings'][$key];
    }
    return $fallback;
}

// Prepare minimal strings used in this fragment
$S = function($k, $d='') use ($languages_for_js, $preferredLang) { return htmlspecialchars(ui_str($languages_for_js, $preferredLang, $k, $d), ENT_QUOTES | ENT_SUBSTITUTE); };

// Prepare output-safe JSON blobs
$availableLangsJson = json_encode($languages_for_js, JSON_UNESCAPED_UNICODE);
$currentUserJson = json_encode($user ?? [], JSON_UNESCAPED_UNICODE);
$preferredLangEscaped = htmlspecialchars($preferredLang, ENT_QUOTES | ENT_SUBSTITUTE);
$csrfToken = '';
if (function_exists('auth_get_csrf_token')) {
    try { $csrfToken = auth_get_csrf_token(); } catch (Throwable $e) { $csrfToken = $_SESSION['csrf_token'] ?? ''; }
} else {
    if (empty($_SESSION['csrf_token'])) {
        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
    }
    $csrfToken = $_SESSION['csrf_token'];
}

// HTML direction attribute for container
$dirAttr = ($langDirection === 'rtl') ? 'rtl' : 'ltr';
$alignStyle = ($langDirection === 'rtl') ? 'direction:rtl;text-align:right;' : 'direction:ltr;text-align:left;';
?>
<link rel="stylesheet" href="/admin/assets/css/pages/vendors.css">

<div id="adminVendors" class="admin-fragment" style="max-width:1200px;margin:18px auto;font-family:system-ui,Segoe UI,Arial,sans-serif;<?php echo $alignStyle; ?>" dir="<?php echo $dirAttr; ?>">
  <header style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
    <h2 id="vendors_title" style="margin:0;"><?php echo $S('vendors_title','Vendors'); ?></h2>
    <div style="margin-left:auto;color:#6b7280;"><?php echo htmlspecialchars($user['username'] ?? 'guest', ENT_QUOTES | ENT_SUBSTITUTE); ?></div>
  </header>

  <!-- فلاتر البحث المتقدمة -->
  <div class="advanced-filters" style="background:#f8fafc;padding:12px;border-radius:8px;margin-bottom:12px;border:1px solid #eef2f7;">
    <h4 style="margin-top:0;margin-bottom:8px;"><?php echo $S('advanced_filters','Advanced Filters'); ?></h4>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;align-items:end;">
      <label>
        <?php echo $S('status','Status'); ?>
        <select id="filterStatus" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;">
          <option value="">-- <?php echo $S('all','All'); ?> --</option>
          <option value="pending">pending</option>
          <option value="approved">approved</option>
          <option value="suspended">suspended</option>
          <option value="rejected">rejected</option>
        </select>
      </label>
      
      <label>
        <?php echo $S('verified','Verified'); ?>
        <select id="filterVerified" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;">
          <option value="">-- <?php echo $S('all','All'); ?> --</option>
          <option value="1"><?php echo $S('yes','Yes'); ?></option>
          <option value="0"><?php echo $S('no','No'); ?></option>
        </select>
      </label>
      
      <label>
        <?php echo $S('country','Country'); ?>
        <select id="filterCountry" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;">
          <option value=""><?php echo $S('loading','Loading...'); ?></option>
        </select>
      </label>
      
      <label>
        <?php echo $S('city','City'); ?>
        <input id="filterCity" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;" placeholder="<?php echo $S('city_name','City name'); ?>">
      </label>
      
      <label>
        <?php echo $S('phone','Phone'); ?>
        <input id="filterPhone" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;" placeholder="<?php echo $S('phone_number','Phone number'); ?>">
      </label>
      
      <label>
        <?php echo $S('email','Email'); ?>
        <input id="filterEmail" type="text" style="width:100%;padding:6px;border:1px solid #e6eef0;border-radius:6px;" placeholder="<?php echo $S('email_address','Email address'); ?>">
      </label>
      
      <div>
        <button id="filterClear" class="btn" type="button" style="width:100%;"><?php echo $S('clear_filters','Clear Filters'); ?></button>
      </div>
    </div>
  </div>

  <div class="tools" style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
    <input id="vendorSearch" placeholder="<?php echo $S('search_placeholder','Search store, email, slug...'); ?>" style="padding:8px;border:1px solid #e6eef0;border-radius:6px;width:320px;">
    <button id="vendorRefresh" class="btn" type="button"><?php echo $S('refresh','Refresh'); ?></button>
    <button id="vendorNewBtn" class="btn primary" type="button"><?php echo $S('new_vendor','New Vendor'); ?></button>
    <div style="margin-left:auto;color:#6b7280;"><?php echo $S('total_label','Total:'); ?> <span id="vendorsCount">‑</span></div>
  </div>

  <div class="table-wrap" style="margin-bottom:18px;">
    <table id="vendorsTable" style="width:100%;border-collapse:collapse;">
      <thead style="background:#f8fafc;">
        <tr>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">ID</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo $S('store_col','Store'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo $S('email_col','Email'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo $S('type_col','Type'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo $S('status_col','Status'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo $S('verified_col','Verified'); ?></th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7"><?php echo $S('actions_col','Actions'); ?></th>
        </tr>
      </thead>
      <tbody id="vendorsTbody"><tr><td colspan="7" style="text-align:center;color:#6b7280;padding:18px;"><?php echo $S('loading','Loading...'); ?></td></tr></tbody>
    </table>
  </div>

  <!-- Embedded vendor form -->
  <section id="vendorFormSection" class="embedded-form" style="background:#fff;border:1px solid #eef2f7;padding:12px;border-radius:8px;">
    <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <h3 id="vendorFormTitle"><?php echo $S('create_edit','Create / Edit Vendor'); ?></h3>
      <div>
        <button id="vendorSaveBtn" class="btn primary" type="button"><?php echo $S('save','Save'); ?></button>
        <button id="vendorResetBtn" class="btn" type="button"><?php echo $S('reset','Reset'); ?></button>
      </div>
    </header>

    <div id="vendorFormErrors" class="errors" style="display:none;color:#b91c1c;margin-bottom:8px;"></div>

    <form id="vendorForm" enctype="multipart/form-data" autocomplete="off" onsubmit="return false;">
      <input type="hidden" id="vendor_id" name="id" value="0">
      <input type="hidden" id="vendor_user_id" name="user_id" value="<?php echo (int)($user['id'] ?? 0); ?>">
      <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE); ?>">

      <div class="form-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
        <label><?php echo $S('store_name_label','Store name'); ?>
          <input id="vendor_store_name" name="store_name" type="text" required>
        </label>

        <label><?php echo $S('slug_label','Slug'); ?>
          <input id="vendor_slug" name="slug" type="text">
        </label>

        <label><?php echo $S('type_label','Type'); ?>
          <select id="vendor_type" name="vendor_type">
            <option value="product_seller">product_seller</option>
            <option value="service_provider">service_provider</option>
            <option value="both">both</option>
          </select>
        </label>

        <label><?php echo $S('store_type_label','Store type'); ?>
          <select id="vendor_store_type" name="store_type">
            <option value="individual">individual</option>
            <option value="company">company</option>
            <option value="brand">brand</option>
          </select>
        </label>

        <label style="display:flex;align-items:center;gap:8px;"><?php echo $S('is_branch_label','Is branch'); ?>
          <input id="vendor_is_branch" name="is_branch" type="checkbox" value="1" style="margin-left:8px;">
        </label>

        <div id="parentVendorWrap" style="display:none;grid-column:1 / span 2;">
          <label><?php echo $S('parent_vendor_label','Parent vendor'); ?>
            <select id="vendor_parent_id" name="parent_vendor_id">
              <option value=""><?php echo $S('select_parent','-- select parent --'); ?></option>
            </select>
          </label>
        </div>

        <label><?php echo $S('branch_code_label','Branch code'); ?>
          <input id="vendor_branch_code" name="branch_code" type="text">
        </label>

        <label>
          <?php echo $S('inherit_settings_label','Inherit settings'); ?>
          <input id="inherit_settings" name="inherit_settings" type="checkbox" value="1" checked style="margin-left:8px;">
        </label>

        <label>
          <?php echo $S('inherit_products_label','Inherit products'); ?>
          <input id="inherit_products" name="inherit_products" type="checkbox" value="1" checked style="margin-left:8px;">
        </label>

        <label>
          <?php echo $S('inherit_commission_label','Inherit commission'); ?>
          <input id="inherit_commission" name="inherit_commission" type="checkbox" value="1" checked style="margin-left:8px;">
        </label>

        <label><?php echo $S('phone_label','Phone'); ?>
          <input id="vendor_phone" name="phone" type="text" required>
        </label>

        <label><?php echo $S('mobile_label','Mobile'); ?>
          <input id="vendor_mobile" name="mobile" type="text">
        </label>

        <label><?php echo $S('email_label','Email'); ?>
          <input id="vendor_email" name="email" type="email" required>
        </label>

        <label><?php echo $S('website_label','Website'); ?>
          <input id="vendor_website" name="website_url" type="text">
        </label>

        <label><?php echo $S('registration_label','Registration number'); ?>
          <input id="vendor_registration_number" name="registration_number" type="text">
        </label>

        <label><?php echo $S('tax_label','Tax number'); ?>
          <input id="vendor_tax_number" name="tax_number" type="text">
        </label>

        <label><?php echo $S('country_label','Country'); ?>
          <select id="vendor_country" name="country_id" required>
            <option value=""><?php echo $S('loading_countries','Loading countries...'); ?></option>
          </select>
        </label>

        <label><?php echo $S('city_label','City'); ?>
          <select id="vendor_city" name="city_id">
            <option value=""><?php echo $S('select_country_first','Select country first'); ?></option>
          </select>
        </label>

        <label><?php echo $S('postal_label','Postal code'); ?>
          <input id="vendor_postal" name="postal_code" type="text">
        </label>

        <label style="grid-column:1 / span 2"><?php echo $S('address_label','Address'); ?>
          <textarea id="vendor_address" name="address" rows="2"></textarea>
        </label>

        <label><?php echo $S('latlng_label','Latitude / Longitude'); ?>
          <div style="display:flex;gap:8px;">
            <input id="vendor_latitude" name="latitude" type="text" placeholder="latitude" style="flex:1">
            <input id="vendor_longitude" name="longitude" type="text" placeholder="longitude" style="flex:1">
            <button id="btnGetCoords" type="button" class="btn"><?php echo $S('get_coords','Get coords'); ?></button>
          </div>
          <small id="geocodeNote" style="color:#6b7280;"></small>
        </label>

        <label><?php echo $S('commission_label','Commission rate'); ?>
          <input id="vendor_commission" name="commission_rate" type="text" value="10.00">
        </label>

        <label><?php echo $S('radius_label','Service radius (KM)'); ?>
          <input id="vendor_radius" name="service_radius" type="number" value="0">
        </label>

        <label><?php echo $S('accepts_booking_label','Accepts online booking'); ?>
          <input id="vendor_accepts_online_booking" name="accepts_online_booking" type="checkbox" value="1" style="margin-left:8px;">
        </label>

        <label><?php echo $S('avg_response_label','Average response time (min)'); ?>
          <input id="vendor_average_response_time" name="average_response_time" type="number" value="0">
        </label>

        <label><?php echo $S('logo_label','Logo'); ?>
          <input id="vendor_logo" name="logo" type="file" accept="image/*">
        </label>
        <div class="img-preview" id="preview_logo" style="grid-column:1 / span 2;"></div>

        <label><?php echo $S('cover_label','Cover'); ?>
          <input id="vendor_cover" name="cover" type="file" accept="image/*">
        </label>
        <div class="img-preview" id="preview_cover" style="grid-column:1 / span 2;"></div>

        <label><?php echo $S('banner_label','Banner'); ?>
          <input id="vendor_banner" name="banner" type="file" accept="image/*">
        </label>
        <div class="img-preview" id="preview_banner" style="grid-column:1 / span 2;"></div>

        <!-- Admin-only fields -->
        <div id="adminFields" style="display:<?php echo $isAdmin ? 'block' : 'none'; ?>;grid-column:1 / span 2;padding-top:8px;border-top:1px dashed #eef2f7;">
          <label><?php echo $S('status_label','Status'); ?>
            <select id="vendor_status" name="status">
              <option value="pending">pending</option>
              <option value="approved">approved</option>
              <option value="suspended">suspended</option>
              <option value="rejected">rejected</option>
            </select>
          </label>
          <label><?php echo $S('is_verified_label','Is verified'); ?>
            <input id="vendor_is_verified" name="is_verified" type="checkbox" value="1">
          </label>
          <label><?php echo $S('is_featured_label','Is featured'); ?>
            <input id="vendor_is_featured" name="is_featured" type="checkbox" value="1">
          </label>
        </div>
      </div>

      <hr style="margin:12px 0;">
      <h4><?php echo $S('translations_heading','Translations'); ?></h4>
      <div id="vendor_translations_area" style="max-height:260px;overflow:auto;border:1px dashed #e6eef0;padding:8px;border-radius:6px;"></div>
      <div style="margin-top:8px;"><button id="vendorAddLangBtn" type="button" class="btn"><?php echo $S('add_language','Add Language'); ?></button></div>
    </form>
  </section>
</div>

<script>
  // Expose server data to client JS
  window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE); ?>';
  window.CURRENT_USER = <?php echo $currentUserJson; ?>;
  window.AVAILABLE_LANGUAGES = <?php echo $availableLangsJson; ?>;
  window.ADMIN_LANG = '<?php echo $preferredLangEscaped; ?>';
  window.LANG_DIRECTION = '<?php echo $dirAttr; ?>';
</script>

<script src="/admin/assets/js/pages/vendors.js" defer></script>