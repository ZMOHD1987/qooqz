<?php
declare(strict_types=1);
/**
 * admin/fragments/IndependentDriver.php
 * Final fragment that works with htdocs/api/bootstrap.php and api/bootstrap_admin_ui.php
 *
 * Adjusted: when requested via XHR (AJAX) or embedded, DO NOT render the full header/footer
 * so it can be safely injected into /admin/dashboard.php without duplicating the header.
 *
 * Place this file exactly at: admin/fragments/IndependentDriver.php (case-sensitive)
 */

if (session_status() === PHP_SESSION_NONE) @session_start();

/* -------------------------
   Logging helpers
   ------------------------- */
function _frag_log(string $m): void {
    $p = __DIR__ . '/../../api/logs/independent_drivers.log';
    @file_put_contents($p, "[".date('c')."] " . $m . PHP_EOL, FILE_APPEND | LOCK_EX);
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        _frag_log("FRAGMENT SHUTDOWN ERROR: {$err['message']} in {$err['file']}:{$err['line']}");
        @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[".date('c')."] FRAGMENT SHUTDOWN ERROR: {$err['message']} in {$err['file']}:{$err['line']}" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
});
_frag_log('FRAGMENT: loading ' . __FILE__ . ' | URI: ' . ($_SERVER['REQUEST_URI'] ?? ''));
/* -------------------------
   Utility
   ------------------------- */
function safe_json_encode($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($s === false) {
        array_walk_recursive($v, function (&$item) {
            if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($s === false) return '{}';
    }
    return $s;
}

/* -------------------------
   Prefer admin UI bootstrap
   ------------------------- */
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? null;
$adminBootstrap   = realpath(__DIR__ . '/../../api/bootstrap_admin_ui.php') ?: (__DIR__ . '/../../api/bootstrap_admin_ui.php');
$genericBootstrap = realpath(__DIR__ . '/../../api/bootstrap.php') ?: (__DIR__ . '/../../api/bootstrap.php');

// Try admin bootstrap safely (capture accidental output)
if (is_readable($adminBootstrap)) {
    try {
        ob_start();
        require_once $adminBootstrap;
        $buf = @ob_get_clean();
        if (empty($ADMIN_UI_PAYLOAD) && !empty($GLOBALS['ADMIN_UI']) && is_array($GLOBALS['ADMIN_UI'])) {
            $ADMIN_UI_PAYLOAD = $GLOBALS['ADMIN_UI'];
        }
    } catch (Throwable $e) {
        @ob_end_clean();
        _frag_log("include adminBootstrap failed: " . $e->getMessage());
    }
}

// If not available, try generic bootstrap
if (empty($ADMIN_UI_PAYLOAD) && is_readable($genericBootstrap)) {
    try {
        ob_start();
        require_once $genericBootstrap;
        $buf = @ob_get_clean();
        if (empty($ADMIN_UI_PAYLOAD) && !empty($GLOBALS['ADMIN_UI']) && is_array($GLOBALS['ADMIN_UI'])) {
            $ADMIN_UI_PAYLOAD = $GLOBALS['ADMIN_UI'];
        } else {
            // If bootstrap printed JSON, attempt decode
            $trim = ltrim((string)$buf);
            if ($trim && ($trim[0] === '{' || $trim[0] === '[')) {
                $decoded = @json_decode($trim, true);
                if (is_array($decoded)) $ADMIN_UI_PAYLOAD = $decoded;
            }
        }
    } catch (Throwable $e) {
        @ob_end_clean();
        _frag_log("include genericBootstrap failed: " . $e->getMessage());
    }
}

/* -------------------------
   Session user (authoritative)
   ------------------------- */
function session_user(): array {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user_id'])) {
        return [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'username' => $_SESSION['username'] ?? ($_SESSION['name'] ?? 'guest'),
            'email' => $_SESSION['email'] ?? '',
            'role_id' => $_SESSION['role_id'] ?? ($_SESSION['role'] ?? null),
            'permissions' => $_SESSION['permissions'] ?? [],
            'preferred_language' => $_SESSION['preferred_language'] ?? null
        ];
    }
    return [];
}
$sessionUser = session_user();

/* -------------------------
   Build final ADMIN_UI payload
   ------------------------- */
$final_admin_ui = [];
if (!empty($ADMIN_UI_PAYLOAD) && is_array($ADMIN_UI_PAYLOAD)) {
    $final_admin_ui = $ADMIN_UI_PAYLOAD;
    if (!isset($final_admin_ui['strings']) || !is_array($final_admin_ui['strings'])) $final_admin_ui['strings'] = [];
    if (!isset($final_admin_ui['user']) || !is_array($final_admin_ui['user'])) $final_admin_ui['user'] = [];
} else {
    $lang = $sessionUser['preferred_language'] ?? ($_SESSION['preferred_language'] ?? 'en');
    $direction = in_array(strtolower(substr((string)$lang,0,2)), ['ar','fa','he','ur'], true) ? 'rtl' : 'ltr';
    $final_admin_ui = [
        'lang' => $lang,
        'direction' => $direction,
        'strings' => [],
        'user' => [
            'id' => (int)($sessionUser['id'] ?? 0),
            'username' => $sessionUser['username'] ?? 'guest',
            'permissions' => $sessionUser['permissions'] ?? []
        ],
        'csrf_token' => $_SESSION['csrf_token'] ?? ( $_SESSION['csrf_token'] = bin2hex(random_bytes(16)) )
    ];
}
$final_admin_ui['session_user'] = $sessionUser;
if (empty($final_admin_ui['csrf_token'])) $final_admin_ui['csrf_token'] = $_SESSION['csrf_token'] ?? ( $_SESSION['csrf_token'] = bin2hex(random_bytes(16)) );
$final_admin_ui['api_base'] = $final_admin_ui['api_base'] ?? '/api/independent_drivers.php';
$ADMIN_UI_JSON = safe_json_encode($final_admin_ui);

/* -------------------------
   Assets & render decision
   ------------------------- */
$cssUrl = '/admin/assets/css/pages/IndependentDriver.css';
$jsUrl  = '/admin/assets/js/pages/IndependentDriver.js';

// Determine how fragment was requested
$directRequest = basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__);

