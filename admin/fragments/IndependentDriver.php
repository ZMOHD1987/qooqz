<?php
// admin/fragments/IndependentDriver.php
// Fragment for Independent Drivers — use translations provided by bootstrap.php (single source).
// - DOES NOT require i18n.php directly; prefers translations exposed by bootstrap (/htdocs/api/bootstrap.php).
// - Safe include wrapper for bootstrap/auth to avoid leaking JSON into HTML.
// - Exposes merged ADMIN_UI to client and falls back to page file only if bootstrap did not provide translations.
// - Keeps existing protections and UI output.
//
// Save as UTF-8 without BOM.

declare(strict_types=1);

// ----------------- Diagnostic shutdown logger -----------------
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log = __DIR__ . '/../../api/error_debug.log';
        $msg = "[" . date('c') . "] SHUTDOWN ERROR: {$err['message']} in {$err['file']}:{$err['line']}" . PHP_EOL;
        @file_put_contents($log, $msg, FILE_APPEND);
    }
});

// ----------------- Helpers -----------------
function safe_json_encode($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($s === false) {
        array_walk_recursive($v, function (&$item) { if (!is_string($item)) return; $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8'); });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
    return $s;
}

// ----------------- Safe include of bootstrap/auth (single source) -----------------
$bootstrapPath = __DIR__ . '/../../api/bootstrap.php';
$authHelper    = __DIR__ . '/../../api/helpers/auth_helper.php';

$isApiRequest = false;
$uri = $_SERVER['REQUEST_URI'] ?? '';
$xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

if (
    (!empty($uri) && function_exists('str_starts_with') && str_starts_with($uri, '/api/'))
    || ($uri === '' && !empty($_SERVER['SCRIPT_NAME']) && function_exists('str_starts_with') && str_starts_with((string)$_SERVER['SCRIPT_NAME'], '/api/'))
    || $xhr
    || $acceptJson
) {
    $isApiRequest = true;
}

$__AUTH_INCLUDE_ERROR = null;
$__BOOTSTRAP_EMITTED_JSON = null;
$bootstrap_emitted_json = false;
$bootstrap_json_payload = null;

if ($isApiRequest) {
    if (is_readable($bootstrapPath)) {
        @require_once $bootstrapPath;
    }
} else {
    if (is_readable($authHelper)) {
        // include auth_helper safely
        $authOut = '';
        $authErr = null;
        try {
            ob_start();
            $prev = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
            try {
                require_once $authHelper;
            } finally {
                if ($prev !== null) set_error_handler($prev);
            }
            $authOut = (string) @ob_get_clean();
        } catch (Throwable $e) {
            $authOut = (string) @ob_get_clean();
            $authErr = $e;
        }

        if ($authErr) {
            @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[".date('c')."] auth_helper include failed: " . $authErr->getMessage() . PHP_EOL . $authErr->getTraceAsString() . PHP_EOL, FILE_APPEND);
            $__AUTH_INCLUDE_ERROR = $authErr->getMessage();
        } else {
            if (strlen(trim($authOut))) {
                @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[".date('c')."] auth_helper emitted output when included by " . __FILE__ . " : " . substr($authOut,0,1024) . PHP_EOL, FILE_APPEND);
            }
        }
    } elseif (is_readable($bootstrapPath)) {
        // last-resort include bootstrap but capture output
        ob_start();
        @require_once $bootstrapPath;
        $buf = (string) @ob_get_clean();
        $trim = ltrim($buf);
        if (strlen($trim) && ($trim[0] === '{' || $trim[0] === '[')) {
            $decoded = @json_decode($trim, true);
            if (is_array($decoded) || is_object($decoded)) {
                $bootstrap_emitted_json = true;
                $bootstrap_json_payload = $decoded;
                @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[".date('c')."] bootstrap emitted JSON while included by " . __FILE__ . " : " . substr($trim,0,1024) . PHP_EOL, FILE_APPEND);
            } else {
                echo $buf;
            }
        } else {
            echo $buf;
        }
    }
}

if (!empty($bootstrap_emitted_json)) {
    $__BOOTSTRAP_EMITTED_JSON = $bootstrap_json_payload ?? ['message' => 'Server emitted JSON while included'];
} else {
    $__BOOTSTRAP_EMITTED_JSON = null;
}

// ----------------- session ensure -----------------
if (function_exists('start_session_safe')) {
    try { start_session_safe(); } catch (Throwable $e) { if (session_status() === PHP_SESSION_NONE) @session_start(); }
} else {
    if (session_status() === PHP_SESSION_NONE) @session_start();
}

// ----------------- get current user (safe) -----------------
function safe_get_current_user(): array {
    if (function_exists('get_current_user')) {
        try {
            $u = get_current_user();
            if (is_array($u)) return $u;
        } catch (Throwable $e) {
            @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[".date('c')."] get_current_user failed: ".$e->getMessage().PHP_EOL, FILE_APPEND);
        }
    }
    $user = [];
    try {
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $user = $_SESSION['user'];
        } elseif (!empty($_SESSION['user_id'])) {
            $user = [
                'id' => (int)($_SESSION['user_id'] ?? 0),
                'name' => $_SESSION['username'] ?? ($_SESSION['name'] ?? ''),
                'username' => $_SESSION['username'] ?? '',
                'email' => $_SESSION['email'] ?? '',
                'roles' => $_SESSION['roles'] ?? [],
                'permissions' => $_SESSION['permissions'] ?? [],
                'preferred_language' => $_SESSION['preferred_language'] ?? null,
                'role_id' => $_SESSION['role_id'] ?? ($_SESSION['role'] ?? null)
            ];
        }
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[".date('c')."] session user read failed: ".$e->getMessage().PHP_EOL, FILE_APPEND);
        $user = [];
    }
    return is_array($user) ? $user : [];
}

$user = safe_get_current_user();
if (!is_array($user)) $user = [];

// permissions
$perms = [];
if (!empty($user['permissions']) && is_array($user['permissions'])) $perms = $user['permissions'];
elseif (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) $perms = $_SESSION['permissions'];
$perms = array_values(array_unique((array)$perms));

$isAdmin = isset($user['role_id']) ? ((int)$user['role_id'] === 1) : (!empty($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1);

$canCreate = in_array('create_drivers', $perms, true) || $isAdmin;
$canEdit = in_array('edit_drivers', $perms, true) || $isAdmin;
$canDelete = in_array('delete_drivers', $perms, true) || $isAdmin;
$canApprove = in_array('approve_drivers', $perms, true) || $isAdmin;
$canManageDocs = in_array('manage_driver_docs', $perms, true) || $isAdmin;

// ----------------- assets / csrf -----------------
$cssUrl = '/admin/assets/css/pages/IndependentDriver.css';
$jsUrl  = '/admin/assets/js/pages/IndependentDriver.js';
$csrf = $_SESSION['csrf_token'] ?? '';
if (empty($csrf)) { $csrf = bin2hex(random_bytes(16)); $_SESSION['csrf_token'] = $csrf; }

// ----------------- Prefer translations from bootstrap (/api/bootstrap.php) -----------------
// The bootstrap (bootstrap_admin_ui.php or bootstrap.php) SHOULD populate $ADMIN_UI_PAYLOAD with:
// [ 'lang' => 'xx', 'direction' => 'ltr|rtl', 'strings' => [...], 'user' => [...], 'csrf_token' => '...' ]
// Here we prefer that source as the single source of truth for translations.
$final_admin_ui = null;
if (isset($ADMIN_UI_PAYLOAD) && is_array($ADMIN_UI_PAYLOAD)) {
    $final_admin_ui = $ADMIN_UI_PAYLOAD;
} else {
    // If bootstrap didn't provide ADMIN_UI_PAYLOAD, construct a minimal one from session/user
    $lang = $user['preferred_language'] ?? ($_SESSION['preferred_language'] ?? 'en');
    $direction = in_array($lang, ['ar','he','fa','ur'], true) ? 'rtl' : 'ltr';
    $final_admin_ui = [
        'lang' => $lang,
        'direction' => $direction,
        'strings' => [], // empty unless bootstrapped or page file fallback used below
        'user' => [
            'id' => (int)($user['id'] ?? $_SESSION['user_id'] ?? 0),
            'username' => $user['username'] ?? $_SESSION['username'] ?? ($user['name'] ?? 'guest'),
            'permissions' => $perms
        ],
        'csrf_token' => $_SESSION['csrf_token'] ?? $csrf
    ];
}

// If bootstrap did not provide strings for this page, optionally attempt a conservative fallback:
// (This preserves your previous behavior but only used when bootstrap offers nothing.)
if (empty($final_admin_ui['strings']) || !is_array($final_admin_ui['strings'])) {
    $final_admin_ui['strings'] = [];
    // fallback to page file (only if you want to support it; otherwise remove this block to rely solely on bootstrap)
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $pageFile = $docRoot . '/languages/IndependentDriver/' . ($final_admin_ui['lang'] ?? 'en') . '.json';
    if (is_readable($pageFile)) {
        $content = @file_get_contents($pageFile);
        $decoded = @json_decode($content, true);
        if (is_array($decoded)) {
            // accept either { "strings": {...} } or flat object
            $strings = isset($decoded['strings']) && is_array($decoded['strings']) ? $decoded['strings'] : $decoded;
            $final_admin_ui['strings'] = $strings;
        }
    }
}

// ensure keys exist
if (!isset($final_admin_ui['lang'])) $final_admin_ui['lang'] = 'en';
if (!isset($final_admin_ui['direction'])) $final_admin_ui['direction'] = in_array($final_admin_ui['lang'], ['ar','he','fa','ur'], true) ? 'rtl' : 'ltr';
if (!isset($final_admin_ui['csrf_token'])) $final_admin_ui['csrf_token'] = $csrf;
if (!isset($final_admin_ui['strings']) || !is_array($final_admin_ui['strings'])) $final_admin_ui['strings'] = [];

// prepare JSON for embedding
$ADMIN_UI_JSON = safe_json_encode($final_admin_ui);

// ----------------- rendering decisions (standalone vs embedded) -----------------
$scriptFilename = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$thisFilename   = basename(__FILE__);
$directRequest  = ($scriptFilename === $thisFilename);
$isAjax         = $xhr;
$forceStandalone = !empty($_GET['_standalone']) || !empty($_GET['standalone']) || !empty($_GET['embed']);
$shouldRenderFull = false;
if ($directRequest) {
    if ($forceStandalone) $shouldRenderFull = true;
    elseif ($isAjax) $shouldRenderFull = false;
    else $shouldRenderFull = true;
}

// start buffering if rendering full page
$headerIncluded = false;
if ($shouldRenderFull && !ob_get_level()) ob_start();

// include header if needed
if ($shouldRenderFull) {
    $headerPath = __DIR__ . '/../includes/header.php';
    if (is_readable($headerPath)) {
        require_once $headerPath;
        $headerIncluded = true;
    } else {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        $csrf_token = $_SESSION['csrf_token'];
        ?><!doctype html>
        <html lang="<?php echo htmlspecialchars($final_admin_ui['lang'], ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars($final_admin_ui['direction'], ENT_QUOTES, 'UTF-8'); ?>">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title><?php echo htmlspecialchars($final_admin_ui['strings']['page']['title'] ?? 'Independent Drivers', ENT_QUOTES, 'UTF-8'); ?></title>
          <link rel="stylesheet" href="/admin/assets/css/admin.css">
        </head>
        <body class="admin">
        <header class="admin-header"><a class="brand" href="/admin/">Admin</a></header>
        <div class="admin-layout">
          <aside id="adminSidebar" class="admin-sidebar" aria-hidden="true"></aside>
          <div class="sidebar-backdrop" aria-hidden="true"></div>
          <main id="adminMainContent" class="admin-main" role="main">
        <?php
        $headerIncluded = true;
    }
}

// ---------- Fragment HTML ----------
?>
<meta data-page="independent_driver" data-assets-js="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>" data-assets-css="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">

<!-- Inject ADMIN_UI early (translations from bootstrap preferred) -->
<script>
(function(){
  try {
    window.ADMIN_UI = window.ADMIN_UI || <?php echo $ADMIN_UI_JSON; ?>;
    window.ADMIN_LANG = window.ADMIN_UI.lang || '<?php echo addslashes($final_admin_ui['lang']); ?>';
    window.ADMIN_DIR = window.ADMIN_UI.direction || '<?php echo addslashes($final_admin_ui['direction']); ?>';
    window.CSRF_TOKEN = window.ADMIN_UI.csrf_token || '<?php echo addslashes($final_admin_ui['csrf_token']); ?>';
    document.documentElement.lang = window.ADMIN_LANG;
    document.documentElement.dir = window.ADMIN_DIR;
    if (console && console.info) console.info('ADMIN_UI injected for IndependentDriver', window.ADMIN_LANG, window.ADMIN_DIR);
  } catch(e){
    console.error('ADMIN_UI inject failed', e);
    window.ADMIN_UI = window.ADMIN_UI || {};
  }
})();
</script>

<div id="independent-driver-app" class="independent-driver <?php echo ($final_admin_ui['direction'] === 'rtl') ? 'rtl' : ''; ?>"
     data-user-id="<?php echo htmlspecialchars((string)($user['id'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
     data-role-id="<?php echo htmlspecialchars((string)($user['role_id'] ?? ($_SESSION['role_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
     data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>"
     data-permissions="<?php echo htmlspecialchars(json_encode($perms, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
     data-csrf="<?php echo htmlspecialchars($final_admin_ui['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
     dir="<?php echo htmlspecialchars($final_admin_ui['direction'], ENT_QUOTES, 'UTF-8'); ?>">

  <div class="idrv-topbar">
    <div class="idrv-user">
      <strong><?php echo htmlspecialchars($final_admin_ui['user']['username'] ?? ($user['name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></strong>
      <?php if (!empty($user['roles'])): ?>
        <div class="idrv-perms"><?php echo htmlspecialchars(implode(', ', (array)$user['roles']), ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
    </div>

    <div class="idrv-controls">
      <input id="idrv-search" data-i18n-placeholder="page.search_placeholder" placeholder="<?php echo htmlspecialchars($final_admin_ui['strings']['page']['search_placeholder'] ?? 'Search by name, phone, email or license #', ENT_QUOTES, 'UTF-8'); ?>" />
      <select id="idrv-filter-status">
        <option value=""><?php echo htmlspecialchars($final_admin_ui['strings']['page']['status_all'] ?? 'All', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="active" data-i18n-key="page.active"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['active'] ?? 'Active', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="inactive" data-i18n-key="page.inactive"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['inactive'] ?? 'Inactive', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="busy" data-i18n-key="page.busy"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['busy'] ?? 'Busy', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="offline" data-i18n-key="page.offline"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['offline'] ?? 'Offline', ENT_QUOTES, 'UTF-8'); ?></option>
      </select>
      <select id="idrv-filter-vehicle">
        <option value=""><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_all'] ?? 'All vehicles', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="motorcycle">motorcycle</option>
        <option value="car">car</option>
        <option value="van">van</option>
        <option value="truck">truck</option>
      </select>
      <?php if ($canCreate): ?>
        <button id="idrv-create-btn" class="btn btn-primary" data-require-perm="create_drivers" data-i18n-value="page.create_button"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['create_button'] ?? 'Create', ENT_QUOTES, 'UTF-8'); ?></button>
      <?php endif; ?>
    </div>
  </div>

  <div class="idrv-grid">
    <form id="idrv-form" class="idrv-form" enctype="multipart/form-data" autocomplete="off" novalidate data-require-perm="create_drivers|edit_drivers">
      <input type="hidden" id="idrv-id" name="id" value="" />
      <?php if (!empty($final_admin_ui['csrf_token'])): ?><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($final_admin_ui['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>

      <div class="idrv-form-inner">
        <div class="idrv-col">
          <label for="idrv-name" data-i18n-key="page.full_name"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['full_name'] ?? 'Full Name', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-name" name="name" type="text" required />

          <label for="idrv-phone" data-i18n-key="page.phone"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['phone'] ?? 'Phone', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-phone" name="phone" type="text" required />

          <label for="idrv-email" data-i18n-key="page.email"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['email'] ?? 'Email', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-email" name="email" type="email" />

          <label for="idrv-vehicle_type" data-i18n-key="page.vehicle_type"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_type'] ?? 'Vehicle Type', ENT_QUOTES, 'UTF-8'); ?></label>
          <select id="idrv-vehicle_type" name="vehicle_type" required>
            <option value=""><?php echo htmlspecialchars('--', ENT_QUOTES, 'UTF-8'); ?></option>
            <option value="motorcycle">motorcycle</option>
            <option value="car">car</option>
            <option value="van">van</option>
            <option value="truck">truck</option>
          </select>

          <label for="idrv-vehicle_number" data-i18n-key="page.vehicle_number"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_number'] ?? 'Vehicle #', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-vehicle_number" name="vehicle_number" type="text" />

          <label for="idrv-license_number" data-i18n-key="page.license_number"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['license_number'] ?? 'License #', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-license_number" name="license_number" type="text" required />
        </div>

        <div class="idrv-col idrv-col-right">
          <label data-i18n-key="page.license_photo"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['license_photo'] ?? 'License Photo', ENT_QUOTES, 'UTF-8'); ?></label>
          <?php if ($canManageDocs): ?>
            <input id="idrv-license_photo" name="license_photo" type="file" accept="image/*" data-require-perm="manage_driver_docs">
          <?php else: ?>
            <div class="note" data-hide-without-perm="manage_driver_docs"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['no_permission_docs'] ?? 'No permission to upload documents', ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <div id="idrv-license-preview" class="idrv-preview"></div>

          <label data-i18n-key="page.id_photo"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['id_photo'] ?? 'ID Photo', ENT_QUOTES, 'UTF-8'); ?></label>
          <?php if ($canManageDocs): ?>
            <input id="idrv-id_photo" name="id_photo" type="file" accept="image/*" data-require-perm="manage_driver_docs">
          <?php else: ?>
            <div class="note" data-hide-without-perm="manage_driver_docs"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['no_permission_docs'] ?? 'No permission to upload documents', ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <div id="idrv-id-preview" class="idrv-preview"></div>

          <label for="idrv-status" data-i18n-key="page.status"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['status'] ?? 'Status', ENT_QUOTES, 'UTF-8'); ?></label>
          <select id="idrv-status" name="status" <?php echo $canApprove ? '' : 'disabled'; ?> data-require-perm="approve_drivers">
            <option value="active"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['active'] ?? 'Active', ENT_QUOTES, 'UTF-8'); ?></option>
            <option value="inactive"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['inactive'] ?? 'Inactive', ENT_QUOTES, 'UTF-8'); ?></option>
            <option value="busy"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['busy'] ?? 'Busy', ENT_QUOTES, 'UTF-8'); ?></option>
            <option value="offline"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['offline'] ?? 'Offline', ENT_QUOTES, 'UTF-8'); ?></option>
          </select>

          <div class="idrv-form-actions">
            <button type="button" id="idrv-save" class="btn btn-primary" <?php echo ($canEdit || $canCreate) ? '' : 'disabled'; ?> data-i18n-value="page.save"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['save'] ?? 'Save', ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" id="idrv-reset" class="btn" data-i18n-value="page.reset"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['reset'] ?? 'Reset', ENT_QUOTES, 'UTF-8'); ?></button>
          </div>

          <div id="idrv-form-message" class="idrv-form-message" aria-live="polite"></div>
        </div>
      </div>
    </form>

    <div class="idrv-table-wrap">
      <table id="idrv-table" class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th><?php echo htmlspecialchars($final_admin_ui['strings']['page']['name'] ?? 'Name', ENT_QUOTES, 'UTF-8'); ?></th>
            <th><?php echo htmlspecialchars($final_admin_ui['strings']['page']['phone'] ?? 'Phone', ENT_QUOTES, 'UTF-8'); ?></th>
            <th><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_number'] ?? 'Vehicle #', ENT_QUOTES, 'UTF-8'); ?></th>
            <th><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_type'] ?? 'Vehicle Type', ENT_QUOTES, 'UTF-8'); ?></th>
            <th><?php echo htmlspecialchars($final_admin_ui['strings']['page']['license_number'] ?? 'License #', ENT_QUOTES, 'UTF-8'); ?></th>
            <th><?php echo htmlspecialchars($final_admin_ui['strings']['page']['status'] ?? 'Status', ENT_QUOTES, 'UTF-8'); ?></th>
            <th><?php echo htmlspecialchars($final_admin_ui['strings']['page']['actions'] ?? 'Actions', ENT_QUOTES, 'UTF-8'); ?></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  function safeParse(txt){ try { return JSON.parse(txt || '[]'); } catch(e){ return []; } }
  var app = document.getElementById('independent-driver-app');
  if (!app) return;
  try {
    var perms = safeParse(app.getAttribute('data-permissions') || '[]');
    window.__IndependentDriverConfig = {
      userId: app.getAttribute('data-user-id') || null,
      roleId: app.getAttribute('data-role-id') || null,
      isAdmin: app.getAttribute('data-is-admin') === '1',
      permissions: perms,
      csrf: app.getAttribute('data-csrf') || ''
    };
    try {
      sessionStorage.setItem('ADMIN_USER_CACHE', JSON.stringify({
        id: window.__IndependentDriverConfig.userId,
        role: window.__IndependentDriverConfig.roleId,
        permissions: window.__IndependentDriverConfig.permissions
      }));
    } catch(e){}
  } catch(e){
    console.error('IndependentDriver config init error', e);
    window.__IndependentDriverConfig = {userId:null, roleId:null, isAdmin:false, permissions:[], csrf:''};
  }
})();
</script>

