<?php
declare(strict_types=1);
/**
 * admin/includes/header.php
 *
 * النسخة النهائية الكاملة والمستقرة للهيدر
 * - تعمل 100% مع RTL/LTR
 * - تترجم كل النصوص تلقائياً من JSON (data-i18n)
 * - إصلاح مشكلة اختفاء الأيقونات في العربية (RTL)
 * - إصلاح اتجاه الأيقونات (تذهب إلى اليمين في RTL)
 * - لا fallback يدوي عربي يعيق الإنجليزية
 * - آمنة ومحسنة
 */
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/* -------------------------
   Detect API/XHR requests
   ------------------------- */
function admin_is_api_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/api/') === 0) return true;
    $xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($xhr) return true;
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, '/api/index.php') !== false) {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (strpos($path, '/api/') === 0) return true;
    }
    return false;
}
if (admin_is_api_request()) {
    return;
}

/* Optional permission include */
$permFile = __DIR__ . '/../require_permission.php';
if (is_readable($permFile)) {
    @require_once $permFile;
}

/* Minimal auth redirect */
$basename = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!in_array($basename, ['login.php', 'ajax_login.php'], true)) {
    if (function_exists('require_login')) {
        try { 
            require_login(); 
        } catch (Throwable $e) { 
            header('Location: /admin/login.php'); 
            exit; 
        }
    } elseif (empty($_SESSION['user_id'])) {
        header('Location: /admin/login.php'); 
        exit;
    }
}

/* -------------------------
   Load bootstrap_admin_ui.php
   ------------------------- */
$adminBootstrap = realpath(__DIR__ . '/../../api/bootstrap_admin_ui.php')
    ?: __DIR__ . '/../../api/bootstrap_admin_ui.php';

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? null;
if (is_readable($adminBootstrap)) {
    try {
        ob_start();
        require_once $adminBootstrap;
        @ob_end_clean();
    } catch (Throwable $e) {
        // continue with defaults
    }
}

/* -------------------------
   Safe defaults & session merge
   ------------------------- */
if (!isset($ADMIN_UI_PAYLOAD) || !is_array($ADMIN_UI_PAYLOAD)) {
    $ADMIN_UI_PAYLOAD = [
        'lang' => 'en',
        'direction' => 'ltr',
        'strings' => [],
        'user' => ['id' => 0, 'username' => 'guest', 'permissions' => [], 'avatar' => '/admin/assets/img/default-avatar.png'],
        'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)),
        'theme' => ['id' => null, 'name' => 'Default', 'slug' => 'default', 'colors' => [], 'fonts' => [], 'designs' => []]
    ];
}

// Initialize user array if not set or null
if (!isset($ADMIN_UI_PAYLOAD['user']) || !is_array($ADMIN_UI_PAYLOAD['user'])) {
    $ADMIN_UI_PAYLOAD['user'] = ['id' => 0, 'username' => 'guest', 'permissions' => [], 'avatar' => '/admin/assets/img/default-avatar.png'];
}

