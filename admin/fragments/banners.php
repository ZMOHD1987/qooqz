<?php
/**
 * admin/fragments/banners.php
 *
 * Banner management fragment (final)
 * - Loads bootstrap_admin_ui.php to get $ADMIN_UI_PAYLOAD
 * - Falls back to loading languages/banners/<lang>.json when translations missing
 * - Exposes window.I18N_FLAT, window.ADMIN_UI, CSRF, API paths to client JS
 * - Uses theme colors_map to inject :root CSS variables
 */

declare(strict_types=1);

// Load admin UI bootstrap (populates $ADMIN_UI_PAYLOAD if available)
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) { /* logged inside bootstrap_admin_ui */ }
}

// Normalize payload
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$userInfo = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];

// Helper: flatten nested translation arrays into dot keys + short keys
function flatten_recursive(array $arr, array &$out = [], $prefix = '') {
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? $k : ($prefix . '.' . $k);
        if (is_array($v)) {
            flatten_recursive($v, $out, $key);
        } else {
            $out[$key] = (string)$v;
            $parts = explode('.', $key);
            $short = end($parts);
            if (!isset($out[$short])) $out[$short] = (string)$v;
        }
    }
    return $out;
}

// If $strings is empty, try loading module-specific translations directly from filesystem
$flatStrings = [];
if (!empty($strings) && is_array($strings)) {
    flatten_recursive($strings, $flatStrings);
} else {
    // Attempt to locate languages/banners/<lang>.json or fallback to en.json
    $langBaseCandidates = [
        realpath(__DIR__ . '/../../languages'),
        realpath(__DIR__ . '/../../../languages'),
        realpath(__DIR__ . '/../../..') . '/languages'
    ];
    $langBase = null;
    foreach ($langBaseCandidates as $cand) {
        if ($cand && is_dir($cand)) { $langBase = $cand; break; }
    }
    $moduleDir = $langBase ? rtrim($langBase, '/\\') . '/banners' : null;
    $loaded = null;
    if ($moduleDir && is_dir($moduleDir)) {
        $pref = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($lang ?: 'en'));
        $tryFiles = [$moduleDir . "/{$pref}.json", $moduleDir . "/{$pref}.json", $moduleDir . '/en.json'];
        foreach ($tryFiles as $f) {
            if ($f && is_readable($f)) {
                $txt = @file_get_contents($f);
                $json = $txt ? @json_decode($txt, true) : null;
                if (is_array($json)) { $loaded = $json; break; }
            }
        }
    }
    if (is_array($loaded)) {
        // loaded may contain nested "strings" root
        if (isset($loaded['strings']) && is_array($loaded['strings'])) $loadedData = $loaded['strings'];
        else $loadedData = $loaded;
        flatten_recursive($loadedData, $flatStrings);
        // Also set ADMIN_UI_PAYLOAD['strings'] so client code using ADMIN_UI.strings may find values
        $ADMIN_UI_PAYLOAD['strings'] = $loadedData;
    }
}

// If still empty, ensure $flatStrings has at least minimal keys (prevents undefined labels)
if (empty($flatStrings)) {
    $fallbacks = [
        'banners.page_title' => 'Banners Management',
        'banners.loading' => 'Loading...',
        'banners.no_banners' => 'No banners found',
        'banners.btn_new' => 'Add Banner',
        'banners.btn_refresh' => 'Refresh',
        'banners.btn_save' => 'Save',
        'banners.btn_cancel' => 'Cancel',
        'banners.btn_delete' => 'Delete',
        'banners.btn_edit' => 'Edit',
        'banners.btn_toggle' => 'Toggle',
        'banners.confirm_delete' => 'Are you sure?',
        'banners.no_permission_notice' => 'You do not have permission'
    ];
    foreach ($fallbacks as $k => $v) if (!isset($flatStrings[$k])) $flatStrings[$k] = $v;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); }
}
$csrf = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES);