<script>
try {
  // Expose page translations coming from bootstrap (preferred). Backwards-compatibility var too.
  window.__IndependentDriverTranslations = window.__IndependentDriverTranslations || <?php echo safe_json_encode($final_admin_ui['strings']); ?>;

  // Merge into ADMIN_UI.strings to keep one translation surface for all admin fragments
  window.ADMIN_UI = window.ADMIN_UI || {};
  window.ADMIN_UI.strings = window.ADMIN_UI.strings || {};
  (function deepMerge(dest, src){
    src = src || {};
    Object.keys(src).forEach(function(k){
      if (src[k] && typeof src[k] === 'object' && !Array.isArray(src[k])) {
        dest[k] = dest[k] || {};
        deepMerge(dest[k], src[k]);
      } else {
        dest[k] = src[k];
      }
    });
  })(window.ADMIN_UI.strings, (window.__IndependentDriverTranslations || {}));

  // ensure direction available
  if (window.ADMIN_UI.direction) {
    try { document.documentElement.dir = window.ADMIN_UI.direction; } catch(e){}
  } else if (window.ADMIN_UI && window.ADMIN_UI.lang) {
    // no explicit direction — optionally set from lang
  }
} catch(e){ console.warn('IndependentDriver translations injection failed', e); }
</script>