// Safe merge user - FIXED: Ensure both are arrays
if (!empty($_SESSION['user_id'])) {
    $sessionUser = [
        'id' => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? ($ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest'),
        'role' => $_SESSION['role_name'] ?? ($ADMIN_UI_PAYLOAD['user']['role'] ?? null),
        'permissions' => $_SESSION['permissions'] ?? ($ADMIN_UI_PAYLOAD['user']['permissions'] ?? []),
        'avatar' => $_SESSION['avatar'] ?? ($ADMIN_UI_PAYLOAD['user']['avatar'] ?? '/admin/assets/img/default-avatar.png')
    ];
    
    // Merge session data into existing user array
    $ADMIN_UI_PAYLOAD['user'] = array_merge($ADMIN_UI_PAYLOAD['user'], $sessionUser);
}

// Language & direction
$sessionLang = $_SESSION['preferred_language'] ?? $_SESSION['lang'] ?? null;
if ($sessionLang && is_string($sessionLang)) {
    $ADMIN_UI_PAYLOAD['lang'] = strtolower(trim($sessionLang));
}

// Ensure lang is set
if (!isset($ADMIN_UI_PAYLOAD['lang']) || empty($ADMIN_UI_PAYLOAD['lang'])) {
    $ADMIN_UI_PAYLOAD['lang'] = 'en';
}

$rtlLangs = ['ar', 'fa', 'he', 'ur'];
$ADMIN_UI_PAYLOAD['direction'] = in_array($ADMIN_UI_PAYLOAD['lang'], $rtlLangs, true) ? 'rtl' : 'ltr';

// Ensure keys exist with proper defaults
if (!isset($ADMIN_UI_PAYLOAD['strings']) || !is_array($ADMIN_UI_PAYLOAD['strings'])) {
    $ADMIN_UI_PAYLOAD['strings'] = [];
}

if (empty($ADMIN_UI_PAYLOAD['csrf_token'])) {
    $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
    $ADMIN_UI_PAYLOAD['csrf_token'] = $_SESSION['csrf_token'];
}

if (!isset($ADMIN_UI_PAYLOAD['theme']) || !is_array($ADMIN_UI_PAYLOAD['theme'])) {
    $ADMIN_UI_PAYLOAD['theme'] = ['id' => null, 'name' => 'Default', 'slug' => 'default', 'colors' => [], 'fonts' => [], 'designs' => []];
}
/* Expose globally */
$GLOBALS['ADMIN_UI'] = $ADMIN_UI_PAYLOAD;

/* Safe JSON encode */
function safe_admin_ui_json_encode($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($s === false) {
        // Fallback for encoding issues
        array_walk_recursive($v, function (&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
    return $s;
}
$ADMIN_UI_JSON = safe_admin_ui_json_encode($ADMIN_UI_PAYLOAD);

/* HTML attributes */
$langAttr = htmlspecialchars($ADMIN_UI_PAYLOAD['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
$dirAttr = htmlspecialchars($ADMIN_UI_PAYLOAD['direction'] ?? 'ltr', ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['title'] ?? $ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$csrf = htmlspecialchars($ADMIN_UI_PAYLOAD['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');

/* -------------------------
   Theme CSS variables & fonts
   ------------------------- */
$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];
$cssVars = [];
$fontLinks = [];

// Colors
if (!empty($theme['colors']) && is_array($theme['colors'])) {
    foreach ($theme['colors'] as $color) {
        if (is_array($color)) {
            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $color['setting_key'] ?? ''));
            $val = $color['color_value'] ?? null;
        } else {
            continue; // Skip if not in expected format
        }
        
        if (empty($val)) continue;
        
        switch ($key) {
            case 'primary_color': 
                $cssVars['--color-primary'] = $val; 
                break;
            case 'primary_hover': 
                $cssVars['--color-primary-dark'] = $val; 
                break;
            case 'accent_color': 
                $cssVars['--color-accent'] = $val; 
                break;
            case 'background_main': 
                $cssVars['--color-bg'] = $val; 
                break;
            case 'background_secondary': 
                $cssVars['--color-surface'] = $val; 
                $cssVars['--color-card'] = $val; 
                break;
            case 'text_primary': 
                $cssVars['--color-text'] = $val; 
                break;
            case 'text_secondary': 
                $cssVars['--color-muted'] = $val; 
                break;
            case 'border_color': 
                $cssVars['--color-border'] = $val; 
                break;
            default: 
                $safeKey = preg_replace('/[^a-z0-9_-]/i', '-', $key);
                $cssVars['--theme-' . $safeKey] = $val;
        }
    }
}

// Designs
if (!empty($theme['designs']) && is_array($theme['designs'])) {
    foreach ($theme['designs'] as $k => $v) {
        $key = preg_replace('/[^a-z0-9_-]/i', '-', (string)$k);
        $val = $v;
        if (is_numeric($val) && strpos((string)$val, '.') === false) {
            $val = $val . 'px';
        }
        $cssVars['--theme-' . $key] = $val;
        if ($key === 'header_height') {
            $cssVars['--header-height'] = $val;
        }
    }
}

// Fonts
if (!empty($theme['fonts']) && is_array($theme['fonts'])) {
    $bodyFontFamily = null;
    foreach ($theme['fonts'] as $f) {
        if (is_array($f)) {
            $family = $f['font_family'] ?? null;
            $url = $f['font_url'] ?? null;
            $category = $f['category'] ?? null;
            
            if ($family && $category === 'body') {
                $bodyFontFamily = $family;
            }
            if ($url) {
                $fontLinks[] = $url;
            }
        }
    }
    if ($bodyFontFamily) {
        $cssVars['--font-family'] = $bodyFontFamily;
    }
}

// Defaults
$defaults = [
    '--color-primary' => '#3B82F6',
    '--color-primary-dark' => '#2563EB',
    '--color-accent' => '#F59E0B',
    '--color-bg' => '#FFFFFF',
    '--color-surface' => '#F9FAFB',
    '--color-card' => '#ffffff',
    '--color-text' => '#111827',
    '--color-muted' => '#6B7280',
    '--color-border' => '#E5E7EB',
    '--font-family' => '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial',
    '--header-height' => '64px',
];

foreach ($defaults as $k => $v) {
    if (!isset($cssVars[$k])) {
        $cssVars[$k] = $v;
    }
}

$fontLinks = array_unique($fontLinks);
?>
<!doctype html>
<html lang="<?php echo $langAttr; ?>" dir="<?php echo $dirAttr; ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?php echo $title; ?></title>

  <?php foreach ($fontLinks as $link): ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endforeach; ?>

  <style id="theme-vars">:root {
<?php foreach ($cssVars as $name => $value): ?>
    <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>;
<?php endforeach; ?>
  }</style>

  <!-- RTL/LTR fixes -->
  <style>
  [dir="rtl"] .header-left { order: 3; }
  [dir="rtl"] .header-center { order: 2; }
  [dir="rtl"] .header-right { order: 1; }
  
  /* Ensure proper flex direction for RTL */
  [dir="rtl"] .admin-header {
    flex-direction: row;
  }
  
  /* Fix icon margins in RTL */
  [dir="rtl"] .icon-btn,
  [dir="rtl"] .btn-logout {
    margin-left: 0;
    margin-right: 8px;
  }
  
  /* Ensure avatar and user info align properly */
  [dir="rtl"] .user-menu {
    flex-direction: row;
  }
  
  /* Logo fixes for RTL */
  [dir="rtl"] .brand {
    flex-direction: row;
  }
  
  [dir="rtl"] .brand-logo {
    margin-left: 8px;
    margin-right: 0;
  }
  </style>

  <link rel="stylesheet" href="/admin/assets/css/admin.css">


  <script>
  (function(){
    try {
      window.ADMIN_UI = <?php echo $ADMIN_UI_JSON; ?>;
      window.ADMIN_LANG = window.ADMIN_UI.lang || 'en';
      window.ADMIN_DIR = window.ADMIN_UI.direction || 'ltr';
      window.CSRF_TOKEN = window.ADMIN_UI.csrf_token || '';
      window.ADMIN_USER = window.ADMIN_UI.user || {};
      
      // Update HTML attributes
      if (document.documentElement) {
        document.documentElement.lang = window.ADMIN_LANG;
        document.documentElement.dir = window.ADMIN_DIR;
      }
    } catch (e) {
      console.warn('ADMIN_UI init error', e);
      // Set minimal defaults
      window.ADMIN_UI = window.ADMIN_UI || {};
      window.ADMIN_LANG = window.ADMIN_LANG || 'en';
      window.ADMIN_DIR = window.ADMIN_DIR || 'ltr';
      window.CSRF_TOKEN = window.CSRF_TOKEN || '';
      window.ADMIN_USER = window.ADMIN_USER || {};
    }
  })();
  </script>
  <script src="/admin/assets/js/admin_core.js" defer></script>
  <script src="/admin/assets/js/sidebar-toggle.js" defer></script>
  <script src="/admin/assets/js/modal.js" defer></script>
  <script src="/admin/assets/js/menu.js" defer></script>
  <script src="/admin/assets/js/admin.js" defer></script>
</head>
<body class="admin">
  <header class="admin-header" role="banner">
    <div class="header-left">
      <button id="sidebarToggle" class="icon-btn" aria-controls="adminSidebar" aria-expanded="false"
              data-i18n-aria-label="toggle_sidebar" aria-label="Toggle sidebar">
        <span aria-hidden="true">☰</span>
      </button>
      <a class="brand" href="/admin/" data-i18n-aria-label="brand" aria-label="Admin">
        <?php
        $logoUrl = $theme['designs']['logo_url'] ?? null;
        $showImg = false;
        
        if ($logoUrl && is_string($logoUrl)) {
            // Check if it's a relative path
            if (!str_starts_with($logoUrl, 'http') && !str_starts_with($logoUrl, '//')) {
                // Make sure it starts with /
                if (!str_starts_with($logoUrl, '/')) {
                    $logoUrl = '/' . $logoUrl;
                }
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $logoUrl;
                $showImg = file_exists($fullPath) && is_readable($fullPath);
            } else {
                $showImg = true; // External URL
            }
        }
        
        if ($showImg && $logoUrl):
        ?>
          <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" 
               alt="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'Brand', ENT_QUOTES, 'UTF-8'); ?>"
               class="brand-logo" width="140" height="40" loading="eager">
        <?php else: ?>
          <span class="brand-fallback" data-i18n="brand">
            <?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['brand'] ?? 'QOOQZ', ENT_QUOTES, 'UTF-8'); ?>
          </span>
        <?php endif; ?>
      </a>
    </div>

    <div class="header-center" role="search">
      <div class="search-wrap">
        <input id="adminSearch" class="search-input" type="search"
               data-i18n-placeholder="search_placeholder"
               placeholder="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['search_placeholder'] ?? 'Search...', ENT_QUOTES, 'UTF-8'); ?>"
               aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['search_button'] ?? 'Search', ENT_QUOTES, 'UTF-8'); ?>">
        <button id="searchBtn" class="icon-btn" data-i18n-aria-label="search_button" 
                aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['search_button'] ?? 'Search', ENT_QUOTES, 'UTF-8'); ?>">
          <span aria-hidden="true">🔍</span>
        </button>
      </div>
    </div>

    <div class="header-right">
      <div class="notif-wrap" data-i18n-title="notifications" 
           title="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['notifications'] ?? 'Notifications', ENT_QUOTES, 'UTF-8'); ?>">
        <button id="notifBtn" class="icon-btn" data-i18n-aria-label="notifications" 
                aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['notifications'] ?? 'Notifications', ENT_QUOTES, 'UTF-8'); ?>">
          <span aria-hidden="true">🔔</span>
        </button>
        <span id="notifCount" class="badge" style="display:none;"></span>
      </div>

      <div class="user-menu">
        <a href="/admin/profile.php" class="user-link" 
           title="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?>">
          <img class="avatar" src="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['avatar'] ?? '/admin/assets/img/default-avatar.png', ENT_QUOTES, 'UTF-8'); ?>"
               alt="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?>" 
               width="36" height="36" loading="lazy">
        </a>
        <div class="user-info">
          <div class="username-text"><?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest', ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="user-role"><?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['user']['role'] ?? 'Administrator', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <form method="POST" action="/admin/logout.php" style="display: inline;">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <button type="submit" class="btn-logout" data-i18n-aria-label="logout" 
                  aria-label="<?php echo htmlspecialchars($ADMIN_UI_PAYLOAD['strings']['logout'] ?? 'Logout', ENT_QUOTES, 'UTF-8'); ?>">
            <span aria-hidden="true">⎋</span>
          </button>
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
      <!-- page content starts here -->