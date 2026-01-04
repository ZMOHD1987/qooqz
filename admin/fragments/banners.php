<?php
// admin/fragments/banners.php
// Banner management fragment - uses ONLY bootstrap_admin_ui.php for all data
// Gets translations, permissions, theme, and settings from database via ADMIN_UI_PAYLOAD

// Load admin UI bootstrap - provides $ADMIN_UI_PAYLOAD with everything from DB
require_once __DIR__ . '/../../api/bootstrap_admin_ui.php';

// Extract data from ADMIN_UI_PAYLOAD
$userInfo = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];

// Permission check - prioritize role_id == 1 (super admin)
$canManageBanners = false;

// First check if user is super admin (role_id == 1)
if (!empty($userInfo['role_id']) && (int)$userInfo['role_id'] === 1) {
    $canManageBanners = true;
}

// Check if user has super_admin role in roles array
if (!$canManageBanners && !empty($userInfo['roles']) && is_array($userInfo['roles'])) {
    if (in_array('super_admin', $userInfo['roles'], true) || in_array('admin', $userInfo['roles'], true)) {
        $canManageBanners = true;
    }
}

// Check permissions array for manage_banners permission
if (!$canManageBanners) {
    $sessionPerms = $userInfo['permissions'] ?? [];
    if (is_array($sessionPerms) && in_array('manage_banners', $sessionPerms, true)) {
        $canManageBanners = true;
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');

// Helper to get translation string
function get_str($key, $strings) {
    if (isset($strings[$key])) return $strings[$key];
    // Try nested keys (e.g., "banners.page_title")
    $parts = explode('.', $key);
    $val = $strings;
    foreach ($parts as $p) {
        if (isset($val[$p])) $val = $val[$p];
        else return $key;
    }
    return is_string($val) ? $val : $key;
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES); ?>" dir="<?php echo htmlspecialchars($direction, ENT_QUOTES); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(get_str('banners.page_title', $strings) ?: get_str('page_title', $strings) ?: 'إدارة البانرات', ENT_QUOTES); ?></title>
    <link rel="stylesheet" href="/admin/assets/css/pages/banners.css">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($theme['colors_map']['primary_color']['color_value'] ?? '#3B82F6', ENT_QUOTES); ?>;
            --primary-hover: <?php echo htmlspecialchars($theme['colors_map']['primary_hover']['color_value'] ?? '#2563EB', ENT_QUOTES); ?>;
            --background-color: <?php echo htmlspecialchars($theme['colors_map']['background_color']['color_value'] ?? '#FFFFFF', ENT_QUOTES); ?>;
            --text-color: <?php echo htmlspecialchars($theme['colors_map']['text_color']['color_value'] ?? '#000000', ENT_QUOTES); ?>;
        }
    </style>
</head>
<body>

<div id="adminBanners" class="admin-fragment" dir="<?php echo htmlspecialchars($direction, ENT_QUOTES); ?>" style="font-family:Arial, Helvetica, sans-serif; max-width:1200px; margin:16px auto;">

  <h2 style="margin-bottom:8px;"><?php echo htmlspecialchars(get_str('banners.heading_manage_banners', $strings) ?: get_str('heading_manage_banners', $strings) ?: 'إدارة البانرات', ENT_QUOTES); ?></h2>

  <?php if (!$canManageBanners): ?>
    <div style="background:#fff3cd;color:#856404;border:1px solid #ffeeba;padding:10px;border-radius:6px;margin-bottom:12px;">
      <?php echo htmlspecialchars(get_str('banners.no_permission_notice', $strings) ?: get_str('no_permission_notice', $strings) ?: 'ليس لديك صلاحية لإدارة البانرات', ENT_QUOTES); ?>
    </div>
  <?php else: ?>

  <!-- Search and filters -->
  <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
    <input type="search" id="bannerSearch" placeholder="<?php echo htmlspecialchars(get_str('banners.search_placeholder', $strings) ?: get_str('search_placeholder', $strings) ?: 'البحث عن بانرات...', ENT_QUOTES); ?>" style="flex:1;min-width:200px;padding:8px;border:1px solid #ddd;border-radius:4px;">
    <button id="btnRefresh" style="padding:8px 16px;background:var(--primary-color);color:#fff;border:none;border-radius:4px;cursor:pointer;"><?php echo htmlspecialchars(get_str('banners.btn_refresh', $strings) ?: get_str('btn_refresh', $strings) ?: 'تحديث', ENT_QUOTES); ?></button>
    <button id="btnNew" style="padding:8px 16px;background:var(--primary-color);color:#fff;border:none;border-radius:4px;cursor:pointer;"><?php echo htmlspecialchars(get_str('banners.btn_new', $strings) ?: get_str('btn_new', $strings) ?: 'إضافة بانر', ENT_QUOTES); ?></button>
  </div>

  <div id="bannersStatus" style="margin-bottom:8px;min-height:20px;color:#064e3b;"></div>

  <div style="margin-bottom:8px;"><strong><?php echo htmlspecialchars(get_str('banners.total', $strings) ?: get_str('total', $strings) ?: 'الإجمالي', ENT_QUOTES); ?>:</strong> <span id="bannersCount">-</span></div>

  <!-- Table -->
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;border:1px solid #ddd;">
      <thead>
        <tr style="background:#f3f4f6;">
          <th style="padding:8px;border:1px solid #ddd;text-align:<?php echo $direction === 'rtl' ? 'right' : 'left'; ?>;"><?php echo htmlspecialchars(get_str('banners.id', $strings) ?: get_str('id', $strings) ?: 'الرقم', ENT_QUOTES); ?></th>
          <th style="padding:8px;border:1px solid #ddd;text-align:<?php echo $direction === 'rtl' ? 'right' : 'left'; ?>;"><?php echo htmlspecialchars(get_str('banners.title', $strings) ?: get_str('title', $strings) ?: 'العنوان', ENT_QUOTES); ?></th>
          <th style="padding:8px;border:1px solid #ddd;text-align:<?php echo $direction === 'rtl' ? 'right' : 'left'; ?>;"><?php echo htmlspecialchars(get_str('banners.image', $strings) ?: get_str('image', $strings) ?: 'الصورة', ENT_QUOTES); ?></th>
          <th style="padding:8px;border:1px solid #ddd;text-align:<?php echo $direction === 'rtl' ? 'right' : 'left'; ?>;"><?php echo htmlspecialchars(get_str('banners.position', $strings) ?: get_str('position', $strings) ?: 'الموضع', ENT_QUOTES); ?></th>
          <th style="padding:8px;border:1px solid #ddd;text-align:<?php echo $direction === 'rtl' ? 'right' : 'left'; ?>;"><?php echo htmlspecialchars(get_str('banners.is_active', $strings) ?: get_str('is_active', $strings) ?: 'نشط', ENT_QUOTES); ?></th>
          <th style="padding:8px;border:1px solid #ddd;text-align:<?php echo $direction === 'rtl' ? 'right' : 'left'; ?>;"><?php echo htmlspecialchars(get_str('banners.actions', $strings) ?: get_str('actions', $strings) ?: 'الإجراءات', ENT_QUOTES); ?></th>
        </tr>
      </thead>
      <tbody id="bannersTbody">
        <tr><td colspan="6" style="padding:12px;text-align:center;color:#666;"><?php echo htmlspecialchars(get_str('banners.loading', $strings) ?: get_str('loading', $strings) ?: 'جاري التحميل...', ENT_QUOTES); ?></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Form area (hidden by default) -->
  <div id="bannerFormWrap" style="display:none;margin-top:20px;padding:16px;border:1px solid #ddd;border-radius:6px;background:#f9fafb;">
    <h3 id="formTitle"><?php echo htmlspecialchars(get_str('banners.add_banner', $strings) ?: get_str('add_banner', $strings) ?: 'إضافة بانر', ENT_QUOTES); ?></h3>
    <form id="bannerForm">
      <input type="hidden" id="bannerId" name="id">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
      <input type="hidden" name="action" value="save">
      
      <div style="margin-bottom:12px;">
        <label><?php echo htmlspecialchars(get_str('banners.title', $strings) ?: 'العنوان', ENT_QUOTES); ?>:</label>
        <input type="text" name="title" id="bannerTitle" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
      </div>
      
      <div style="margin-bottom:12px;">
        <label><?php echo htmlspecialchars(get_str('banners.subtitle', $strings) ?: 'العنوان الفرعي', ENT_QUOTES); ?>:</label>
        <input type="text" name="subtitle" id="bannerSubtitle" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
      </div>
      
      <div style="margin-bottom:12px;">
        <label><?php echo htmlspecialchars(get_str('banners.image_url', $strings) ?: 'رابط الصورة', ENT_QUOTES); ?>:</label>
        <input type="text" name="image_url" id="bannerImageUrl" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
      </div>
      
      <div style="margin-bottom:12px;">
        <label><?php echo htmlspecialchars(get_str('banners.position', $strings) ?: 'الموضع', ENT_QUOTES); ?>:</label>
        <select name="position" id="bannerPosition" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
          <option value="homepage_main"><?php echo htmlspecialchars(get_str('banners.position_homepage_main', $strings) ?: 'الرئيسية - أساسي', ENT_QUOTES); ?></option>
          <option value="homepage_secondary"><?php echo htmlspecialchars(get_str('banners.position_homepage_secondary', $strings) ?: 'الرئيسية - ثانوي', ENT_QUOTES); ?></option>
          <option value="category"><?php echo htmlspecialchars(get_str('banners.position_category', $strings) ?: 'الفئة', ENT_QUOTES); ?></option>
          <option value="product"><?php echo htmlspecialchars(get_str('banners.position_product', $strings) ?: 'المنتج', ENT_QUOTES); ?></option>
          <option value="custom"><?php echo htmlspecialchars(get_str('banners.position_custom', $strings) ?: 'مخصص', ENT_QUOTES); ?></option>
        </select>
      </div>
      
      <div style="margin-bottom:12px;">
        <label><input type="checkbox" name="is_active" id="bannerIsActive" value="1"> <?php echo htmlspecialchars(get_str('banners.is_active', $strings) ?: 'نشط', ENT_QUOTES); ?></label>
      </div>
      
      <div style="display:flex;gap:8px;">
        <button type="submit" style="padding:8px 16px;background:var(--primary-color);color:#fff;border:none;border-radius:4px;cursor:pointer;"><?php echo htmlspecialchars(get_str('banners.btn_save', $strings) ?: 'حفظ', ENT_QUOTES); ?></button>
        <button type="button" id="btnCancelForm" style="padding:8px 16px;background:#6b7280;color:#fff;border:none;border-radius:4px;cursor:pointer;"><?php echo htmlspecialchars(get_str('banners.btn_cancel', $strings) ?: 'إلغاء', ENT_QUOTES); ?></button>
      </div>
    </form>
  </div>

  <?php endif; ?>

</div>

<script>
// Pass ADMIN_UI_PAYLOAD data to JavaScript
window.ADMIN_UI = <?php echo json_encode($ADMIN_UI_PAYLOAD, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.I18N = window.ADMIN_UI.strings || {};
window.I18N_FLAT = window.ADMIN_UI.strings || {};
window.USER_INFO = window.ADMIN_UI.user || {};
window.THEME = window.ADMIN_UI.theme || {};
window.LANG = '<?php echo htmlspecialchars($lang, ENT_QUOTES); ?>';
window.DIRECTION = '<?php echo htmlspecialchars($direction, ENT_QUOTES); ?>';
window.CSRF_TOKEN = '<?php echo $csrf; ?>';
</script>
<script src="/admin/assets/js/pages/banners.js"></script>

</body>
</html>

// --- configuration: base path for language files
$langBase = __DIR__ . '/../../languages/banners'; // languages/banners
$defaultLangCode = 'en';

// --- try to include init/auth (common locations) to ensure session user/permissions/rbac available
$bootCandidates = [
    __DIR__ . '/../includes/init.php',
    __DIR__ . '/../../admin/includes/init.php',
    __DIR__ . '/../../includes/init.php',
    __DIR__ . '/../_auth.php',
    __DIR__ . '/_auth.php',
    __DIR__ . '/../../_auth.php'
];
foreach ($bootCandidates as $c) {
    if (is_readable($c)) {
        try { require_once $c; } catch (Throwable $e) { /* ignore */ }
    }
}

// --- determine preferred language
$preferred_lang = $defaultLangCode;
if (!empty($_SESSION['preferred_language'])) {
    $preferred_lang = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$_SESSION['preferred_language']));
} elseif (!empty($_SESSION['user']['preferred_language'])) {
    $preferred_lang = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$_SESSION['user']['preferred_language']));
} elseif (!empty($_SESSION['user_id'])) {
    // optional DB lookup if init didn't provide preferred_language
    $dbFile = __DIR__ . '/../../api/config/db.php';
    if (is_readable($dbFile)) {
        require_once $dbFile;
        if (function_exists('connectDB')) {
            $conn = connectDB();
            if ($conn instanceof mysqli) {
                $uid = (int)$_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT preferred_language FROM users WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $uid);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!empty($res['preferred_language'])) $preferred_lang = preg_replace('/[^a-z0-9_-]/i','',strtolower($res['preferred_language']));
                }
            }
        }
    }
}

