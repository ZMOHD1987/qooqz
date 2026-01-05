<?php
/**
 * admin/fragments/roles.php
 * Admin UI fragment to manage roles
 * - Uses api/bootstrap_admin_ui.php for translations/theme/user
 */

declare(strict_types=1);

$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) { try { require_once $bootstrap; } catch (Throwable $e) {} }

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$user = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];

// Explicitly load role_permissions translations (which includes roles)
$languagesBaseDir = dirname(__DIR__, 2) . '/languages';
$rolePermsLangFile = $languagesBaseDir . '/role_permissions/' . $lang . '.json';
if (file_exists($rolePermsLangFile)) {
    $rolePermsJson = @file_get_contents($rolePermsLangFile);
    if ($rolePermsJson) {
        if (substr($rolePermsJson, 0, 3) === "\xEF\xBB\xBF") {
            $rolePermsJson = preg_replace('/^\x{FEFF}/u', '', $rolePermsJson);
        }
        $rolePermsData = @json_decode($rolePermsJson, true);
        if (is_array($rolePermsData) && isset($rolePermsData['strings'])) {
            $strings = array_merge_recursive($strings, $rolePermsData['strings']);
        }
    }
}

// flatten strings for convenience
function flatten_strings_roles(array $src): array {
    $out = [];
    $stack = [['prefix'=>'','node'=>$src]];
    while ($stack) {
        $it = array_pop($stack);
        $prefix = $it['prefix']; $node = $it['node'];
        if (!is_array($node)) continue;
        foreach ($node as $k => $v) {
            $key = $prefix === '' ? $k : ($prefix . '.' . $k);
            if (is_array($v)) $stack[] = ['prefix'=>$key,'node'=>$v];
            else { $out[$key] = (string)$v; $parts = explode('.',$key); $short = end($parts); if (!isset($out[$short])) $out[$short] = (string)$v; }
        }
    }
    return $out;
}
$flat = flatten_strings_roles($strings);
function gs_roles($k, $flat, $d='') { if (isset($flat[$k]) && $flat[$k] !== '') return $flat[$k]; $parts=explode('.',$k); $short=end($parts); if (isset($flat[$short]) && $flat[$short] !== '') return $flat[$short]; return $d !== '' ? $d : $short; }

$canManage = false;
if (!empty($user['role_id']) && (int)$user['role_id'] === 1) $canManage = true;
if (!$canManage && !empty($user['roles']) && is_array($user['roles']) && (in_array('super_admin',$user['roles'],true)||in_array('admin',$user['roles'],true))) $canManage = true;
if (!$canManage && !empty($user['permissions']) && is_array($user['permissions']) && in_array('manage_roles',$user['permissions'],true)) $canManage = true;

if (empty($_SESSION['csrf_token'])) { try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); } }
$csrf = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES);

// API path
$apiPath = '/api/controllers/RolesController.php';
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES); ?>" dir="<?php echo htmlspecialchars($direction, ENT_QUOTES); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars(gs_roles('roles.title',$flat,'Roles Management'), ENT_QUOTES); ?></title>
  
  <style>
  /* Inject theme colors as CSS variables from database */
  :root {
    <?php
    if (!empty($theme['colors_map'])) {
      foreach ($theme['colors_map'] as $key => $value) {
        echo "--theme-" . $key . ": " . htmlspecialchars($value, ENT_QUOTES) . ";\n    ";
      }
    }
    if (!empty($theme['buttons_map']['primary'])) {
      $primary = $theme['buttons_map']['primary'];
      echo "--btn-primary-bg: " . htmlspecialchars($primary['background_color'], ENT_QUOTES) . ";\n    ";
      echo "--btn-primary-text: " . htmlspecialchars($primary['text_color'], ENT_QUOTES) . ";\n    ";
      echo "--btn-primary-hover: " . htmlspecialchars($primary['hover_background_color'], ENT_QUOTES) . ";\n    ";
    }
    ?>
  }
  </style>
  
  <link rel="stylesheet" href="/admin/assets/css/pages/roles.css">
