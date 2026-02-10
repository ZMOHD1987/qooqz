<?php
declare(strict_types=1);

if (php_sapi_name() === 'cli') return;

// ════════════════════════════════════════════════════════════
// SESSION
// ════════════════════════════════════════════════════════════
$sessionConfig = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/config/session.php';
if (file_exists($sessionConfig)) {
    require_once $sessionConfig;
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// Block API access
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
    http_response_code(403);
    exit('Direct access denied');
}

// ════════════════════════════════════════════════════════════
// LOAD BOOTSTRAP
// ════════════════════════════════════════════════════════════
$bootstrapPath = $_SERVER['DOCUMENT_ROOT'] . '/api/bootstrap_admin_ui.php';
$bootstrapLoaded = false;

if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
    $bootstrapLoaded = true;
    error_log('[header.php] bootstrap_admin_ui loaded');
} else {
    error_log('[header.php] bootstrap_admin_ui NOT FOUND at: ' . $bootstrapPath);
}

// ════════════════════════════════════════════════════════════
// EXTRACT PAYLOAD
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? null;

if (!$payload || !is_array($payload)) {
    error_log('[header.php] ADMIN_UI empty, creating fallback');
    
    $payload = [
        'user' => [
            'id' => $_SESSION['user_id'] ?? 0,
            'username' => $_SESSION['username'] ?? 'guest',
            'email' => $_SESSION['email'] ?? null,
            'roles' => $_SESSION['roles'] ?? [],
            'permissions' => $_SESSION['permissions'] ?? [],
            'avatar' => '/admin/assets/img/default-avatar.png',
            'preferred_language' => $_SESSION['preferred_language'] ?? 'en',
        ],
        'lang' => $_SESSION['preferred_language'] ?? 'en',
        'direction' => in_array($_SESSION['preferred_language'] ?? 'en', ['ar','fa','he','ur']) ? 'rtl' : 'ltr',
        'csrf_token' => '',
        'theme' => [
            'color_settings' => [],
            'font_settings' => [],
            'design_settings' => [],
            'button_styles' => [],
            'card_styles' => [],
            'generated_css' => '',
        ],
        'strings' => [],
        'settings' => [],
        'translation_path' => '/languages/admin/',
    ];
    
    error_log('[header.php] Fallback ADMIN_UI created');
} else {
    error_log('[header.php] ADMIN_UI loaded successfully');
}

// ════════════════════════════════════════════════════════════
// DETERMINE TRANSLATION PATH DYNAMICALLY
// ════════════════════════════════════════════════════════════
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$translationPath = '/languages/admin/'; // Default

// Define paths based on URI
$translationPaths = [
    '/users' => '/languages/Users/',
    '/tenant_users' => '/languages/TenantUsers/',
    '/dashboard' => '/languages/Dashboard/',
    // Add more as needed
];

foreach ($translationPaths as $path => $transPath) {
    if (strpos($currentUri, $path) !== false) {
        $translationPath = $transPath;
        break;
    }
}

$payload['translation_path'] = $translationPath;

// ════════════════════════════════════════════════════════════
// CSRF TOKEN
// ════════════════════════════════════════════════════════════
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$payload['csrf_token'] = $csrfToken;

// ════════════════════════════════════════════════════════════
// EXTRACT DATA
// ════════════════════════════════════════════════════════════
$user = $payload['user'] ?? [];
$lang = $payload['lang'] ?? 'en';
$dir = $payload['direction'] ?? 'ltr';
$theme = $payload['theme'] ?? [];

// ════════════════════════════════════════════════════════════
// SAFE JSON
// ════════════════════════════════════════════════════════════
function safe_json($data): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    if ($json === false) {
        error_log('[header.php] JSON encoding failed: ' . json_last_error_msg());
        return '{}';
    }
    return $json;
}

$jsonPayload = safe_json($payload);

