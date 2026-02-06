<?php
/**
 * admin/fragments/DeliveryZone.php
 *
 * Admin fragment for managing Delivery Zones with Leaflet + Leaflet.draw
 * - Safe includes for auth/bootstrap (won't leak JSON or fatal errors)
 * - Detects if admin/includes/header.php already printed header (ADMIN_HEADER_PRESENT)
 *   and will not include header/footer again when embedded.
 * - Exposes safe JS variables: window.DZ, window.CURRENT_USER, window.AVAILABLE_LANGUAGES,
 *   window.ADMIN_LANG, window.LANG_DIRECTION, window.CSRF_TOKEN
 *
 * Save as UTF-8 without BOM.
 */

declare(strict_types=1);

// ---------------- Diagnostic shutdown logger (helps capture HTTP 500 reasons) ----------------
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log = __DIR__ . '/../../api/error_debug.log';
        $msg = "[" . date('c') . "] SHUTDOWN ERROR: {$err['message']} in {$err['file']}:{$err['line']}" . PHP_EOL;
        @file_put_contents($log, $msg, FILE_APPEND);
    }
});

// ---------------- Paths ----------------
$baseApiDir = realpath(__DIR__ . '/../../api') ?: (__DIR__ . '/../../api');
$bootstrapPath = $baseApiDir . '/bootstrap.php';
$authHelper = $baseApiDir . '/helpers/auth_helper.php';
$logFile = $baseApiDir . '/error_debug.log';

// ---------------- Safe include of auth_helper or bootstrap ----------------
$__AUTH_INCLUDE_ERROR = null;
$__BOOTSTRAP_EMITTED_JSON = null;

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

if (!$isApiRequest) {
    if (is_readable($authHelper)) {
        // safe include: capture output and convert warnings to exceptions
        try {
            ob_start();
            $prevErr = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
            try {
                require_once $authHelper;
            } finally {
                if ($prevErr !== null) set_error_handler($prevErr);
            }
            $auth_out = (string) @ob_get_clean();
            if (strlen(trim($auth_out))) {
                @file_put_contents($logFile, "[" . date('c') . "] auth_helper emitted output when included by " . __FILE__ . " : " . substr($auth_out, 0, 1024) . PHP_EOL, FILE_APPEND);
            }
        } catch (Throwable $e) {
            @file_put_contents($logFile, "[" . date('c') . "] auth_helper include failed: " . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);
            $__AUTH_INCLUDE_ERROR = $e->getMessage();
        }
    } elseif (is_readable($bootstrapPath)) {
        // last-resort include bootstrap but capture output to avoid leaking JSON
        ob_start();
        @require_once $bootstrapPath;
        $buf = (string) @ob_get_clean();
        $trim = ltrim($buf);
        if (strlen($trim) && ($trim[0] === '{' || $trim[0] === '[')) {
            $decoded = @json_decode($trim, true);
            if (is_array($decoded) || is_object($decoded)) {
                $__BOOTSTRAP_EMITTED_JSON = $decoded;
                @file_put_contents($logFile, "[" . date('c') . "] bootstrap emitted JSON while included by " . __FILE__ . " : " . substr($trim, 0, 1024) . PHP_EOL, FILE_APPEND);
            } else {
                echo $buf;
            }
        } else {
            echo $buf;
        }
    }
}

// ---------------- Ensure session ----------------
if (function_exists('start_session_safe')) {
    try { start_session_safe(); } catch (Throwable $e) { if (session_status() === PHP_SESSION_NONE) @session_start(); }
} else {
    if (session_status() === PHP_SESSION_NONE) @session_start();
}

// ---------------- Ensure auth init (best-effort) ----------------
$db = null;
if (function_exists('get_db_connection')) {
    try { $db = get_db_connection(); } catch (Throwable $e) { $db = null; }
}
if (function_exists('auth_init')) {
    try { auth_init($db); } catch (Throwable $e) { @file_put_contents($logFile, "[".date('c')."] auth_init failed: ".$e->getMessage().PHP_EOL, FILE_APPEND); }
}