// --- load JSON translations (nested structure)
function load_lang_json($basePath, $code) {
    $file = rtrim($basePath, '/\\') . '/' . $code . '.json';
    if (!is_readable($file)) return null;
    $txt = @file_get_contents($file);
    if ($txt === false) return null;
    $json = @json_decode($txt, true);
    return is_array($json) ? $json : null;
}

$langJson = load_lang_json($langBase, $preferred_lang);
$enJson = load_lang_json($langBase, $defaultLangCode);

// if preferred missing, fallback to English
if ($langJson === null) $langJson = is_array($enJson) ? $enJson : [];

// if English exists, merge defaults (do not overwrite existing keys)
if (is_array($enJson)) {
    // recursive merge: en as base, overlay langJson
    $merged = $enJson;
    $stack = [ ['a' => &$merged, 'b' => $langJson] ];
    // simple recursive replacement
    $merged = array_replace_recursive($enJson, $langJson);
    $langJson = $merged;
}

// flatten nested translations to single-level map for fast lookup in JS/template
function flatten_recursive(array $arr, array &$out = [], $prefix = '') {
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? $k : ($prefix . '.' . $k);
        if (is_array($v)) flatten_recursive($v, $out, $key);
        else {
            $out[$key] = $v;
            // also map by short key (last segment) if not present, to support older key lookups
            $parts = explode('.', $key);
            $short = end($parts);
            if (!isset($out[$short])) $out[$short] = $v;
        }
    }
    return $out;
}
$langFlat = [];
flatten_recursive($langJson, $langFlat);