// Build colors_map from theme (support list -> map fallback)
$colorsMap = [];
if (!empty($theme['colors_map']) && is_array($theme['colors_map'])) {
    $colorsMap = $theme['colors_map'];
} elseif (!empty($theme['colors']) && is_array($theme['colors'])) {
    foreach ($theme['colors'] as $c) {
        $k = $c['setting_key'] ?? $c['setting_name'] ?? null;
        if ($k) $colorsMap[strtolower(preg_replace('/[^a-z0-9\-]+/i','-', $k))] = $c['color_value'] ?? null;
    }
}

// color lookup helper
function color_lookup(array $map, array $keys, string $fallback = ''): string {
    foreach ($keys as $k) if (isset($map[$k]) && trim((string)$map[$k]) !== '') return (string)$map[$k];
    return $fallback;
}

// CSS variables
$cssVars = [
    '--theme-primary'       => color_lookup($colorsMap, ['primary','primary-color','primary_color'], '#3B82F6'),
    '--theme-primary-hover' => color_lookup($colorsMap, ['primary-hover','primary_hover'], ''),
    '--theme-background'    => color_lookup($colorsMap, ['background','background-main','background_main'], '#FFFFFF'),
    '--theme-card'          => color_lookup($colorsMap, ['background-secondary','card','card_background'], '#FFFFFF'),
    '--theme-border'        => color_lookup($colorsMap, ['border','border_color','border-color'], '#E5E7EB'),
    '--theme-text-primary'  => color_lookup($colorsMap, ['text-primary','text_primary','text'], '#111827'),
    '--theme-text-muted'    => color_lookup($colorsMap, ['text-secondary','text_secondary','muted'], '#6B7280'),
    '--theme-error'         => color_lookup($colorsMap, ['error','error-color','error_color'], '#DC2626'),
    '--theme-success'       => color_lookup($colorsMap, ['success','success-color','success_color'], '#059669'),
];

// Font
$fontFamily = 'Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
if (!empty($theme['fonts']) && is_array($theme['fonts']) && !empty($theme['fonts'][0]['font_family'])) $fontFamily = $theme['fonts'][0]['font_family'];
$cssVars['--theme-font-primary'] = $fontFamily;

// API endpoints
$apiBanners = $ADMIN_UI_PAYLOAD['api']['banners'] ?? '/api/banners';
$apiUpload = $ADMIN_UI_PAYLOAD['api']['upload_image'] ?? null;

// Permissions
$canManage = false;
if (!empty($userInfo['role_id']) && (int)$userInfo['role_id'] === 1) $canManage = true;
if (!$canManage && !empty($userInfo['roles']) && is_array($userInfo['roles'])) {
    if (in_array('super_admin', $userInfo['roles'], true) || in_array('admin', $userInfo['roles'], true)) $canManage = true;
}
if (!$canManage && !empty($userInfo['permissions']) && is_array($userInfo['permissions'])) {
    if (in_array('manage_banners', $userInfo['permissions'], true)) $canManage = true;
}

// Helper get string (uses flatStrings)
function gs(string $key, array $flat): string {
    if (isset($flat[$key]) && $flat[$key] !== '') return $flat[$key];
    $parts = explode('.', $key); $short = end($parts);
    if (isset($flat[$short]) && $flat[$short] !== '') return $flat[$short];
    // small English fallback for safe UI
    $fallbacks = [
        'banners.page_title'=>'Banners Management',
        'banners.loading'=>'Loading...',
        'banners.no_banners'=>'No banners found',
        'banners.btn_new'=>'Add Banner',
        'banners.btn_refresh'=>'Refresh',
        'banners.btn_save'=>'Save'
    ];
    return $fallbacks[$key] ?? $fallbacks[$short] ?? $short;
}

?><!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES); ?>" dir="<?php echo htmlspecialchars($direction, ENT_QUOTES); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars(gs('banners.page_title', $flatStrings), ENT_QUOTES); ?></title>

  <style id="db-theme-vars">:root {
<?php foreach ($cssVars as $var => $val): ?>
  <?php echo $var; ?>: <?php echo htmlspecialchars($val, ENT_QUOTES); ?>;
<?php endforeach; ?>
}</style>

  <link rel="stylesheet" href="/admin/assets/css/pages/banners.css">