// Detect XHR (Admin.fetchAndInsert sets X-Requested-With)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// If directRequest AND not XHR and not explicit embed param => render full page shell
$forceStandalone = !empty($_GET['_standalone']) || !empty($_GET['standalone']) || !empty($_GET['embed']);
$shouldRenderFull = ($directRequest && !$isAjax && !$forceStandalone);

/* Render header if we should */
if ($shouldRenderFull) {
    $headerPath = __DIR__ . '/../includes/header.php';
    if (is_readable($headerPath)) {
        require_once $headerPath;
    } else {
        // minimal shell
        ?><!doctype html>
        <html lang="<?php echo htmlspecialchars($final_admin_ui['lang']); ?>" dir="<?php echo htmlspecialchars($final_admin_ui['direction']); ?>">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title><?php echo htmlspecialchars($final_admin_ui['strings']['page']['title'] ?? 'Independent Drivers'); ?></title>
          <link rel="stylesheet" href="/admin/assets/css/admin.css">
        </head>
        <body class="admin">
        <header class="admin-header"><a class="brand" href="/admin/">Admin</a></header>
        <div class="admin-layout"><main id="adminMainContent" class="admin-main">
        <?php
    }
}

/* -------------------------
   Fragment HTML (no duplicate headers)
   ------------------------- */
?>
<meta data-page="independent_driver" data-assets-js="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>" data-assets-css="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">

<script>
  // Expose ADMIN_UI to the client; include session_user copy and api_base
  window.ADMIN_UI = window.ADMIN_UI || <?php echo $ADMIN_UI_JSON; ?>;
  if (!window.ADMIN_UI.api_base) window.ADMIN_UI.api_base = '<?php echo addslashes($final_admin_ui['api_base']); ?>';
  window.CSRF_TOKEN = window.ADMIN_UI.csrf_token || '<?php echo addslashes($final_admin_ui['csrf_token']); ?>';
  try { document.documentElement.lang = window.ADMIN_UI.lang || document.documentElement.lang; document.documentElement.dir = window.ADMIN_UI.direction || document.documentElement.dir; } catch (e) {}
</script>