// helper: server-side language lookup (tries flat -> nested containers -> fallback)
function get_lang($key, $default = null) {
    global $langFlat, $langJson;
    if (isset($langFlat[$key]) && $langFlat[$key] !== '') return $langFlat[$key];
    // containers
    $containers = ['banners','strings','buttons','labels','actions','status','forms','table','menu'];
    foreach ($containers as $c) {
        if (!empty($langJson[$c]) && is_array($langJson[$c]) && isset($langJson[$c][$key]) && $langJson[$c][$key] !== '') return $langJson[$c][$key];
    }
    if ($default !== null) return $default;
    return ucwords(str_replace(['_', '.'], ' ', $key));
}

// --- languages list for translation rows (DB preferred, fallback scanning files)
$languages = [];
$dbFile = __DIR__ . '/../../api/config/db.php';
if (is_readable($dbFile)) {
    require_once $dbFile;
    if (function_exists('connectDB')) {
        $conn = connectDB();
        if ($conn instanceof mysqli) {
            $q = "SELECT code, name, direction FROM languages ORDER BY code";
            if ($stmt = $conn->prepare($q)) {
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $languages[] = $r;
                $stmt->close();
            }
        }
    }
}
if (empty($languages) && is_dir($langBase)) {
    foreach (scandir($langBase) as $f) {
        if (preg_match('/^([a-z]{2,8})\\.json$/i', $f, $m)) {
            $code = $m[1];
            $pack = load_lang_json($langBase, $code);
            $languages[] = ['code' => $code, 'name' => $pack['name'] ?? strtoupper($code), 'direction' => $pack['direction'] ?? 'ltr'];
        }
    }
}

