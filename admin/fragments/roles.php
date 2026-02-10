<?php
declare(strict_types=1);

/**
 * /admin/pages/roles.php
 * Production Version - Works inside and outside Dashboard
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD HEADER IF STANDALONE
// ═══════════════════════════════════════════════��════════════
if (!$isFragment) {
    require_once __DIR__ . '/../includes/header.php';
}
// ✅ CRITICAL: Check all possible sources
$payload = $GLOBALS['ADMIN_UI'] ?? $GLOBALS['ADMIN_UI_PAYLOAD'] ?? [];

if (empty($payload) && isset($ADMIN_UI_PAYLOAD)) {
    $payload = $ADMIN_UI_PAYLOAD;
}

// Last resort: build from session
if (empty($payload['user'])) {
    $payload = [
        'user' => $_SESSION['user'] ?? [],
        'lang' => $_SESSION['preferred_language'] ?? 'en',
        'direction' => 'ltr',
        'theme' => [],
        'strings' => [],
    ];
}
// ════════════════════════════════════════════════════════════
// READ FROM GLOBALS (SET BY HEADER/DASHBOARD)
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? null;
$lang = $payload['lang'] ?? 'en';
$direction = $payload['direction'] ?? 'ltr';
$theme = $payload['theme'] ?? [];
$strings = $payload['strings'] ?? [];

// Fallback to session
if (empty($user) && !empty($_SESSION['user'])) {
    $user = $_SESSION['user'];
}

// Load role_permissions translations
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

// Flatten strings
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

function gs_roles($k, $flat, $d='') { 
    if (isset($flat[$k]) && $flat[$k] !== '') return $flat[$k]; 
    $parts=explode('.',$k); 
    $short=end($parts); 
    if (isset($flat[$short]) && $flat[$short] !== '') return $flat[$short]; 
    return $d !== '' ? $d : $short; 
}

// Check permissions
$permissions = $user['permissions'] ?? $_SESSION['permissions'] ?? [];
$roles = $user['roles'] ?? $_SESSION['roles'] ?? [];

if (!is_array($permissions)) $permissions = [];
if (!is_array($roles)) $roles = [];

$canManage = false;
if (!empty($user['role_id']) && (int)$user['role_id'] === 1) $canManage = true;
if (!$canManage && (in_array('super_admin', $roles, true) || in_array('admin', $roles, true))) $canManage = true;
if (!$canManage && in_array('manage_roles', $permissions, true)) $canManage = true;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    try { 
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); 
    } catch (Throwable $e) { 
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16)); 
    }
}
$csrf = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES);

$apiPath = '/api/roles';

?>

<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/roles.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="roles" 
      data-i18n-files="/languages/role_permissions/<?= htmlspecialchars($lang) ?>.json"
      data-assets-css="/admin/assets/css/pages/roles.css"
      data-assets-js="/admin/assets/js/pages/roles.js">

<style>
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

<div id="adminRoles" style="max-width:1200px;margin:16px auto;padding:12px">
    <h2><?= htmlspecialchars(gs_roles('roles.title',$flat,'Roles Management'), ENT_QUOTES) ?></h2>

    <?php if (!$canManage): ?>
    <div class="alert"><?= htmlspecialchars(gs_roles('roles.no_permission_notice',$flat,'You do not have permission to manage roles'), ENT_QUOTES) ?></div>
    <?php endif; ?>

    <div class="tools" style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
        <input id="rolesSearch" type="search" placeholder="<?= htmlspecialchars(gs_roles('roles.search_placeholder',$flat,'Search...'), ENT_QUOTES) ?>" style="flex:1;padding:8px;border:1px solid var(--theme-border,#ddd);border-radius:6px">
        <button id="rolesRefresh" class="btn primary"><?= htmlspecialchars(gs_roles('roles.btn_refresh',$flat,'Refresh'), ENT_QUOTES) ?></button>
        <?php if ($canManage): ?>
        <button id="rolesNew" class="btn primary"><?= htmlspecialchars(gs_roles('roles.btn_new',$flat,'Add New Role'), ENT_QUOTES) ?></button>
        <?php endif; ?>
    </div>

    <div id="rolesStatus" class="status" style="min-height:22px;margin-bottom:8px"><?= htmlspecialchars(gs_roles('roles.loading',$flat,'Loading...'), ENT_QUOTES) ?></div>

    <div class="table-wrap">
        <table id="rolesTable" style="width:100%;border-collapse:collapse" dir="<?= $direction ?>">
            <thead>
                <tr>
                    <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);width:50px"><?= htmlspecialchars(gs_roles('roles.table_id',$flat,'ID'), ENT_QUOTES) ?></th>
                    <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb)"><?= htmlspecialchars(gs_roles('roles.table_key_name',$flat,'Key Name'), ENT_QUOTES) ?></th>
                    <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb)"><?= htmlspecialchars(gs_roles('roles.table_display_name',$flat,'Display Name'), ENT_QUOTES) ?></th>
                    <th style="padding:10px;border-bottom:1px solid var(--theme-border,#e5e7eb);text-align:<?= $direction === 'rtl' ? 'left' : 'right' ?>"><?= htmlspecialchars(gs_roles('roles.table_actions',$flat,'Actions'), ENT_QUOTES) ?></th>
                </tr>
            </thead>
            <tbody id="rolesTbody">
                <tr><td colspan="4" style="padding:12px;text-align:center;color:#666"><?= htmlspecialchars(gs_roles('roles.loading',$flat,'Loading...'), ENT_QUOTES) ?></td></tr>
            </tbody>
        </table>
    </div>

    <div id="rolesPager" style="margin-top:12px"></div>

    <?php if ($canManage): ?>
    <div id="rolesFormWrap" class="form-wrap" style="display:none;margin-top:14px;padding:12px;border-radius:8px;border:1px solid var(--theme-border,#e5e7eb);background:var(--theme-card,#fff)">
        <h3 id="rolesFormTitle"><?= htmlspecialchars(gs_roles('roles.form_title',$flat,'Add New Role'), ENT_QUOTES) ?></h3>
        <form id="rolesForm" autocomplete="off">
            <input type="hidden" id="rolesId" name="id" value="">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label for="rolesKeyName"><?= htmlspecialchars(gs_roles('roles.label_key_name',$flat,'Key Name'), ENT_QUOTES) ?> *</label>
                    <input type="text" id="rolesKeyName" name="key_name" required placeholder="<?= htmlspecialchars(gs_roles('roles.placeholder_key_name',$flat,'e.g., admin'), ENT_QUOTES) ?>" style="width:100%;padding:8px;border:1px solid var(--theme-border,#e5e7eb);border-radius:6px">
                </div>
                <div>
                    <label for="rolesDisplayName"><?= htmlspecialchars(gs_roles('roles.label_display_name',$flat,'Display Name'), ENT_QUOTES) ?> *</label>
                    <input type="text" id="rolesDisplayName" name="display_name" required placeholder="<?= htmlspecialchars(gs_roles('roles.placeholder_display_name',$flat,'e.g., Administrator'), ENT_QUOTES) ?>" style="width:100%;padding:8px;border:1px solid var(--theme-border,#e5e7eb);border-radius:6px">
                </div>
                <div style="grid-column:1 / span 2;text-align:right;margin-top:8px">
                    <button type="button" id="rolesCancel" class="btn"><?= htmlspecialchars(gs_roles('roles.btn_cancel',$flat,'Cancel'), ENT_QUOTES) ?></button>
                    <button type="submit" id="rolesSave" class="btn primary"><?= htmlspecialchars(gs_roles('roles.btn_save',$flat,'Save'), ENT_QUOTES) ?></button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
window.ADMIN_UI = <?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?> || {};
window.I18N_FLAT = <?= json_encode($flat, JSON_UNESCAPED_UNICODE) ?> || {};
window.USER_INFO = window.ADMIN_UI.user || {};
window.THEME = window.ADMIN_UI.theme || {};
window.CSRF_TOKEN = '<?= $csrf ?>';
window.API_ROLES = '<?= addslashes($apiPath) ?>';
window.DIRECTION = '<?= $direction ?>';
console.log('%c[Roles] Loaded', 'color:#10b981;font-weight:bold');
console.log('User:', window.USER_INFO.username);
console.log('Can Manage:', <?= $canManage ? 'true' : 'false' ?>);
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/roles.js?v=<?= time() ?>"></script>
<script>
(function(){
    console.log('%c[Roles] Embedded mode', 'color:#3b82f6;font-weight:bold');
    let attempts = 0;
    const check = setInterval(function(){
        attempts++;
        if (window.Roles && typeof window.Roles.init === 'function') {
            clearInterval(check);
            console.log('%c[Roles] ✅ Module found!', 'color:#10b981;font-weight:bold');
            window.Roles.init().catch(err=>console.error('❌',err));
        } else if (attempts > 30) {
            clearInterval(check);
            console.error('%c[Roles] ❌ Timeout', 'color:#ef4444;font-weight:bold');
        }
    }, 200);
})();
</script>
<?php endif; ?>

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>