// ════════════════════════════════════════════════════════════
// EXTRACT LOGO
// ════════════════════════════════════════════════════════════
$logo = '';
foreach ($theme['design_settings'] ?? [] as $d) {
    if (($d['setting_key'] ?? '') === 'logo_url') {
        $logo = $d['setting_value'] ?? '';
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <meta name="i18n-primary-file" content="<?= $translationPath . rawurlencode($lang) ?>.json">
    
    <title data-i18n="brand">Admin Panel</title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/admin/assets/css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/admin/assets/css/admin-overrides.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/admin/assets/css/modal.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/admin/assets/css/color-slider.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/admin/assets/css/mobile-responsive.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<!-- Admin Framework -->
    <script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
    <link rel="stylesheet" href="/admin/assets/css/admin_framework.css?v=<?= time() ?>">
    <!-- Dynamic Theme CSS -->
    <?php if (!empty($theme['generated_css'])): ?>
    <style id="dynamic-theme-db">
/* Generated CSS from AdminUiThemeLoader */
<?= $theme['generated_css'] ?>
    </style>
    <?php endif; ?>

    <!-- CSS Variables -->
    <style id="dynamic-theme-vars">
:root {
<?php foreach ($theme['color_settings'] ?? [] as $c): ?>
    --<?= htmlspecialchars($c['setting_key']) ?>: <?= htmlspecialchars($c['color_value']) ?>;
<?php endforeach; ?>
<?php foreach ($theme['font_settings'] ?? [] as $f): ?>
<?php if (!empty($f['font_family'])): ?>
    --<?= htmlspecialchars($f['setting_key']) ?>-family: <?= htmlspecialchars($f['font_family']) ?>;
<?php endif; ?>
<?php if (!empty($f['font_size'])): ?>
    --<?= htmlspecialchars($f['setting_key']) ?>-size: <?= htmlspecialchars($f['font_size']) ?>;
<?php endif; ?>
<?php if (!empty($f['font_weight'])): ?>
    --<?= htmlspecialchars($f['setting_key']) ?>-weight: <?= htmlspecialchars($f['font_weight']) ?>;
<?php endif; ?>
<?php endforeach; ?>
<?php foreach ($theme['design_settings'] ?? [] as $d): ?>
<?php if (!empty($d['setting_value'])): ?>
    --<?= htmlspecialchars($d['setting_key']) ?>: <?= htmlspecialchars($d['setting_value']) ?>;
<?php endif; ?>
<?php endforeach; ?>
}

/* Apply theme immediately */
body {
    background: var(--background-main, var(--background_main, #0A0A0A));
    color: var(--text-primary, var(--text_primary, #FFFFFF));
}

.admin-header {
    background: var(--sidebar-background, var(--sidebar_background, #4B0082));
    color: var(--sidebar-text, var(--sidebar_text, #FFFFFF));
}

.admin-sidebar {
    background: var(--sidebar-background, var(--sidebar_background, #4B0082));
    color: var(--sidebar-text, var(--sidebar_text, #FFFFFF));
}
    </style>

    <!-- Load Google Fonts -->
    <?php 
    $loadedFonts = [];
    foreach ($theme['font_settings'] ?? [] as $f): 
        if (empty($f['font_family'])) continue;
        if (preg_match('/system|arial|verdana/i', $f['font_family'])) continue;
        if (in_array($f['font_family'], $loadedFonts)) continue;
        $loadedFonts[] = $f['font_family'];
    ?>
    <?php if (!empty($f['font_url'])): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($f['font_url']) ?>">
    <?php else: ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= urlencode(str_replace(' ', '+', $f['font_family'])) ?>&display=swap">
    <?php endif; ?>
    <?php endforeach; ?>

    <!-- Inject ADMIN_UI -->
    <script id="admin-ui-injection">
(function() {
    'use strict';
    
    window.ADMIN_UI = <?= $jsonPayload ?>;
    window.ADMIN_LANG = '<?= $lang ?>';
    window.ADMIN_DIR = '<?= $dir ?>';
    window.CSRF_TOKEN = '<?= $csrfToken ?>';
    window.ADMIN_USER = window.ADMIN_UI.user || {};

    document.documentElement.lang = window.ADMIN_LANG;
    document.documentElement.dir = window.ADMIN_DIR;

    console.log('%c════════════════════════════════════', 'color: #3B82F6');
    console.log('%c✓ ADMIN UI Loaded', 'color: #10B981; font-weight: bold');
    console.log('%c════════════════════════════════════', 'color: #3B82F6');
    console.log('Language:', window.ADMIN_LANG);
    console.log('Direction:', window.ADMIN_DIR);
    console.log('Translation Path:', window.ADMIN_UI.translation_path);
    console.log('User:', window.ADMIN_USER.username);
    console.log('Theme colors:', window.ADMIN_UI?.theme?.color_settings?.length || 0);
    console.log('Bootstrap loaded:', <?= $bootstrapLoaded ? 'true' : 'false' ?>);
})();
    </script>

    <!-- Core JS -->
    <script src="/admin/assets/js/admin_core.js" defer></script>
    <script src="/admin/assets/js/sidebar-toggle.js" defer></script>
    <script src="/admin/assets/js/modal.js" defer></script>
</head>
<body class="admin">

<!-- ════════════════════════════════════════════════════════════
     HEADER
     ════════════════════════════════════════════════════════════ -->
<header class="admin-header" role="banner">
    <div class="header-left">
        <button id="sidebarToggle" 
                class="icon-btn" 
                type="button"
                aria-controls="adminSidebar" 
                aria-expanded="false"
                data-i18n-aria-label="toggle_sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <a class="brand" href="/admin/">
            <?php if ($logo && (file_exists($_SERVER['DOCUMENT_ROOT'] . $logo) || filter_var($logo, FILTER_VALIDATE_URL))): ?>
                <img src="<?= htmlspecialchars($logo) ?>" 
                     alt="Logo" 
                     class="brand-logo" 
                     width="140" 
                     height="40" 
                     loading="eager">
            <?php else: ?>
                <span class="brand-text" data-i18n="brand">Admin Panel</span>
            <?php endif; ?>
        </a>
    </div>

    <div class="header-center">
        <div class="search-wrap">
            <input id="adminSearch" 
                   type="search" 
                   placeholder="Search..."
                   data-i18n-placeholder="search_placeholder"
                   autocomplete="off">
            <button id="searchBtn" 
                    class="icon-btn" 
                    type="button">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>

    <div class="header-right">
        <button id="notifBtn" 
                class="icon-btn" 
                type="button"
                data-i18n-aria-label="notifications">
            <i class="fas fa-bell"></i>
            <span id="notifCount" class="badge" style="display:none;">0</span>
        </button>

        <div class="user-menu">
            <a href="/admin/profile.php" class="user-link">
                <img class="avatar" 
                     src="<?= htmlspecialchars($user['avatar'] ?? '/admin/assets/img/default-avatar.png') ?>" 
                     alt="<?= htmlspecialchars($user['username'] ?? 'User') ?>" 
                     width="36" 
                     height="36" 
                     loading="lazy"
                     onerror="this.src='/admin/assets/img/default-avatar.png'">
            </a>
            <div class="user-info">
                <div class="username"><?= htmlspecialchars($user['username'] ?? 'Guest') ?></div>
                <div class="user-role"><?= htmlspecialchars($user['roles'][0] ?? 'User') ?></div>
            </div>
            <form method="POST" action="/admin/logout.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" 
                        class="btn-logout"
                        data-i18n-aria-label="logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </div>
</header>

<!-- ════════════════════════════════════════════════════════════
     LAYOUT
     ════════════════════════════════════════════════════════════ -->
<div class="admin-layout">
    <aside id="adminSidebar" 
           class="admin-sidebar" 
           role="navigation">
        <?php
        $menuFile = __DIR__ . '/menu.php';
        if (is_readable($menuFile)) {
            include $menuFile;
        } else {
            echo '<p style="padding:1rem; color:rgba(255,255,255,0.7);" data-i18n="menu_unavailable">Menu not available</p>';
        }
        ?>
    </aside>

    <div class="sidebar-backdrop" aria-hidden="true"></div>

    <main id="adminMainContent" class="admin-main" role="main">