<div id="independent-driver-app" class="independent-driver <?php echo ($final_admin_ui['direction'] === 'rtl') ? 'rtl' : ''; ?>"
     data-user-id="<?php echo (int)($final_admin_ui['session_user']['id'] ?? $final_admin_ui['user']['id'] ?? 0); ?>"
     data-role-id="<?php echo (int)($final_admin_ui['session_user']['role_id'] ?? $final_admin_ui['user']['role_id'] ?? $_SESSION['role_id'] ?? 0); ?>"
     data-is-admin="<?php echo (((int)($final_admin_ui['session_user']['role_id'] ?? $final_admin_ui['user']['role_id'] ?? 0)) === 1) ? '1' : '0'; ?>"
     data-permissions="<?php echo htmlspecialchars(json_encode($final_admin_ui['session_user']['permissions'] ?? $final_admin_ui['user']['permissions'] ?? []), ENT_QUOTES, 'UTF-8'); ?>"
     data-csrf="<?php echo htmlspecialchars($final_admin_ui['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

  <div class="idrv-topbar">
    <div class="idrv-user"><strong><?php echo htmlspecialchars($final_admin_ui['session_user']['username'] ?? $final_admin_ui['user']['username'] ?? 'Guest'); ?></strong></div>
    <div class="idrv-controls">
      <input id="idrv-search" placeholder="<?php echo htmlspecialchars($final_admin_ui['strings']['page']['search_placeholder'] ?? 'Search by name, phone, email or license #', ENT_QUOTES, 'UTF-8'); ?>">
      <select id="idrv-filter-status">
        <option value=""><?php echo htmlspecialchars($final_admin_ui['strings']['page']['status_all'] ?? 'All', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="active"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['active'] ?? 'Active', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="inactive"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['inactive'] ?? 'Inactive', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="busy"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['busy'] ?? 'Busy', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="offline"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['offline'] ?? 'Offline', ENT_QUOTES, 'UTF-8'); ?></option>
      </select>

      <select id="idrv-filter-vehicle">
        <option value=""><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_all'] ?? 'All vehicles', ENT_QUOTES, 'UTF-8'); ?></option>
        <option value="motorcycle">motorcycle</option>
        <option value="car">car</option>
        <option value="van">van</option>
        <option value="truck">truck</option>
      </select>

      <button id="idrv-create-btn" class="btn btn-primary"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['create_button'] ?? 'Create', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>

  <div class="idrv-grid">
    <form id="idrv-form" class="idrv-form" enctype="multipart/form-data" autocomplete="off" novalidate>
      <input type="hidden" id="idrv-id" name="id" value="">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($final_admin_ui['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

      <div class="idrv-form-inner">
        <div class="idrv-col">
          <label for="idrv-name"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['full_name'] ?? 'Full Name', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-name" name="full_name" type="text" required />

          <label for="idrv-phone"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['phone'] ?? 'Phone', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-phone" name="phone" type="text" required />

          <label for="idrv-email"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['email'] ?? 'Email', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-email" name="email" type="email" />

          <label for="idrv-vehicle_type"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_type'] ?? 'Vehicle Type', ENT_QUOTES, 'UTF-8'); ?></label>
          <select id="idrv-vehicle_type" name="vehicle_type" required>
            <option value=""><?php echo htmlspecialchars('--', ENT_QUOTES, 'UTF-8'); ?></option>
            <option value="motorcycle">motorcycle</option>
            <option value="car">car</option>
            <option value="van">van</option>
            <option value="truck">truck</option>
          </select>

          <label for="idrv-vehicle_number"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['vehicle_number'] ?? 'Vehicle #', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-vehicle_number" name="vehicle_number" type="text" />

          <label for="idrv-license_number"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['license_number'] ?? 'License #', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-license_number" name="license_number" type="text" required />
        </div>

        <div class="idrv-col idrv-col-right">
          <label><?php echo htmlspecialchars($final_admin_ui['strings']['page']['license_photo'] ?? 'License Photo', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-license_photo" name="license_photo" type="file" accept="image/*">
          <div id="idrv-license-preview" class="idrv-preview"></div>

          <label><?php echo htmlspecialchars($final_admin_ui['strings']['page']['id_photo'] ?? 'ID Photo', ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="idrv-id_photo" name="id_photo" type="file" accept="image/*">
          <div id="idrv-id-preview" class="idrv-preview"></div>

          <label for="idrv-status"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['status'] ?? 'Status', ENT_QUOTES, 'UTF-8'); ?></label>
          <select id="idrv-status" name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="busy">Busy</option>
            <option value="offline">Offline</option>
          </select>

          <div class="idrv-form-actions">
            <button type="button" id="idrv-save" class="btn btn-primary"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['save'] ?? 'Save', ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" id="idrv-reset" class="btn"><?php echo htmlspecialchars($final_admin_ui['strings']['page']['reset'] ?? 'Reset', ENT_QUOTES, 'UTF-8'); ?></button>
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
  // expose ADMIN_UI and small helpers
  window.ADMIN_UI = window.ADMIN_UI || <?php echo $ADMIN_UI_JSON; ?>;
  if (!window.ADMIN_UI.api_base) window.ADMIN_UI.api_base = '<?php echo addslashes($final_admin_ui['api_base']); ?>';
  window.CSRF_TOKEN = window.ADMIN_UI.csrf_token || '<?php echo addslashes($final_admin_ui['csrf_token']); ?>';

  // clear form helper (ensures create opens empty without duplicating headers)
  window.__IndependentDriverHelpers = window.__IndependentDriverHelpers || {};
  window.__IndependentDriverHelpers.clearFormForCreate = function() {
    try {
      var form = document.getElementById('idrv-form');
      if (!form) return;
      var idField = form.querySelector('#idrv-id');
      if (idField) idField.value = '';
      form.reset();
      var lp = document.getElementById('idrv-license-preview');
      var ip = document.getElementById('idrv-id-preview');
      if (lp) lp.innerHTML = '';
      if (ip) ip.innerHTML = '';
      Array.prototype.slice.call(form.querySelectorAll('input[name^="delete_"]')).forEach(function(i){ i.remove(); });
      var cs = form.querySelector('input[name="csrf_token"]');
      if (cs) cs.value = window.CSRF_TOKEN || '';
    } catch(e) { console.warn(e); }
  };

  // wire create button if page doesn't already
  document.addEventListener('DOMContentLoaded', function() {
    try {
      var btn = document.getElementById('idrv-create-btn');
      if (btn && typeof window.__IndependentDriverHelpers.clearFormForCreate === 'function') {
        btn.addEventListener('click', window.__IndependentDriverHelpers.clearFormForCreate, { passive: true });
      }
    } catch (e){}
  });
</script>

<script src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<?php
// close full render wrapper if used
if ($shouldRenderFull) {
    $footerPath = __DIR__ . '/../includes/footer.php';
    if (is_readable($footerPath)) require_once $footerPath;
    else echo "\n</main></div></body></html>\n";
}
?>