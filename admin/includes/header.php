<?php
declare(strict_types=1);
/**
 * admin/includes/header.php
 * Robust header that injects $ADMIN_UI payload and renders top layout skeleton.
 */

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/* Prevent API/XHR direct execution for safety */
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($reqUri, '/api/') === 0) {
    return;
}

/* Try to include bootstrap to populate $ADMIN_UI_PAYLOAD (safe if file uses guards) */
$adminBootstrap = realpath(__DIR__ . '/../../api/bootstrap_admin_ui.php') ?: (__DIR__ . '/../../api/bootstrap_admin_ui.php');
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? null;
if (is_readable($adminBootstrap)) {
    try {
        require_once $adminBootstrap;
    } catch (Throwable $e) {
        // ignore and use defaults
    }
}


/* Fallback defaults */
if (!isset($ADMIN_UI_PAYLOAD) || !is_array($ADMIN_UI_PAYLOAD)) {
    $ADMIN_UI_PAYLOAD = [
        'lang' => 'en',
        'direction' => 'ltr',
        'strings' => [],
        'user' => ['id' => 0, 'username' => 'guest', 'permissions' => [], 'avatar' => '/admin/assets/img/default-avatar.png'],
        'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)),
        'theme' => ['colors' => [], 'fonts' => [], 'designs' => []]
    ];
}

/* Ensure user structure */
if (!isset($ADMIN_UI_PAYLOAD['user']) || !is_array($ADMIN_UI_PAYLOAD['user'])) {
    $ADMIN_UI_PAYLOAD['user'] = ['id' => 0, 'username' => 'guest', 'permissions' => [], 'avatar' => '/admin/assets/img/default-avatar.png'];
}
if (!empty($_SESSION['user_id'])) {
    $sessionUser = [
        'id' => (int)($_SESSION['user_id'] ?? 0),
        'username' => $_SESSION['username'] ?? $ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest',
        'permissions' => $_SESSION['permissions'] ?? $ADMIN_UI_PAYLOAD['user']['permissions'] ?? [],
        'avatar' => $_SESSION['avatar'] ?? $ADMIN_UI_PAYLOAD['user']['avatar'] ?? '/admin/assets/img/default-avatar.png'
    ];
    $ADMIN_UI_PAYLOAD['user'] = array_merge($ADMIN_UI_PAYLOAD['user'], $sessionUser);
}

/* Determine direction from lang */
$rtlLangs = ['ar','fa','he','ur'];
$lang = strtolower($ADMIN_UI_PAYLOAD['lang'] ?? 'en');
$dir = in_array($lang, $rtlLangs, true) ? 'rtl' : 'ltr';
$ADMIN_UI_PAYLOAD['direction'] = $dir;

/* Ensure strings exists */
if (!isset($ADMIN_UI_PAYLOAD['strings']) || !is_array($ADMIN_UI_PAYLOAD['strings'])) {
    $ADMIN_UI_PAYLOAD['strings'] = [];
}

