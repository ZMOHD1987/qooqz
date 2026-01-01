<?php
// admin/fragments/IndependentDriver.php
// Robust fragment for Independent Drivers.
// - Safe wrapper around get_current_user() so RBAC/db errors do not break page rendering
// - Output buffering when rendering full page to avoid leaking raw JSON responses into HTML
// - Uses session fallback and caches minimal user/permissions into sessionStorage via client-side script
// - Keeps previous behavior: works as standalone page (with header/footer) or AJAX/embedded fragment
//
// Save as UTF-8 without BOM.

declare(strict_types=1);

// ----------------- Diagnostic shutdown logger (temporary, helps catch 500 causes) -----------------
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log = __DIR__ . '/../../api/error_debug.log';
        $msg = "[" . date('c') . "] SHUTDOWN ERROR: {$err['message']} in {$err['file']}:{$err['line']}" . PHP_EOL;
        @file_put_contents($log, $msg, FILE_APPEND);
    }
});

// ------------------- safe include wrapper (replace any direct bootstrap include) -------------------
$bootstrapPath = __DIR__ . '/../../api/bootstrap.php';
$authHelper    = __DIR__ . '/../../api/helpers/auth_helper.php';

// detect API/XHR/JSON request
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

// markers for accidental JSON emission or auth include errors
$__BOOTSTRAP_EMITTED_JSON = null;
$bootstrap_emitted_json = false;
$bootstrap_json_payload = null;
$__AUTH_INCLUDE_ERROR = null;

// Include logic
if ($isApiRequest) {
    // API/XHR request: include bootstrap normally (it will output JSON when appropriate)
    if (is_readable($bootstrapPath)) {
        @require_once $bootstrapPath;
    }
} else {
    // Non-API: prefer auth_helper which is safe for page includes.
    if (is_readable($authHelper)) {
        // safe include: capture output and convert warnings/notices to exceptions so we can log and degrade gracefully
        $authIncludeOutput = '';
        $authIncludeError = null;
        try {
            ob_start();
            $prevErr = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                // convert to exception to catch warnings/notices
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
            try {
                require_once $authHelper;
            } finally {
                if ($prevErr !== null) set_error_handler($prevErr);
            }
            $authIncludeOutput = (string) @ob_get_clean();
        } catch (Throwable $e) {
            $authIncludeOutput = (string) @ob_get_clean();
            $authIncludeError = $e;
        }

        if ($authIncludeError) {
            $dbg = __DIR__ . '/../../api/error_debug.log';
            $msg = "[" . date('c') . "] auth_helper include failed: " . $authIncludeError->getMessage()
                . " in " . $authIncludeError->getFile() . ":" . $authIncludeError->getLine() . PHP_EOL
                . $authIncludeError->getTraceAsString() . PHP_EOL;
            @file_put_contents($dbg, $msg, FILE_APPEND);
            $__AUTH_INCLUDE_ERROR = $authIncludeError->getMessage();
        } else {
            $__AUTH_INCLUDE_ERROR = null;
            if (strlen(trim($authIncludeOutput))) {
                @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[" . date('c') . "] auth_helper emitted output when included by " . __FILE__ . " : " . substr($authIncludeOutput, 0, 1024) . PHP_EOL, FILE_APPEND);
            }
        }
    } elseif (is_readable($bootstrapPath)) {
        // As last-resort include bootstrap but capture output to avoid leaking JSON into HTML
        ob_start();
        @require_once $bootstrapPath;
        $buf = (string) @ob_get_clean();
        $trim = ltrim($buf);
        // detect JSON output (conservative: starts with { or [ and decodes)
        if (strlen($trim) && ($trim[0] === '{' || $trim[0] === '[')) {
            $decoded = @json_decode($trim, true);
            if (is_array($decoded) || is_object($decoded)) {
                // bootstrap printed JSON (likely error) — record it to log and do not echo to page
                $bootstrap_emitted_json = true;
                $bootstrap_json_payload = $decoded;
                @file_put_contents(__DIR__ . '/../../api/error_debug.log', "[" . date('c') . "] bootstrap emitted JSON while included by " . __FILE__ . " : " . substr($trim, 0, 1024) . PHP_EOL, FILE_APPEND);
            } else {
                // not valid JSON -> safe to echo as it's probably HTML; append it back
                echo $buf;
            }
        } else {
            // no JSON; safe to echo captured HTML output
            echo $buf;
        }
    }
}