// ---------------- Build current user snapshot safely ----------------
$currentUser = null;
if (function_exists('get_authenticated_user_with_permissions')) {
    try { $currentUser = get_authenticated_user_with_permissions(); } catch (Throwable $e) { @file_put_contents($logFile, "[".date('c')."] get_authenticated_user_with_permissions failed: ".$e->getMessage().PHP_EOL, FILE_APPEND); $currentUser = null; }
}
if (empty($currentUser)) {
    $sessUser = $_SESSION['user'] ?? null;
    if (empty($sessUser) && !empty($_SESSION['user_id'])) {
        $sessUser = [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : (isset($_SESSION['role']) ? (int)$_SESSION['role'] : 0),
            'preferred_language' => $_SESSION['preferred_language'] ?? ($_SESSION['lang'] ?? null),
            'permissions' => $_SESSION['permissions'] ?? []
        ];
    }
    $currentUser = is_array($sessUser) ? $sessUser : ['id'=>0,'username'=>'guest','role_id'=>0,'permissions'=>[]];
}

// ---------------- Normalized JS user + csrf ----------------
$safeUser = [
    'id' => isset($currentUser['id']) ? (int)$currentUser['id'] : 0,
    'username' => isset($currentUser['username']) ? (string)$currentUser['username'] : null,
    'email' => isset($currentUser['email']) ? (string)$currentUser['email'] : null,
    'role_id' => isset($currentUser['role_id']) ? (int)$currentUser['role_id'] : 0,
    'preferred_language' => $currentUser['preferred_language'] ?? null,
    'permissions' => is_array($currentUser['permissions']) ? $currentUser['permissions'] : []
];

if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}
$csrfToken = (string)($_SESSION['csrf_token'] ?? '');

