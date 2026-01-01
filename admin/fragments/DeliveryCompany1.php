<?php
/**
 * admin/fragments/DeliveryCompany.php
 *
 * Admin fragment for Delivery Companies — injects JS config and loads admin UI.
 * - Exposes API endpoints:
 *   window.API_BASE = '/api/routes/shipping.php'
 *   window.COUNTRIES_API = '/api/helpers/countries.php'
 *   window.CITIES_API = '/api/helpers/cities.php'
 *   window.PARENTS_API = '/api/routes/shipping.php?action=parents'
 * - Exposes CURRENT_USER, CSRF_TOKEN, AVAILABLE_LANGUAGES, ADMIN_LANG, LANG_DIRECTION
 *
 * Place at: admin/fragments/DeliveryCompany.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// try to gather current user safely (supports many session shapes)
$user = [];
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $user = $_SESSION['user'];
} else {
    if (!empty($_SESSION['user_id'])) {
        $user = [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : (isset($_SESSION['role']) ? (int)$_SESSION['role'] : null),
            'preferred_language' => $_SESSION['preferred_language'] ?? ($_SESSION['lang'] ?? null),
            'permissions' => $_SESSION['permissions'] ?? []
        ];
    }
}
$safeUser = [
    'id' => isset($user['id']) ? (int)$user['id'] : null,
    'username' => isset($user['username']) ? (string)$user['username'] : null,
    'email' => isset($user['email']) ? (string)$user['email'] : null,
    'role_id' => isset($user['role_id']) ? (int)$user['role_id'] : (isset($user['role']) ? (int)$user['role'] : null),
    'preferred_language' => $user['preferred_language'] ?? null,
    'permissions' => is_array($user['permissions']) ? $user['permissions'] : []
];

// Languages injection (best-effort)
$languages_for_js = [];
$langBase = $langBase ?? '/languages/admin';
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../'), '/\\');
$langDirPath = $docRoot . rtrim($langBase, '/\\');
if (is_dir($langDirPath)) {
    $files = glob($langDirPath . '/*.json') ?: [];
    foreach ($files as $file) {
        $code = pathinfo($file, PATHINFO_FILENAME);
        $content = @file_get_contents($file);
        $data = $content ? @json_decode($content, true) : null;
        if (is_array($data)) {
            $languages_for_js[] = [
                'code' => (string)$code,
                'name' => isset($data['name']) ? (string)$data['name'] : strtoupper((string)$code),
                'direction' => isset($data['direction']) ? (string)$data['direction'] : 'ltr',
                'strings' => isset($data['strings']) && is_array($data['strings']) ? $data['strings'] : []
            ];
        }
    }
}
if (empty($languages_for_js)) $languages_for_js[] = ['code'=>'en','name'=>'English','direction'=>'ltr','strings'=>[]];

$preferredLang = $_SESSION['preferred_language'] ?? ($safeUser['preferred_language'] ?? $languages_for_js[0]['code']);
if (!is_string($preferredLang) || $preferredLang === '') $preferredLang = $languages_for_js[0]['code'];
$langMeta = null;
foreach ($languages_for_js as $l) if ($l['code'] === $preferredLang) { $langMeta = $l; break; }
if (!$langMeta) $langMeta = $languages_for_js[0];
$langDirection = $langMeta['direction'] ?? 'ltr';

// CSRF fallback
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}
$csrfToken = (string)($_SESSION['csrf_token'] ?? '');

function safe_json_encode($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE);
    if ($s === false) {
        array_walk_recursive($v, function (&$item) { if (!is_string($item)) return; $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8'); });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
    return $s;
}

$currentUserJson = safe_json_encode($safeUser);
$availableLangsJson = safe_json_encode($languages_for_js);
?>
<link rel="stylesheet" href="/admin/assets/css/pages/DeliveryCompany.css">

<div id="adminDeliveryCompanies" dir="<?php echo htmlspecialchars($langDirection, ENT_QUOTES | ENT_SUBSTITUTE); ?>" style="direction:<?php echo htmlspecialchars($langDirection, ENT_QUOTES | ENT_SUBSTITUTE); ?>;max-width:1200px;margin:18px auto;">
  <header style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
    <h2 style="margin:0;">شركات الشحن</h2>
    <div style="margin-left:auto;color:#6b7280;"><?php echo htmlspecialchars($safeUser['username'] ?? 'guest', ENT_QUOTES | ENT_SUBSTITUTE); ?></div>
  </header>

  <!-- Filters (same as before) -->
  <div class="filters" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
    <input id="deliveryCompanySearch" placeholder="Search name, email, phone..." style="padding:8px;border:1px solid #e6eef0;border-radius:6px;width:260px;">
    <input id="deliveryCompanyFilterPhone" placeholder="Phone" style="padding:8px;border:1px solid #e6eef0;border-radius:6px;width:160px;">
    <input id="deliveryCompanyFilterEmail" placeholder="Email" style="padding:8px;border:1px solid #e6eef0;border-radius:6px;width:200px;">
    <select id="deliveryCompanyFilterCountry" style="padding:8px;border:1px solid #e6eef0;border-radius:6px;">
      <option value="">All countries</option>
    </select>
    <select id="deliveryCompanyFilterCity" style="padding:8px;border:1px solid #e6eef0;border-radius:6px;">
      <option value="">All cities</option>
    </select>
    <select id="deliveryCompanyFilterActive" style="padding:8px;border:1px solid #e6eef0;border-radius:6px;">
      <option value="">All status</option>
      <option value="1">Active</option>
      <option value="0">Inactive</option>
    </select>
    <button id="deliveryCompanyRefresh" class="btn" type="button">Refresh</button>
    <button id="deliveryCompanyNewBtn" class="btn primary" type="button">New Company</button>
    <div style="margin-left:auto;color:#6b7280;">Total: <span id="deliveryCompaniesCount">‑</span></div>
  </div>

  <div class="table-wrap" style="margin-bottom:18px;">
    <table id="deliveryCompaniesTable" style="width:100%;border-collapse:collapse;">
      <thead style="background:#f8fafc;">
        <tr>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">ID</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">Name</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">Email</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">Phone</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">Country / City</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">Active</th>
          <th style="padding:8px;border-bottom:1px solid #eef2f7">Actions</th>
        </tr>
      </thead>
      <tbody id="deliveryCompaniesTbody"><tr><td colspan="7" style="text-align:center;color:#6b7280;padding:18px;">Loading...</td></tr></tbody>
    </table>
  </div>

  <!-- Form (same fields) -->
  <section id="deliveryCompanyFormSection" class="embedded-form" style="background:#fff;border:1px solid #eef2f7;padding:12px;border-radius:8px;">
    <header style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <h3 id="deliveryCompanyFormTitle">Create / Edit Delivery Company</h3>
      <div>
        <button id="deliveryCompanySaveBtn" class="btn primary" type="button">Save</button>
        <button id="deliveryCompanyResetBtn" class="btn" type="button">Reset</button>
      </div>
    </header>

    <div id="deliveryCompanyFormErrors" class="errors" style="display:none;color:#b91c1c;margin-bottom:8px;"></div>

    <form id="deliveryCompanyForm" enctype="multipart/form-data" autocomplete="off" onsubmit="return false;">
      <input type="hidden" id="delivery_company_id" name="id" value="0">
      <input type="hidden" id="delivery_company_user_id" name="user_id" value="<?php echo (int)($safeUser['id'] ?? 0); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE); ?>">

      <div class="form-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
        <label>Parent Company
          <select id="delivery_company_parent" name="parent_id" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
            <option value="">-- No parent --</option>
          </select>
        </label>

        <label>Name
          <input id="delivery_company_name" name="name" type="text" required style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>Slug
          <input id="delivery_company_slug" name="slug" type="text" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>Phone
          <input id="delivery_company_phone" name="phone" type="text" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>Email
          <input id="delivery_company_email" name="email" type="email" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>Website
          <input id="delivery_company_website" name="website_url" type="text" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>API URL
          <input id="delivery_company_api_url" name="api_url" type="text" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>API Key
          <input id="delivery_company_api_key" name="api_key" type="text" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>Tracking URL
          <input id="delivery_company_tracking" name="tracking_url" type="text" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
        </label>

        <label>Country
          <select id="delivery_company_country" name="country_id" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
            <option value="">Loading countries...</option>
          </select>
        </label>

        <label>City
          <select id="delivery_company_city" name="city_id" style="width:100%;padding:8px;border:1px solid #e6eef0;border-radius:6px;">
            <option value="">Select country first</option>
          </select>
        </label>

        <label>Logo
          <input id="delivery_company_logo" name="logo" type="file" accept="image/*">
        </label>
        <div class="img-preview" id="preview_delivery_logo" style="display:flex;align-items:center;gap:8px;"></div>

        <div id="deliveryAdminFields" style="display:<?php echo ($safeUser['role_id'] === 1) ? 'block' : 'none'; ?>;grid-column:1 / span 2;padding-top:8px;border-top:1px dashed #eef2f7;">
          <label style="display:inline-block;margin-right:12px">Active
            <input id="delivery_company_is_active" name="is_active" type="checkbox" value="1">
          </label>
          <label style="display:inline-block">Rating average
            <input id="delivery_company_rating" name="rating_average" type="text" value="0.00" style="width:120px;padding:6px;border:1px solid #e6eef0;border-radius:6px;">
          </label>
        </div>
      </div>

      <hr style="margin:12px 0;">
      <h4>Translations</h4>
      <div id="deliveryCompany_translations_area" style="max-height:260px;overflow:auto;border:1px dashed #e6eef0;padding:8px;border-radius:6px;"></div>
      <div style="margin-top:8px;"><button id="deliveryCompanyAddLangBtn" type="button" class="btn">Add Language</button></div>
    </form>
  </section>
</div>

<script>
  // API endpoints used by the admin JS
  window.API_BASE = '/api/routes/shipping.php';
  window.COUNTRIES_API = '/api/helpers/countries.php';
  window.CITIES_API = '/api/helpers/cities.php';
  window.PARENTS_API = '/api/routes/shipping.php?action=parents';
  window.CSRF_TOKEN = '<?php echo addslashes($csrfToken); ?>';
  try { window.CURRENT_USER = <?php echo $currentUserJson; ?>; } catch (e) { window.CURRENT_USER = {}; }
  try { window.AVAILABLE_LANGUAGES = <?php echo $availableLangsJson; ?>; } catch (e) { window.AVAILABLE_LANGUAGES = [{"code":"en","name":"English","direction":"ltr","strings":{}}]; }
  window.ADMIN_LANG = '<?php echo htmlspecialchars($preferredLang, ENT_QUOTES | ENT_SUBSTITUTE); ?>';
  window.LANG_DIRECTION = '<?php echo htmlspecialchars($langDirection, ENT_QUOTES | ENT_SUBSTITUTE); ?>';
</script>

<script src="/admin/assets/js/pages/DeliveryCompany.js" defer></script>