</head>
<body>
  <div id="adminBanners" style="max-width:1200px;margin:16px auto;padding:12px;">
    <h2><?php echo htmlspecialchars(gs('banners.page_title', $flatStrings), ENT_QUOTES); ?></h2>

    <?php if (!$canManage): ?>
      <div class="alert"><?php echo htmlspecialchars(gs('banners.no_permission_notice', $flatStrings), ENT_QUOTES); ?></div>
    <?php else: ?>

      <div class="tools" style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
        <input id="bannerSearch" type="search" placeholder="<?php echo htmlspecialchars(gs('banners.search_placeholder', $flatStrings), ENT_QUOTES); ?>" style="flex:1;padding:8px;border:1px solid var(--theme-border);border-radius:6px;">
        <button id="btnRefresh" class="btn primary"><?php echo htmlspecialchars(gs('banners.btn_refresh', $flatStrings), ENT_QUOTES); ?></button>
        <button id="btnNew" class="btn primary"><?php echo htmlspecialchars(gs('banners.btn_new', $flatStrings), ENT_QUOTES); ?></button>
        <div style="margin-left:auto;"><?php echo htmlspecialchars(gs('banners.total', $flatStrings), ENT_QUOTES); ?>: <span id="bannersCount">-</span></div>
      </div>

      <div id="bannersStatus" class="status" style="min-height:20px;margin-bottom:8px;"></div>

      <div class="table-wrap" style="overflow:auto;">
        <table id="bannersTable" style="width:100%;border-collapse:collapse;">
          <thead>
            <tr>
              <th><?php echo htmlspecialchars(gs('banners.id', $flatStrings), ENT_QUOTES); ?></th>
              <th><?php echo htmlspecialchars(gs('banners.title', $flatStrings), ENT_QUOTES); ?></th>
              <th><?php echo htmlspecialchars(gs('banners.image', $flatStrings), ENT_QUOTES); ?></th>
              <th><?php echo htmlspecialchars(gs('banners.position', $flatStrings), ENT_QUOTES); ?></th>
              <th><?php echo htmlspecialchars(gs('banners.is_active', $flatStrings), ENT_QUOTES); ?></th>
              <th><?php echo htmlspecialchars(gs('banners.actions', $flatStrings), ENT_QUOTES); ?></th>
            </tr>
          </thead>
          <tbody id="bannersTbody">
            <tr><td colspan="6" style="padding:12px;text-align:center;color:#666;"><?php echo htmlspecialchars(gs('banners.loading', $flatStrings), ENT_QUOTES); ?></td></tr>
          </tbody>
        </table>
      </div>

      <div id="bannerFormWrap" class="form-wrap" style="display:none;margin-top:18px;">
        <h3 id="formTitle"><?php echo htmlspecialchars(gs('banners.add_banner', $flatStrings), ENT_QUOTES); ?></h3>
        <form id="bannerForm" autocomplete="off" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <input type="hidden" id="bannerId" name="id" value="">
          <input type="hidden" id="banner_translations" name="translations" value="">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="action" value="save">

          <div style="grid-column:1 / span 2;">
            <label for="bannerTitle"><?php echo htmlspecialchars(gs('banners.title', $flatStrings), ENT_QUOTES); ?></label>
            <input id="bannerTitle" name="title" type="text" required style="width:100%;padding:8px;border:1px solid var(--theme-border);border-radius:6px;">
          </div>

          <div>
            <label for="bannerImageUrl"><?php echo htmlspecialchars(gs('banners.image_url', $flatStrings), ENT_QUOTES); ?></label>
            <input id="bannerImageUrl" name="image_url" type="text" style="width:100%;padding:8px;border:1px solid var(--theme-border);border-radius:6px;">
            <input id="banner_image_file" name="image_file" type="file" accept="image/*" style="margin-top:6px;">
            <small id="imageUploadStatus" style="display:block;color:#666;"></small>
            <div id="banner_image_preview" style="margin-top:6px;"></div>
          </div>

          <div>
            <label for="banner_mobile_image_url"><?php echo htmlspecialchars(gs('banners.label_image_mobile', $flatStrings) ?? gs('banners.image', $flatStrings), ENT_QUOTES); ?></label>
            <input id="banner_mobile_image_url" name="mobile_image_url" type="text" style="width:100%;padding:8px;border:1px solid var(--theme-border);border-radius:6px;">
            <input id="banner_mobile_image_file" name="mobile_image_file" type="file" accept="image/*" style="margin-top:6px;">
            <small id="mobileImageUploadStatus" style="display:block;color:#666;"></small>
            <div id="banner_mobile_image_preview" style="margin-top:6px;"></div>
          </div>

          <div>
            <label for="bannerPosition"><?php echo htmlspecialchars(gs('banners.position', $flatStrings), ENT_QUOTES); ?></label>
            <select id="bannerPosition" name="position" style="width:100%;padding:8px;border:1px solid var(--theme-border);border-radius:6px;">
              <option value="homepage_main"><?php echo htmlspecialchars(gs('banners.position_homepage_main', $flatStrings), ENT_QUOTES); ?></option>
              <option value="homepage_secondary"><?php echo htmlspecialchars(gs('banners.position_homepage_secondary', $flatStrings), ENT_QUOTES); ?></option>
              <option value="category"><?php echo htmlspecialchars(gs('banners.position_category', $flatStrings), ENT_QUOTES); ?></option>
              <option value="product"><?php echo htmlspecialchars(gs('banners.position_product', $flatStrings), ENT_QUOTES); ?></option>
              <option value="custom"><?php echo htmlspecialchars(gs('banners.position_custom', $flatStrings), ENT_QUOTES); ?></option>
            </select>
          </div>

          <div>
            <label><?php echo htmlspecialchars(gs('banners.is_active', $flatStrings), ENT_QUOTES); ?></label>
            <select id="bannerIsActive" name="is_active" style="width:100%;padding:8px;border:1px solid var(--theme-border);border-radius:6px;">
              <option value="1"><?php echo htmlspecialchars(gs('banners.yes', $flatStrings), ENT_QUOTES); ?></option>
              <option value="0"><?php echo htmlspecialchars(gs('banners.no', $flatStrings), ENT_QUOTES); ?></option>
            </select>
          </div>

          <div style="grid-column:1 / span 2;text-align:right;">
            <button type="button" id="btnCancelForm" class="btn"><?php echo htmlspecialchars(gs('banners.btn_cancel', $flatStrings), ENT_QUOTES); ?></button>
            <button type="submit" id="bannerSaveBtn" class="btn primary"><?php echo htmlspecialchars(gs('banners.btn_save', $flatStrings), ENT_QUOTES); ?></button>
          </div>
        </form>
      </div>

    <?php endif; ?>
  </div>

  <script>
    // expose payloads
    window.ADMIN_UI = <?php echo json_encode($ADMIN_UI_PAYLOAD, JSON_UNESCAPED_UNICODE); ?> || {};
    window.I18N = window.ADMIN_UI.strings || {};
    window.I18N_FLAT = <?php echo json_encode($flatStrings, JSON_UNESCAPED_UNICODE); ?> || {};
    window.USER_INFO = window.ADMIN_UI.user || {};
    window.THEME = window.ADMIN_UI.theme || {};
    window.LANG = '<?php echo htmlspecialchars($lang, ENT_QUOTES); ?>';
    window.DIRECTION = '<?php echo htmlspecialchars($direction, ENT_QUOTES); ?>';
    window.CSRF_TOKEN = '<?php echo $csrf; ?>';
    window.API_BANNERS = '<?php echo addslashes($apiBanners); ?>';
    <?php if (!empty($apiUpload)): ?>
    window.ADMIN_UI = window.ADMIN_UI || {};
    window.ADMIN_UI.api = window.ADMIN_UI.api || {};
    window.ADMIN_UI.api.upload_image = '<?php echo addslashes($apiUpload); ?>';
    <?php endif; ?>

    (function(){ try{ if(window.DIRECTION) document.documentElement.setAttribute('dir', window.DIRECTION); if(window.LANG) document.documentElement.setAttribute('lang', window.LANG); }catch(e){} })();
  </script>

  <script src="/admin/assets/js/pages/banners.js" defer></script>
</body>
</html>