if (!empty($bootstrap_emitted_json)) {
    $__BOOTSTRAP_EMITTED_JSON = $bootstrap_json_payload ?? ['message' => 'Server emitted JSON while included'];
} else {
    $__BOOTSTRAP_EMITTED_JSON = null;
}
// ---------------------------------------------------------------------------------------------------------------------

// Safe session start using helper if available
if (function_exists('start_session_safe')) {
    try { start_session_safe(); } catch (Throwable $e) { /* ignore */ }
} else {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
}

// Determine invocation context
$scriptFilename = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$thisFilename   = basename(__FILE__);
$directRequest  = ($scriptFilename === $thisFilename); // true when accessed directly
$isAjax         = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$forceStandalone = !empty($_GET['_standalone']) || !empty($_GET['standalone']) || !empty($_GET['embed']);

$shouldRenderFull = false;
if ($directRequest) {
    if ($forceStandalone) $shouldRenderFull = true;
    elseif ($isAjax) $shouldRenderFull = false;
    else $shouldRenderFull = true;
} else {
    // included via include(...) : do not render header/footer
    $shouldRenderFull = false;
}

// start buffering if rendering full page to catch accidental JSON output
if ($shouldRenderFull && !ob_get_level()) ob_start();

// Safe get_current_user wrapper — prevents uncaught exceptions from RBAC or DB problems
function safe_get_current_user(): array {
    // prefer a provided helper but guard it
    if (function_exists('get_current_user')) {
        try {
            $u = get_current_user();
            if (is_array($u)) return $u;
        } catch (Throwable $e) {
            error_log('safe_get_current_user: get_current_user threw: ' . $e->getMessage());
            // fall through to session fallback
        }
    }

    // session fallback: build minimal user snapshot safely
    $user = [];
    try {
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $user = $_SESSION['user'];
        } elseif (!empty($_SESSION['user_id'])) {
            $user = [
                'id' => (int)($_SESSION['user_id'] ?? 0),
                'name' => $_SESSION['username'] ?? ($_SESSION['name'] ?? ''),
                'username' => $_SESSION['username'] ?? ($_SESSION['name'] ?? ''),
                'email' => $_SESSION['email'] ?? '',
                'roles' => $_SESSION['roles'] ?? [],
                'permissions' => $_SESSION['permissions'] ?? [],
                'preferred_language' => $_SESSION['preferred_language'] ?? null,
                'role_id' => $_SESSION['role_id'] ?? ($_SESSION['role'] ?? null)
            ];
        }
    } catch (Throwable $e) {
        error_log('safe_get_current_user: session read failed: ' . $e->getMessage());
        $user = [];
    }

    return is_array($user) ? $user : [];
}

// Acquire user in a resilient way
$user = safe_get_current_user();
if (!is_array($user)) $user = [];

// Normalize permissions and flags (safe defaults)
$perms = [];
if (!empty($user['permissions']) && is_array($user['permissions'])) $perms = $user['permissions'];
elseif (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) $perms = $_SESSION['permissions'];
$perms = array_values(array_unique((array)$perms));

$isAdmin = false;
if (isset($user['role_id'])) $isAdmin = ((int)$user['role_id'] === 1);
elseif (!empty($_SESSION['role_id'])) $isAdmin = ((int)$_SESSION['role_id'] === 1);