// ---------------- Languages listing (languages/admin/*.json) ----------------
$langBase = '/languages/admin';
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/../../'), '/\\');
$langDirPath = $docRoot . rtrim($langBase, '/\\');
$languages_for_js = [];
if (is_dir($langDirPath)) {
    foreach (glob($langDirPath . '/*.json') ?: [] as $file) {
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
$langMeta = null; foreach ($languages_for_js as $l) if ($l['code'] === $preferredLang) { $langMeta = $l; break; }
if (!$langMeta) $langMeta = $languages_for_js[0];
$langDirection = $langMeta['direction'] ?? 'ltr';

// ---------------- Safe JSON encoder ----------------
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

// ---------------- Header/Footer inclusion logic ----------------
// If admin/includes/header.php defines ADMIN_HEADER_PRESENT we consider the parent already printed header.
// header.php should define ADMIN_HEADER_PRESENT after rendering header.
$parentHasHeader = defined('ADMIN_HEADER_PRESENT');

// decide whether fragment should render full page header/footer or act as embedded content
$scriptFilename = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$thisFilename   = basename(__FILE__);
$directRequest  = ($scriptFilename === $thisFilename);
$isAjax         = $xhr;
$forceStandalone = !empty($_GET['_standalone']) || !empty($_GET['standalone']) || !empty($_GET['embed']);

$shouldRenderFull = false;
if ($parentHasHeader) {
    // header already present by parent, render fragment content only (but consider as full context)
    $shouldRenderFull = false; // do not include header/footer here
    $headerIncludedByFragment = false;
} else {
    if ($directRequest) {
        if ($forceStandalone) $shouldRenderFull = true;
        elseif ($isAjax) $shouldRenderFull = false;
        else $shouldRenderFull = true;
    } else {
        $shouldRenderFull = false;
    }
    $headerIncludedByFragment = false;
}

// If we will render full page and parent did NOT include header, include header now
$headerIncluded = false;
if ($shouldRenderFull && !$parentHasHeader) {
    $headerPath = __DIR__ . '/../includes/header.php';
    if (is_readable($headerPath)) {
        require_once $headerPath;
        $headerIncluded = true;
        $headerIncludedByFragment = true;
    } else {
        // minimal fallback header
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        $csrf_token = $_SESSION['csrf_token'];
        ?><!doctype html>
        <html lang="<?php echo htmlspecialchars($preferredLang, ENT_QUOTES | ENT_SUBSTITUTE); ?>" dir="<?php echo htmlspecialchars($langDirection, ENT_QUOTES | ENT_SUBSTITUTE); ?>">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>Admin - Delivery Zones</title>
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
        $headerIncludedByFragment = true;
    }
}

// ------------------ Render fragment HTML ------------------
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
<link rel="stylesheet" href="/admin/assets/css/pages/DeliveryZone.css" />

<div id="adminDeliveryZone" class="delivery-zone-admin" dir="<?php echo htmlspecialchars($langDirection, ENT_QUOTES | ENT_SUBSTITUTE); ?>" data-lang="<?php echo htmlspecialchars($preferredLang, ENT_QUOTES | ENT_SUBSTITUTE); ?>">
  <header class="dz-header">
    <h2 class="dz-title" id="dz_title" data-i18n="delivery_zone.module_title">Delivery Zones</h2>
    <div class="dz-controls">
      <button id="dzNewBtn" class="btn primary" type="button" data-i18n="delivery_zone.create_title">New Zone</button>
      <button id="dzRefresh" class="btn" type="button" data-i18n="actions.refresh">Refresh</button>
    </div>
  </header>

  <section class="dz-main">
    <aside class="dz-sidebar">
      <div class="dz-filters">
        <input id="dzSearch" class="dz-input" data-i18n-placeholder="placeholders.search" placeholder="Search zones..." />
        <select id="dzStatusFilter" class="dz-select">
          <option value="" data-i18n="delivery_zone.all_statuses">All</option>
          <option value="active" data-i18n="statuses.active">Active</option>
          <option value="inactive" data-i18n="statuses.inactive">Inactive</option>
        </select>
      </div>
      <div id="dzList" class="dz-list" data-i18n-loading="messages.loading">Loading...</div>
    </aside>

    <div class="dz-map-area">
      <div id="dzMap" style="height:520px;border:1px solid #ddd;border-radius:6px"></div>

      <div class="dz-form-area">
        <form id="dzForm" class="dz-form" onsubmit="return false;">
          <input type="hidden" name="csrf_token" id="dz_csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE); ?>">
          <input type="hidden" id="dz_id" name="id" value="0">

          <label class="dz-label">
            <span data-i18n="fields.zone_name">Zone Name</span>
            <input id="dz_zone_name" name="zone_name" class="dz-input" data-i18n-placeholder="placeholders.enter_zone_name" />
          </label>

          <label class="dz-label">
            <span data-i18n="fields.zone_type">Zone Type</span>
            <select id="dz_zone_type" name="zone_type" class="dz-select">
              <option value="polygon" data-i18n="zone_type_values.polygon">Polygon</option>
              <option value="rectangle" data-i18n="zone_type_values.rectangle">Rectangle</option>
              <option value="radius" data-i18n="zone_type_values.radius">Circle (Radius)</option>
              <option value="city" data-i18n="zone_type_values.city">City</option>
              <option value="district" data-i18n="zone_type_values.district">District</option>
              <option value="postal" data-i18n="zone_type_values.postal">Postal</option>
            </select>
          </label>

          <label class="dz-label">
            <span data-i18n="fields.zone_value_json">Zone Value (JSON)</span>
            <textarea id="dz_zone_value" name="zone_value" class="dz-textarea" rows="3" data-i18n-placeholder="placeholders.paste_geojson"></textarea>
          </label>

          <div class="dz-row">
            <label class="dz-label dz-col">
              <span data-i18n="fields.shipping_rate">Shipping Rate</span>
              <input id="dz_shipping_rate" name="shipping_rate" type="number" step="0.01" class="dz-input" value="0.00">
            </label>

            <label class="dz-label dz-col">
              <span data-i18n="fields.free_shipping_threshold">Free Shipping Threshold</span>
              <input id="dz_free_threshold" name="free_shipping_threshold" type="number" step="0.01" class="dz-input">
            </label>
          </div>

          <div class="dz-row">
            <label class="dz-label dz-col">
              <span data-i18n="fields.estimated_delivery_days">Estimated Delivery Days</span>
              <input id="dz_estimated_days" name="estimated_delivery_days" type="number" class="dz-input" value="3">
            </label>

            <label class="dz-label dz-col">
              <span data-i18n="fields.status">Status</span>
              <select id="dz_status" name="status" class="dz-select">
                <option value="active" data-i18n="statuses.active">Active</option>
                <option value="inactive" data-i18n="statuses.inactive">Inactive</option>
              </select>
            </label>
          </div>

          <div class="form-actions">
            <button id="dzSaveBtn" class="btn primary" type="button" data-i18n="actions.save">Save</button>
            <button id="dzResetBtn" class="btn" type="button" data-i18n="actions.reset">Reset</button>
            <label class="dz-inline">
              <input type="checkbox" id="dz_auto_save" checked>
              <span data-i18n="delivery_zone.auto_save">Auto-save</span>
            </label>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<script>
  // Expose runtime variables for DeliveryZone JS
  window.DZ = window.DZ || {};
  window.DZ.API_BASE = '/api/routes/DeliveryZone.php';
  window.DZ.HELPERS = {
    countries: '/api/helpers/countries.php?scope=all',
    cities: '/api/helpers/cities.php?scope=all'
  };
  try { window.CURRENT_USER = <?php echo $currentUserJson; ?>; } catch (e) { window.CURRENT_USER = { id:0, username:'guest', permissions:[] }; }
  try { window.AVAILABLE_LANGUAGES = <?php echo $availableLangsJson; ?>; } catch (e) { window.AVAILABLE_LANGUAGES = [{"code":"en","name":"English","direction":"ltr","strings":{}}]; }
  window.ADMIN_LANG = '<?php echo htmlspecialchars($preferredLang, ENT_QUOTES | ENT_SUBSTITUTE); ?>';
  window.LANG_DIRECTION = '<?php echo htmlspecialchars($langDirection, ENT_QUOTES | ENT_SUBSTITUTE); ?>';
  window.CSRF_TOKEN = '<?php echo addslashes($csrfToken); ?>';
  var langBase = '<?php echo addslashes($langBase); ?>';
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js" defer></script>
<script src="/admin/assets/js/i18n.js" defer></script>
<script src="/admin/assets/js/pages/DeliveryZone.js" defer></script>

<?php
// ---------------- Post-render: buffer check and footer inclusion ----------------
if ($shouldRenderFull && $headerIncluded) {
    $out = (string) @ob_get_clean();
    $trim = ltrim($out);
    $printed = false;

    if (!empty($__AUTH_INCLUDE_ERROR)) {
        echo '<section class="container" style="padding:18px">';
        echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
        echo '<strong>Server error</strong>';
        echo '<div style="margin-top:6px;">' . htmlspecialchars((string)$__AUTH_INCLUDE_ERROR, ENT_QUOTES | ENT_SUBSTITUTE) . '</div>';
        echo '</div></section>';
        $printed = true;
    }

    if (!$printed && !empty($__BOOTSTRAP_EMITTED_JSON)) {
        echo '<section class="container" style="padding:18px">';
        echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
        echo '<strong>Server error</strong>';
        echo '<div style="margin-top:6px;">' . htmlspecialchars((string)($__BOOTSTRAP_EMITTED_JSON['message'] ?? 'Internal server error'), ENT_QUOTES | ENT_SUBSTITUTE) . '</div>';
        echo '</div></section>';
        $printed = true;
    } else {
        if (!$printed && strlen($trim) && ($trim[0] === '{' || $trim[0] === '[')) {
            $try = @json_decode($trim, true);
            if (is_array($try) && (isset($try['success']) || isset($try['message']))) {
                echo '<section class="container" style="padding:18px">';
                echo '<div style="padding:18px;background:#fff5f5;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">';
                echo '<strong>Server error</strong>';
                echo '<div style="margin-top:6px;">' . htmlspecialchars($try['message'] ?? json_encode($try), ENT_QUOTES | ENT_SUBSTITUTE) . '</div>';
                echo '</div></section>';
                $printed = true;
            }
        }
    }

    if (!$printed) {
        echo $out;
    }
}

// Include footer only if we included header ourselves
if (!empty($headerIncludedByFragment)) {
    $footerPath = __DIR__ . '/../includes/footer.php';
    if (is_readable($footerPath)) {
        require_once $footerPath;
    } else {
        echo "\n</main></div></body></html>\n";
    }
}