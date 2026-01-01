<?php
// htdocs/admin/includes/header.php
// Updated Admin header:
// - Merges full language JSON (htdocs/languages/admin/<code>.json) with server-side $ui_strings
// - Exposes a complete window.ADMIN_UI payload before loading admin.js so client-side i18n works for AJAX fragments
// - Ensures correct lang/dir on <html>
// - Exposes CSRF token and current user info
// - Loads core admin CSS/JS (deferred) and keeps structure for sidebar/header/main
//
// Save as UTF-8 without BOM.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load permission helpers
$permFile = __DIR__ . '/../require_permission.php';
if (is_readable($permFile)) {
    require_once $permFile;
} else {
    error_log('Missing require_permission.php at ' . $permFile);
}

// Protect admin pages by default
$basename = basename($_SERVER['SCRIPT_NAME'] ?? '');
$excludeLoginPages = ['login.php', 'ajax_login.php'];
if (!in_array($basename, $excludeLoginPages, true)) {
    if (function_exists('require_login')) {
        require_login();
    }
}

// Prepare $ui_strings fallback
$ui_strings = (isset($ui_strings) && is_array($ui_strings)) ? $ui_strings : [];
if (!isset($ui_strings['strings']) || !is_array($ui_strings['strings'])) $ui_strings['strings'] = [];
if (!isset($ui_strings['nav']) || !is_array($ui_strings['nav'])) $ui_strings['nav'] = [];

// Get current user/session info for UI
$user_session = null;
if (function_exists('get_current_user')) {
    $user_session = get_current_user();
}
if (!$user_session && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $user_session = $_SESSION['user'];
}
if (!$user_session) $user_session = [];

// Add minimal user payload to ui_strings (so it is present in merged payload)
$ui_strings['user'] = [
    'id' => $user_session['id'] ?? null,
    'username' => $user_session['username'] ?? $user_session['name'] ?? ($_SESSION['username'] ?? null),
    'role' => $user_session['role'] ?? ($_SESSION['role_name'] ?? null),
];

// Determine preferred language and direction (server-side preference takes precedence)
$preferred_lang = $preferred_lang ?? ($ui_strings['lang'] ?? ($_SESSION['preferred_language'] ?? 'ar'));
$html_direction = $html_direction ?? ($ui_strings['direction'] ?? (($preferred_lang === 'ar') ? 'rtl' : 'ltr'));

// Ensure ui_strings reflect selection
$ui_strings['lang'] = $preferred_lang;
$ui_strings['direction'] = $html_direction;

// Build small user/UI JSON (may be partial)
$ui_json = '{}';
try { $ui_json = json_encode($ui_strings, JSON_UNESCAPED_UNICODE) ?: '{}'; } catch (Throwable $e) { $ui_json = '{}'; }

// Attempt to read full language JSON from htdocs/languages/admin/<code>.json
$lang_payload = [];
$langCode = preg_replace('/[^a-z0-9_-]/i', '', $preferred_lang ?: 'ar');
$langFilePath = realpath(__DIR__ . '/../../') . '/languages/admin/' . $langCode . '.json';
if ($langFilePath && is_readable($langFilePath)) {
    try {
        $raw = file_get_contents($langFilePath);
        $dec = json_decode($raw, true);
        if (is_array($dec)) $lang_payload = $dec;
    } catch (Throwable $e) {
        // ignore parse errors, fallback to $ui_strings only
        error_log('Failed to load language file: ' . $langFilePath . ' error: ' . $e->getMessage());
    }
}

// Merge lang_payload and ui_strings payload: language first, then ui overrides (user info, runtime strings)
$merged_payload = $lang_payload;
if (is_array($ui_strings) && count($ui_strings)) {
    // array_replace_recursive so ui_strings override language file where appropriate
    $merged_payload = array_replace_recursive($merged_payload, $ui_strings);
}