<script src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<?php
// If we rendered full page, capture buffer and check for accidental JSON output
if ($shouldRenderFull && $headerIncluded) {
    $out = (string) @ob_get_clean();
    $trim = ltrim($out);
    $printed = false;

    if (!empty($__AUTH_INCLUDE_ERROR)) {
        echo '<section class="container" style="padding:18px">';
        echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
        echo '<strong>Server error</strong>';
        echo '<div style="margin-top:6px;">' . htmlspecialchars((string)$__AUTH_INCLUDE_ERROR, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div></section>';
        $printed = true;
    }

    if (!$printed && !empty($__BOOTSTRAP_EMITTED_JSON)) {
        echo '<section class="container" style="padding:18px">';
        echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
        echo '<strong>خطأ في الخادم</strong>';
        echo '<div style="margin-top:6px;">' . htmlspecialchars((string)($__BOOTSTRAP_EMITTED_JSON['message'] ?? 'خطأ داخلي — راجع السجل'), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div></section>';
        $printed = true;
    } else {
        if (!$printed && strlen($trim) && ($trim[0] === '{' || $trim[0] === '[')) {
            $try = @json_decode($trim, true);
            if (is_array($try) && (isset($try['success']) || isset($try['message']))) {
                echo '<section class="container" style="padding:18px">';
                echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
                echo '<strong>Server error</strong>';
                echo '<div style="margin-top:6px;">' . htmlspecialchars($try['message'] ?? json_encode($try), ENT_QUOTES, 'UTF-8') . '</div>';
                echo '</div></section>';
                $printed = true;
            }
        }
    }

    if (!$printed) {
        echo $out;
    }
}

// include footer if header was included
if ($headerIncluded) {
    $footerPath = __DIR__ . '/../includes/footer.php';
    if (is_readable($footerPath)) {
        require_once $footerPath;
    } else {
        echo "\n</main></div></body></html>\n";
    }
}
?>