// permission check (no redirect)
$canManageBanners = false;

// First check if user is super admin (role_id == 1)
if (!empty($_SESSION['user']['role_id']) && (int)$_SESSION['user']['role_id'] === 1) {
    $canManageBanners = true;
}
if (!empty($_SESSION['user']['role']) && (int)$_SESSION['user']['role'] === 1) {
    $canManageBanners = true;
}

// Check if user has super_admin role in roles array
if (!$canManageBanners && !empty($_SESSION['user']['roles']) && is_array($_SESSION['user']['roles'])) {
    if (in_array('super_admin', $_SESSION['user']['roles'], true) || in_array('admin', $_SESSION['user']['roles'], true)) {
        $canManageBanners = true;
    }
}

// Check using RBAC if available
if (!$canManageBanners && isset($rbac) && is_object($rbac)) {
    if (method_exists($rbac, 'hasPermission')) {
        try { $canManageBanners = (bool)$rbac->hasPermission('manage_banners'); } catch (Throwable $e) { $canManageBanners = false; }
    } elseif (method_exists($rbac, 'check')) {
        try { $canManageBanners = (bool)$rbac->check('manage_banners'); } catch (Throwable $e) { $canManageBanners = false; }
    }
}

// Check permissions array for manage_banners permission
if (!$canManageBanners) {
    $sessionPerms = $_SESSION['permissions'] ?? [];
    if (is_array($sessionPerms) && in_array('manage_banners', $sessionPerms, true)) $canManageBanners = true;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');

?>
<link rel="stylesheet" href="/admin/assets/css/pages/banners.css">

<div id="adminBanners" class="admin-fragment" dir="<?php echo htmlspecialchars($langJson['direction'] ?? ($langJson['dir'] ?? 'ltr'), ENT_QUOTES); ?>" style="font-family:Arial, Helvetica, sans-serif; max-width:1200px; margin:16px auto;">

  <h2 style="margin-bottom:8px;"><?php echo htmlspecialchars(get_lang('banners.heading_manage_banners') ?: get_lang('heading_manage_banners')); ?></h2>

  <?php if (!$canManageBanners): ?>
    <div style="background:#fff3cd;color:#856404;border:1px solid #ffeeba;padding:10px;border-radius:6px;margin-bottom:12px;">
      <?php echo htmlspecialchars(get_lang('banners.no_permission_notice') ?: get_lang('no_permission_notice')); ?>
    </div>
  <?php endif; ?>

  <div id="bannersStatus" aria-live="polite" class="status" style="min-height:22px;margin-bottom:8px;color:#064e3b;"></div>

  <div class="tools" style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
    <input id="bannerSearch" type="search" placeholder="<?php echo htmlspecialchars(get_lang('search_placeholder','Search...')); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;width:320px;">
    <button id="bannerRefresh" class="btn" type="button"><?php echo htmlspecialchars(get_lang('banners.btn_refresh') ?: get_lang('btn_refresh')); ?></button>
    <button id="bannerNewBtn" class="btn primary" type="button"><?php echo htmlspecialchars(get_lang('banners.btn_new') ?: get_lang('btn_new')); ?></button>
    <span style="margin-left:auto;color:#666;font-size:13px;"><?php echo htmlspecialchars(get_lang('banners.label_total') ?: get_lang('label_total')); ?>: <span id="bannersCount">‑</span></span>
  </div>

  <div class="table-wrap" style="overflow:auto;border:1px solid #e5e7eb;border-radius:6px;background:#fff;padding:8px;">
    <table id="bannersTable" style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead style="background:#f3f4f6;">
        <tr>
          <th style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;width:60px;"><?php echo htmlspecialchars(get_lang('banners.col_id') ?: get_lang('col_id')); ?></th>
          <th style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;"><?php echo htmlspecialchars(get_lang('banners.col_title') ?: get_lang('col_title')); ?></th>
          <th style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;width:120px;"><?php echo htmlspecialchars(get_lang('banners.col_image') ?: get_lang('col_image')); ?></th>
          <th style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;width:160px;"><?php echo htmlspecialchars(get_lang('banners.col_position') ?: get_lang('col_position')); ?></th>
          <th style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;width:80px;"><?php echo htmlspecialchars(get_lang('banners.col_active') ?: get_lang('col_active')); ?></th>
          <th style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;width:220px;"><?php echo htmlspecialchars(get_lang('banners.col_actions') ?: get_lang('col_actions')); ?></th>
        </tr>
      </thead>
      <tbody id="bannersTbody">
        <tr><td colspan="6" style="padding:12px;text-align:center;color:#666;"><?php echo htmlspecialchars(get_lang('banners.loading') ?: get_lang('loading')); ?></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Form (create/edit) -->
  <div id="bannerFormWrap" class="form-wrap" style="margin-top:18px;display:none;border:1px solid #e5e7eb;padding:12px;border-radius:6px;background:#fff;">
    <h3 id="bannerFormTitle"><?php echo htmlspecialchars(get_lang('banners.form_title_create') ?: get_lang('form_title_create')); ?></h3>

    <form id="bannerForm" autocomplete="off" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <input type="hidden" id="banner_id" name="id" value="0">
      <input type="hidden" id="banner_translations" name="translations" value="">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

      <div style="grid-column:1 / span 2;display:flex;gap:8px;align-items:center;">
        <label style="width:80px;display:inline-block;"><?php echo htmlspecialchars(get_lang('banners.label_title') ?: get_lang('label_title')); ?></label>
        <input id="banner_title" name="title" type="text" required style="flex:1;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
        <button id="toggleTranslationsBtn" type="button" class="btn" style="padding:6px 10px;"><?php echo htmlspecialchars(get_lang('labels.translations') ?: get_lang('btn_translations','Translations')); ?></button>
      </div>

      <div style="grid-column:1 / span 2;">
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_subtitle') ?: get_lang('label_subtitle')); ?></label>
        <input id="banner_subtitle" name="subtitle" type="text" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_link_url') ?: get_lang('label_link_url')); ?></label>
        <input id="banner_link_url" name="link_url" type="text" placeholder="/products.php" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_link_text') ?: get_lang('label_link_text')); ?></label>
        <input id="banner_link_text" name="link_text" type="text" placeholder="<?php echo htmlspecialchars(get_lang('banners.label_link_text') ?: get_lang('label_link_text')); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_image_desktop') ?: get_lang('label_image_desktop')); ?></label>
        <input id="banner_image_file" type="file" accept="image/*" style="margin-bottom:6px;">
        <input id="banner_image_url" name="image_url" type="text" placeholder="/uploads/banners/..." style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
        <div id="banner_image_preview" style="margin-top:6px;"></div>
        <small id="imageUploadStatus" style="color:#666;display:block;margin-top:6px;"></small>
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_image_mobile') ?: get_lang('label_image_mobile')); ?></label>
        <input id="banner_mobile_image_file" type="file" accept="image/*" style="margin-bottom:6px;">
        <input id="banner_mobile_image_url" name="mobile_image_url" type="text" placeholder="/uploads/banners/..." style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
        <div id="banner_mobile_image_preview" style="margin-top:6px;"></div>
        <small id="mobileImageUploadStatus" style="color:#666;display:block;margin-top:6px;"></small>
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_position') ?: get_lang('label_position')); ?></label>
        <select id="banner_position" name="position" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
          <option value=""><?php echo htmlspecialchars(get_lang('banners.opt_choose') ?: get_lang('opt_choose','— Choose —')); ?></option>
          <option value="homepage_main">homepage_main</option>
          <option value="homepage_secondary">homepage_secondary</option>
        </select>
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_theme') ?: get_lang('label_theme')); ?></label>
        <input id="banner_theme_id" name="theme_id" type="number" min="0" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_bg_color') ?: get_lang('label_bg_color')); ?></label>
        <input id="banner_background_color" name="background_color" type="color" value="#ffffff" style="height:38px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_text_color') ?: get_lang('label_text_color')); ?></label>
        <input id="banner_text_color" name="text_color" type="color" value="#000000" style="height:38px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_button_style') ?: get_lang('label_button_style')); ?></label>
        <input id="banner_button_style" name="button_style" type="text" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_sort_order') ?: get_lang('label_sort_order')); ?></label>
        <input id="banner_sort_order" name="sort_order" type="number" value="0" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_active') ?: get_lang('label_active')); ?></label>
        <select id="banner_is_active" name="is_active" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
          <option value="1"><?php echo htmlspecialchars(get_lang('banners.yes') ?: get_lang('yes','Yes')); ?></option>
          <option value="0"><?php echo htmlspecialchars(get_lang('banners.no') ?: get_lang('no','No')); ?></option>
        </select>
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_start_date') ?: get_lang('label_start_date')); ?></label>
        <input id="banner_start_date" name="start_date" type="datetime-local" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <div>
        <label style="display:block;margin-bottom:6px;"><?php echo htmlspecialchars(get_lang('banners.label_end_date') ?: get_lang('label_end_date')); ?></label>
        <input id="banner_end_date" name="end_date" type="datetime-local" style="padding:8px;border:1px solid #d1d5db;border-radius:4px;">
      </div>

      <!-- Translations inline table -->
      <div id="translationsArea" style="grid-column:1 / span 2; margin-top:12px; border:1px dashed #e5e7eb; padding:10px; border-radius:6px; display:none;">
        <strong style="display:block;margin-bottom:8px;"><?php echo htmlspecialchars(get_lang('banners.translations_title') ?: get_lang('translations_title','Banner Translations')); ?></strong>
        <p style="color:#666;margin-top:0;margin-bottom:8px;"><?php echo htmlspecialchars(get_lang('banners.translations_desc') ?: get_lang('translations_desc','Fill translations for supported languages')); ?></p>
        <table id="translationsInlineTable" style="width:100%;border-collapse:collapse;">
          <thead><tr style="background:#fafafa;"><th style="padding:8px">Language</th><th style="padding:8px"><?php echo htmlspecialchars(get_lang('banners.col_title') ?: get_lang('col_title')); ?></th><th style="padding:8px"><?php echo htmlspecialchars(get_lang('banners.label_subtitle') ?: get_lang('label_subtitle')); ?></th><th style="padding:8px"><?php echo htmlspecialchars(get_lang('banners.label_link_text') ?: get_lang('label_link_text')); ?></th></tr></thead>
          <tbody>
            <?php if (!empty($languages) && is_array($languages)): foreach ($languages as $lg): $code = htmlspecialchars($lg['code'], ENT_QUOTES); $name = htmlspecialchars($lg['name'] ?? $code, ENT_QUOTES); ?>
            <tr data-lang="<?php echo $code; ?>">
              <td style="padding:6px;border-bottom:1px solid #eee;vertical-align:top;"><?php echo $name; ?> <small style="color:#666">(<?php echo $code; ?>)</small></td>
              <td style="padding:6px;border-bottom:1px solid #eee;"><input class="tr-title" data-lang="<?php echo $code; ?>" style="width:100%;padding:6px;border:1px solid #e5e7eb;border-radius:4px;"></td>
              <td style="padding:6px;border-bottom:1px solid #eee;"><input class="tr-subtitle" data-lang="<?php echo $code; ?>" style="width:100%;padding:6px;border:1px solid #e5e7eb;border-radius:4px;"></td>
              <td style="padding:6px;border-bottom:1px solid #eee;"><input class="tr-linktext" data-lang="<?php echo $code; ?>" style="width:100%;padding:6px;border:1px solid #e5e7eb;border-radius:4px;"></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="padding:12px;text-align:center;color:#666;"><?php echo htmlspecialchars(get_lang('no_languages','No languages configured')); ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div style="grid-column:1 / span 2;display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
        <button id="bannerCancelBtn" type="button" class="btn"><?php echo htmlspecialchars(get_lang('banners.btn_cancel') ?: get_lang('btn_cancel')); ?></button>
        <button id="bannerDeleteBtn" type="button" class="btn danger" style="display:none;"><?php echo htmlspecialchars(get_lang('banners.btn_delete') ?: get_lang('btn_delete')); ?></button>
        <button id="bannerSaveBtn" type="button" class="btn primary"><?php echo htmlspecialchars(get_lang('banners.btn_save') ?: get_lang('btn_save')); ?></button>
      </div>
    </form>
  </div>

</div>

<script>
  window.I18N = <?php echo json_encode($langJson, JSON_UNESCAPED_UNICODE); ?>;
  window.I18N_FLAT = <?php echo json_encode($langFlat, JSON_UNESCAPED_UNICODE); ?>;
  window.AVAILABLE_LANGUAGES = <?php echo json_encode($languages, JSON_UNESCAPED_UNICODE); ?>;
  window.CURRENT_LOCALE = "<?php echo htmlspecialchars($preferred_lang, ENT_QUOTES); ?>";
  window.DIRECTION = "<?php echo htmlspecialchars($langJson['direction'] ?? ($langJson['dir'] ?? 'ltr'), ENT_QUOTES); ?>";
  window.CAN_MANAGE_BANNERS = <?php echo $canManageBanners ? 'true' : 'false'; ?>;
  window.CSRF_TOKEN = "<?php echo $csrf; ?>";

  // ensure page-level dir/lang applied early
  (function(){
    try {
      if (window.DIRECTION) document.documentElement.setAttribute('dir', window.DIRECTION);
      if (window.CURRENT_LOCALE) document.documentElement.setAttribute('lang', window.CURRENT_LOCALE);
    } catch (e) {}
  })();
</script>

<script src="/admin/assets/js/pages/banners.js" defer></script>