// Final merged JSON safe encode
$mergedJson = '{}';
try { $mergedJson = json_encode($merged_payload, JSON_UNESCAPED_UNICODE); } catch (Throwable $e) { $mergedJson = $ui_json; }

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf_token = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($merged_payload['lang'] ?? $preferred_lang, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars($merged_payload['direction'] ?? $html_direction, ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?php echo htmlspecialchars($merged_payload['strings']['title'] ?? ($ui_strings['strings']['title'] ?? 'Admin'), ENT_QUOTES, 'UTF-8'); ?></title>

  <!-- Core CSS -->
  <link rel="stylesheet" href="/admin/assets/css/admin.css">

  <!-- Make full language + UI payload available immediately to client scripts -->
  <script>
    try {
      window.ADMIN_UI = <?php echo $mergedJson; ?> || {};
    } catch (e) {
      window.ADMIN_UI = window.ADMIN_UI || {};
    }
    window.ADMIN_LANG = (window.ADMIN_UI && window.ADMIN_UI.lang) ? window.ADMIN_UI.lang : <?php echo json_encode($preferred_lang, JSON_UNESCAPED_UNICODE); ?>;
    window.ADMIN_DIR  = (window.ADMIN_UI && window.ADMIN_UI.direction) ? window.ADMIN_UI.direction : <?php echo json_encode($html_direction, JSON_UNESCAPED_UNICODE); ?>;
    window.CSRF_TOKEN = '<?php echo addslashes($csrf_token); ?>';
    window.ADMIN_USER = (window.ADMIN_UI && window.ADMIN_UI.user) ? window.ADMIN_UI.user : <?php echo json_encode($user_session ?? [], JSON_UNESCAPED_UNICODE); ?>;
    // Ensure document attributes reflect language/direction immediately (in case CSS adjusts)
    try {
      document.documentElement.lang = window.ADMIN_LANG || document.documentElement.lang || '<?php echo addslashes($preferred_lang); ?>';
      document.documentElement.dir  = window.ADMIN_DIR  || document.documentElement.dir  || '<?php echo addslashes($html_direction); ?>';
      if (window.ADMIN_DIR) document.body.classList.add(window.ADMIN_DIR === 'rtl' ? 'rtl' : 'ltr');
    } catch (e) { /* ignore */ }
    if (window.console && console.info) console.info('ADMIN_UI initialized:', window.ADMIN_LANG, window.ADMIN_DIR, 'USER:', window.ADMIN_USER);
  </script>

  <!-- Core scripts (deferred so DOM can parse) -->
  <script src="/admin/assets/js/modal.js" defer></script>
  <script src="/admin/assets/js/admin.js" defer></script>
  <script src="/admin/assets/js/menu.js" defer></script>

</head>
<body class="admin">
  <header class="admin-header" role="banner" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['title'] ?? ($ui_strings['strings']['title'] ?? 'Admin'), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="header-left">
      <button id="sidebarToggle" class="icon-btn" aria-controls="adminSidebar" aria-expanded="false" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['toggle_sidebar'] ?? ($ui_strings['strings']['toggle_sidebar'] ?? 'Toggle sidebar'), ENT_QUOTES, 'UTF-8'); ?>">☰</button>

      <a class="brand" href="/admin/" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['brand'] ?? ($ui_strings['strings']['brand'] ?? 'Brand'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php
          $logoPublic = '/uploads/logo.png';
          if (is_readable($_SERVER['DOCUMENT_ROOT'] . $logoPublic)): ?>
            <img src="<?php echo htmlspecialchars($logoPublic, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($merged_payload['strings']['brand'] ?? ($ui_strings['strings']['brand'] ?? 'Brand'), ENT_QUOTES, 'UTF-8'); ?>" class="brand-logo" />
        <?php else: ?>
            <span class="brand-fallback" data-i18n="strings.brand"><?php echo htmlspecialchars($merged_payload['strings']['brand'] ?? ($ui_strings['strings']['brand'] ?? 'QOOQZ'), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
      </a>
    </div>

    <div class="header-center" role="search" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['search_placeholder'] ?? ($ui_strings['strings']['search_placeholder'] ?? 'Search'), ENT_QUOTES, 'UTF-8'); ?>">
      <div class="search-wrap">
        <input id="adminSearch" class="search-input" type="search"
               placeholder="<?php echo htmlspecialchars($merged_payload['strings']['search_placeholder'] ?? ($ui_strings['strings']['search_placeholder'] ?? 'Search...'), ENT_QUOTES, 'UTF-8'); ?>"
               aria-label="<?php echo htmlspecialchars($merged_payload['strings']['search_placeholder'] ?? ($ui_strings['strings']['search_placeholder'] ?? 'Search'), ENT_QUOTES, 'UTF-8'); ?>" />
        <button id="searchBtn" class="icon-btn" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['search_button'] ?? ($ui_strings['strings']['search_button'] ?? 'Search'), ENT_QUOTES, 'UTF-8'); ?>" type="button">🔍</button>
      </div>
    </div>

    <div class="header-right">
      <div class="notif-wrap" aria-live="polite" aria-atomic="true">
        <button id="notifBtn" class="icon-btn" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['notifications'] ?? ($ui_strings['strings']['notifications'] ?? 'Notifications'), ENT_QUOTES, 'UTF-8'); ?>">🔔</button>
        <span id="notifCount" class="badge" style="display:none;"></span>
      </div>

      <div class="user-menu" role="navigation" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['profile'] ?? ($ui_strings['strings']['profile'] ?? 'Profile'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php
          $avatar = $user_session['avatar'] ?? ($_SESSION['avatar'] ?? '/admin/assets/img/default-avatar.png');
          $displayName = $user_session['username'] ?? $user_session['name'] ?? ($_SESSION['username'] ?? 'Admin');
          $roleLabel = $user_session['role'] ?? ($_SESSION['role_name'] ?? 'Administrator');
        ?>
        <a href="/admin/profile.php" class="user-link" title="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>">
          <img class="avatar" src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>">
        </a>
        <div class="user-info">
          <div class="username-text" style="display:none;"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="user-role"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <form method="POST" action="/admin/logout.php" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="btn-logout" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['logout'] ?? ($ui_strings['strings']['logout'] ?? 'Logout'), ENT_QUOTES, 'UTF-8'); ?>">⎋</button>
        </form>
      </div>
    </div>
  </header>

  <div class="admin-layout">
    <aside id="adminSidebar" class="admin-sidebar" role="navigation" aria-label="<?php echo htmlspecialchars($merged_payload['strings']['menu'] ?? ($ui_strings['strings']['menu'] ?? 'Menu'), ENT_QUOTES, 'UTF-8'); ?>">
      <nav>
        <?php
          $menuFile = __DIR__ . '/menu.php';
          if (is_readable($menuFile)) include $menuFile;
          else {
            echo '<ul class="sidebar-list" role="menu"><li><a href="/admin/dashboard.php" data-load-url="/admin/dashboard.php">Dashboard</a></li></ul>';
          }
        ?>
      </nav>
    </aside>

    <div class="sidebar-backdrop" aria-hidden="true"></div>

    <main id="adminMainContent" class="admin-main" role="main">
      <!-- content begins -->