</head>
<body>
  <div id="adminRoles" style="max-width:1200px;margin:16px auto;padding:12px;">
    <h2><?php echo htmlspecialchars(gs_roles('roles.title',$flat,'Roles Management'), ENT_QUOTES); ?></h2>

    <?php if (!$canManage): ?>
      <div class="alert"><?php echo htmlspecialchars(gs_roles('roles.no_permission_notice',$flat,'You do not have permission to manage roles'), ENT_QUOTES); ?></div>
    <?php endif; ?>

    <div class="tools" style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
      <input id="rolesSearch" type="search" placeholder="<?php echo htmlspecialchars(gs_roles('roles.search_placeholder',$flat,'Search...'), ENT_QUOTES); ?>" style="flex:1;padding:8px;border:1px solid var(--theme-border,#ddd);border-radius:6px;">
      <button id="rolesRefresh" class="btn primary"><?php echo htmlspecialchars(gs_roles('roles.btn_refresh',$flat,'Refresh'), ENT_QUOTES); ?></button>
      <?php if ($canManage): ?>
        <button id="rolesNew" class="btn primary"><?php echo htmlspecialchars(gs_roles('roles.btn_new',$flat,'Add New Role'), ENT_QUOTES); ?></button>
      <?php endif; ?>
    </div>

    <div id="rolesStatus" class="status" style="min-height:22px;margin-bottom:8px;"><?php echo htmlspecialchars(gs_roles('roles.loading',$flat,'Loading...'), ENT_QUOTES); ?></div>

    <div class="table-wrap">
      <table id="rolesTable" style="width:100%;border-collapse:collapse;" dir="<?php echo $direction; ?>">
        <thead>
          <tr>
            <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);width:50px;"><?php echo htmlspecialchars(gs_roles('roles.table_id',$flat,'ID'), ENT_QUOTES); ?></th>
            <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);"><?php echo htmlspecialchars(gs_roles('roles.table_key_name',$flat,'Key Name'), ENT_QUOTES); ?></th>
            <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);"><?php echo htmlspecialchars(gs_roles('roles.table_display_name',$flat,'Display Name'), ENT_QUOTES); ?></th>
            <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);text-align:<?php echo $direction === 'rtl' ? 'left' : 'right'; ?>;"><?php echo htmlspecialchars(gs_roles('roles.table_actions',$flat,'Actions'), ENT_QUOTES); ?></th>
          </tr>
        </thead>
        <tbody id="rolesTbody">
          <tr><td colspan="4" style="padding:12px;text-align:center;color:#666;"><?php echo htmlspecialchars(gs_roles('roles.loading',$flat,'Loading...'), ENT_QUOTES); ?></td></tr>
        </tbody>
      </table>
    </div>

    <div id="rolesPager" style="margin-top:12px;"></div>

    <!-- Form -->
    <?php if ($canManage): ?>
    <div id="rolesFormWrap" class="form-wrap" style="display:none;margin-top:14px;padding:12px;border-radius:8px;border:1px solid var(--theme-border,#e5e7eb);background:var(--theme-card,#fff);">
      <h3 id="rolesFormTitle"><?php echo htmlspecialchars(gs_roles('roles.form_title',$flat,'Add New Role'), ENT_QUOTES); ?></h3>
      <form id="rolesForm" autocomplete="off">
        <input type="hidden" id="rolesId" name="id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <label for="rolesKeyName"><?php echo htmlspecialchars(gs_roles('roles.label_key_name',$flat,'Key Name'), ENT_QUOTES); ?></label>
            <input type="text" id="rolesKeyName" name="key_name" required placeholder="<?php echo htmlspecialchars(gs_roles('roles.placeholder_key_name',$flat,'e.g., admin'), ENT_QUOTES); ?>" style="width:100%;padding:8px;border:1px solid var(--theme-border,#e5e7eb);border-radius:6px;">
          </div>
          <div>
            <label for="rolesDisplayName"><?php echo htmlspecialchars(gs_roles('roles.label_display_name',$flat,'Display Name'), ENT_QUOTES); ?></label>
            <input type="text" id="rolesDisplayName" name="display_name" required placeholder="<?php echo htmlspecialchars(gs_roles('roles.placeholder_display_name',$flat,'e.g., Administrator'), ENT_QUOTES); ?>" style="width:100%;padding:8px;border:1px solid var(--theme-border,#e5e7eb);border-radius:6px;">
          </div>
          <div style="grid-column:1 / span 2;text-align:right;margin-top:8px;">
            <button type="button" id="rolesCancel" class="btn"><?php echo htmlspecialchars(gs_roles('roles.btn_cancel',$flat,'Cancel'), ENT_QUOTES); ?></button>
            <button type="submit" id="rolesSave" class="btn primary"><?php echo htmlspecialchars(gs_roles('roles.btn_save',$flat,'Save'), ENT_QUOTES); ?></button>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>

  </div>

<script>
window.ADMIN_UI = <?php echo json_encode($ADMIN_UI_PAYLOAD, JSON_UNESCAPED_UNICODE); ?> || {};
window.I18N_FLAT = <?php echo json_encode($flat, JSON_UNESCAPED_UNICODE); ?> || {};
window.USER_INFO = window.ADMIN_UI.user || {};
window.THEME = window.ADMIN_UI.theme || {};
window.CSRF_TOKEN = '<?php echo $csrf; ?>';
window.API_ROLES = '<?php echo addslashes($apiPath); ?>';
window.DIRECTION = '<?php echo $direction; ?>';
</script>

<script src="/admin/assets/js/pages/roles.js" defer></script>
</body>
</html>