/* Safe JSON for client */
function safe_admin_ui_json_encode($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($s === false) {
        array_walk_recursive($v, function (&$item) {
            if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
    return $s;
}
$ADMIN_UI_JSON = safe_admin_ui_json_encode($ADMIN_UI_PAYLOAD);
$GLOBALS['ADMIN_UI'] = $ADMIN_UI_PAYLOAD;
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars($dir, ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <title><?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></title>

  <link rel="stylesheet" href="/admin/assets/css/admin.css">
  <link rel="stylesheet" href="/admin/assets/css/admin-overrides.css">
  <link rel="stylesheet" href="/admin/assets/css/modal.css">

  <script>
    try {
      window.ADMIN_UI = <?php echo $ADMIN_UI_JSON; ?>;
      window.ADMIN_LANG = window.ADMIN_UI.lang || 'en';
      window.ADMIN_DIR = window.ADMIN_UI.direction || 'ltr';
      window.CSRF_TOKEN = window.ADMIN_UI.csrf_token || '';
      window.ADMIN_USER = window.ADMIN_UI.user || {};
      if (document && document.documentElement) {
        document.documentElement.lang = window.ADMIN_LANG;
        document.documentElement.dir = window.ADMIN_DIR;
      }
    } catch (e) {
      console.warn('ADMIN_UI init error', e);
      window.ADMIN_UI = window.ADMIN_UI || {};
      window.ADMIN_LANG = window.ADMIN_LANG || 'en';
      window.ADMIN_DIR = window.ADMIN_DIR || 'ltr';
      window.CSRF_TOKEN = window.CSRF_TOKEN || '';
      window.ADMIN_USER = window.ADMIN_USER || {};
    }
  </script>

  <script src="/admin/assets/js/admin_core.js" defer></script>
  <script src="/admin/assets/js/sidebar-toggle.js" defer></script>
  <script src="/admin/assets/js/modal.js" defer></script>
</head>
<body class="admin">
  <header class="admin-header" role="banner">
    <div class="header-left">
      <button id="sidebarToggle" class="icon-btn" aria-controls="adminSidebar" aria-expanded="false" aria-label="Toggle sidebar" data-i18n-aria-label="toggle_sidebar"><span aria-hidden="true">☰</span></button>
      <a class="brand" href="/admin/" aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?>">
        <?php
        $logoUrl = $ADMIN_UI_PAYLOAD['theme']['designs']['logo_url'] ?? null;
        $showImg = false;
        if ($logoUrl) {
            if (!preg_match('/^https?:\\/\\//', $logoUrl) && !str_starts_with($logoUrl, '//')) {
                if (!str_starts_with($logoUrl, '/')) $logoUrl = '/' . ltrim($logoUrl, '/');
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $logoUrl;
                $showImg = file_exists($fullPath) && is_readable($fullPath);
            } else {
                $showImg = true;
            }
        }
        if ($showImg && $logoUrl):
        ?>
          <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Brand', ENT_QUOTES, 'UTF-8'); ?>" class="brand-logo" width="140" height="40" loading="eager">
        <?php else: ?>
          <span class="brand-fallback" data-i18n="brand"><?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
      </a>
    </div>

    <div class="header-center" role="search">
      <div class="search-wrap">
        <input id="adminSearch" class="search-input" type="search" data-i18n-placeholder="search_placeholder" placeholder="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['search_placeholder'] ?? 'Search...', ENT_QUOTES, 'UTF-8'); ?>">
        <button id="searchBtn" class="icon-btn" aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['search_button'] ?? 'Search', ENT_QUOTES, 'UTF-8'); ?>" data-i18n-aria-label="search_button"><span aria-hidden="true">🔍</span></button>
      </div>
    </div>

    <div class="header-right">
      <div class="notif-wrap">
        <button id="notifBtn" class="icon-btn" aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['notifications'] ?? 'Notifications', ENT_QUOTES, 'UTF-8'); ?>" data-i18n-aria-label="notifications"><span aria-hidden="true">🔔</span></button>
        <span id="notifCount" class="badge" style="display:none;"></span>
      </div>

      <div class="user-menu">
        <a href="/admin/profile.php" class="user-link" title="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?>">
          <img class="avatar" src="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['avatar'] ?? '/admin/assets/img/default-avatar.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?>" width="36" height="36" loading="lazy">
        </a>
        <div class="user-info">
          <div class="username-text"><?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="user-role"><?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['role'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <form method="POST" action="/admin/logout.php" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="btn-logout" aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['logout'] ?? 'Logout', ENT_QUOTES, 'UTF-8'); ?>" data-i18n-aria-label="logout"><span aria-hidden="true">⎋</span></button>
        </form>
      </div>
    </div>
  </header>

  <div class="admin-layout">
    <aside id="adminSidebar" class="admin-sidebar" role="navigation">
      <nav>
        <?php
        $menuFile = __DIR__ . '/menu.php';
        if (is_readable($menuFile)) {
            include $menuFile;
        } else {
            echo '<ul class="sidebar-list"><li><a href="/admin/dashboard.php" class="sidebar-link" data-i18n="nav.dashboard">Dashboard</a></li></ul>';
        }
        ?>
      </nav>
    </aside>

    <div class="sidebar-backdrop"></div>
    <main id="adminMainContent" class="admin-main" role="main">
      <!-- Page content starts here -->