$canCreate = in_array('create_drivers', $perms, true) || $isAdmin;
$canEdit = in_array('edit_drivers', $perms, true) || $isAdmin;
$canDelete = in_array('delete_drivers', $perms, true) || $isAdmin;
$canApprove = in_array('approve_drivers', $perms, true) || $isAdmin;
$canManageDocs = in_array('manage_driver_docs', $perms, true) || $isAdmin;

// assets/meta
$cssUrl = '/admin/assets/css/pages/IndependentDriver.css';
$jsUrl  = '/admin/assets/js/pages/IndependentDriver.js';
$csrf = $_SESSION['csrf_token'] ?? '';
$dir = $_SESSION['preferred_language'] ?? ($user['preferred_language'] ?? 'rtl');


// If rendering full page include header
$headerIncluded = false;
if ($shouldRenderFull) {
    $headerPath = __DIR__ . '/../includes/header.php';
    if (is_readable($headerPath)) {
        // header may call require_login() etc.
        require_once $headerPath;
        $headerIncluded = true;
    } else {
        // minimal fallback header
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        $csrf_token = $_SESSION['csrf_token'];
        ?><!doctype html>
        <html lang="ar" dir="<?php echo htmlspecialchars($dir, ENT_QUOTES, 'UTF-8'); ?>">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>Admin - Independent Drivers</title>
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

// ---------- Fragment HTML (safe) ----------
?>
<meta data-page="independent_driver" data-assets-js="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>" data-assets-css="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">

<div id="independent-driver-app" class="independent-driver <?php echo ($dir === 'rtl') ? 'rtl' : ''; ?>"
     data-user-id="<?php echo htmlspecialchars((string)($user['id'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
     data-role-id="<?php echo htmlspecialchars((string)($user['role_id'] ?? ($_SESSION['role_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
     data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>"
     data-permissions="<?php echo htmlspecialchars(json_encode($perms, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
     data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
     dir="<?php echo htmlspecialchars($dir, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="idrv-topbar">
    <div class="idrv-user">
      <strong><?php echo htmlspecialchars($user['name'] ?? ($user['username'] ?? ($user['email'] ?? 'Guest')), ENT_QUOTES, 'UTF-8'); ?></strong>
      <?php if (!empty($user['roles'])): ?>
        <div class="idrv-perms"><?php echo htmlspecialchars(implode(', ', (array)$user['roles']), ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
    </div>

    <div class="idrv-controls">
      <input id="idrv-search" placeholder="Search by name, phone, email or license #" />
      <select id="idrv-filter-status"><option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="busy">Busy</option><option value="offline">Offline</option></select>
      <select id="idrv-filter-vehicle"><option value="">All vehicles</option><option value="motorcycle">motorcycle</option><option value="car">car</option><option value="van">van</option><option value="truck">truck</option></select>
      <?php if ($canCreate): ?>
        <button id="idrv-create-btn" class="btn btn-primary" data-require-perm="create_drivers">Create</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="idrv-grid">
    <form id="idrv-form" class="idrv-form" enctype="multipart/form-data" autocomplete="off" novalidate data-require-perm="create_drivers|edit_drivers">
      <input type="hidden" id="idrv-id" name="id" value="" />
      <?php if ($csrf): ?><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>

      <div class="idrv-form-inner">
        <div class="idrv-col">
          <label for="idrv-name">Full Name</label>
          <input id="idrv-name" name="name" type="text" required />

          <label for="idrv-phone">Phone</label>
          <input id="idrv-phone" name="phone" type="text" required />

          <label for="idrv-email">Email</label>
          <input id="idrv-email" name="email" type="email" />

          <label for="idrv-vehicle_type">Vehicle Type</label>
          <select id="idrv-vehicle_type" name="vehicle_type" required>
            <option value="">--</option>
            <option value="motorcycle">motorcycle</option>
            <option value="car">car</option>
            <option value="van">van</option>
            <option value="truck">truck</option>
          </select>

          <label for="idrv-vehicle_number">Vehicle #</label>
          <input id="idrv-vehicle_number" name="vehicle_number" type="text" />

          <label for="idrv-license_number">License #</label>
          <input id="idrv-license_number" name="license_number" type="text" required />
        </div>

        <div class="idrv-col idrv-col-right">
          <label>License Photo</label>
          <?php if ($canManageDocs): ?>
            <input id="idrv-license_photo" name="license_photo" type="file" accept="image/*" data-require-perm="manage_driver_docs">
          <?php else: ?>
            <div class="note" data-hide-without-perm="manage_driver_docs">No permission to upload documents</div>
          <?php endif; ?>
          <div id="idrv-license-preview" class="idrv-preview"></div>

          <label>ID Photo</label>
          <?php if ($canManageDocs): ?>
            <input id="idrv-id_photo" name="id_photo" type="file" accept="image/*" data-require-perm="manage_driver_docs">
          <?php else: ?>
            <div class="note" data-hide-without-perm="manage_driver_docs">No permission to upload documents</div>
          <?php endif; ?>
          <div id="idrv-id-preview" class="idrv-preview"></div>

          <label for="idrv-status">Status</label>
          <select id="idrv-status" name="status" <?php echo $canApprove ? '' : 'disabled'; ?> data-require-perm="approve_drivers">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="busy">Busy</option>
            <option value="offline">Offline</option>
          </select>

          <div class="idrv-form-actions">
            <button type="button" id="idrv-save" class="btn btn-primary" <?php echo ($canEdit || $canCreate) ? '' : 'disabled'; ?>>Save</button>
            <button type="button" id="idrv-reset" class="btn">Reset</button>
          </div>

          <div id="idrv-form-message" class="idrv-form-message" aria-live="polite"></div>
        </div>
      </div>
    </form>

    <div class="idrv-table-wrap">
      <table id="idrv-table" class="table">
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Phone</th><th>Vehicle #</th><th>Vehicle Type</th><th>License #</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function(){
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
      // cache minimal user for other fragments during the session
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

    function safeParse(txt){ try { return JSON.parse(txt || '[]'); } catch(e){ return []; } }
  })();
</script>

<script src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<?php
// If we rendered full page, capture buffer and check for accidental JSON output
if ($shouldRenderFull && $headerIncluded) {
    $out = (string) @ob_get_clean();
    $trim = ltrim($out);
    $printed = false;

    // if auth include failed earlier, show friendly message
    if (!empty($__AUTH_INCLUDE_ERROR)) {
        echo '<section class="container" style="padding:18px">';
        echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
        echo '<strong>Server error</strong>';
        echo '<div style="margin-top:6px;">' . htmlspecialchars((string)$__AUTH_INCLUDE_ERROR, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div></section>';
        $printed = true;
    }

    // if bootstrap emitted JSON earlier while included, show friendly message
    if (!$printed && !empty($__BOOTSTRAP_EMITTED_JSON)) {
        echo '<section class="container" style="padding:18px">';
        echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
        echo '<strong>خطأ في الخادم</strong>';
        echo '<div style="margin-top:6px;">' . htmlspecialchars((string)($__BOOTSTRAP_EMITTED_JSON['message'] ?? 'خطأ داخلي — راجع السجل'), ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div></section>';
        $printed = true;
    } else {
        if (!$printed && strlen($trim) && ($trim[0] === '{' || $trim[0] === '[')) {
            // try decode — if it's an error JSON, render a friendly HTML instead of raw JSON
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
        // print original captured HTML (normal path)
        echo $out;
    }
}

// include footer if we included header which expects a footer
if ($headerIncluded) {
    $footerPath = __DIR__ . '/../includes/footer.php';
    if (is_readable($footerPath)) {
        require_once $footerPath;
    } else {
        echo "\n</main></div></body></html>\n";
    }
}