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
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <meta name="i18n-primary-file" content="<?= $translationPath . rawurlencode($lang) ?>.json">

    <!-- PWA / Mobile App Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" data-i18n="brand" content="Admin">
    <meta name="application-name" data-i18n="brand" content="Admin">
    <meta name="theme-color" content="#1e2533">
    <link rel="manifest" href="/admin/manifest.json">

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

    <!-- CSS Variables — DB-driven (both underscore and hyphen forms for full compatibility) -->
    <style id="dynamic-theme-vars">
:root {
<?php
// ── Color settings (both underscore and hyphenated) ──────────────────────────
$_emittedVars = [];
foreach ($theme['color_settings'] ?? [] as $c):
    $k = htmlspecialchars($c['setting_key']);
    $h = htmlspecialchars(str_replace('_', '-', $c['setting_key']));
    $v = htmlspecialchars($c['color_value']);
    $_emittedVars['--' . $k] = $v;
    $_emittedVars['--' . $h] = $v;
?>
    --<?= $k ?>: <?= $v ?>;
<?php if ($h !== $k): ?>    --<?= $h ?>: <?= $v ?>;
<?php endif; endforeach; ?>
<?php foreach ($theme['font_settings'] ?? [] as $f):
    $sk = htmlspecialchars($f['setting_key']);
    $sh = htmlspecialchars(str_replace('_', '-', $f['setting_key']));
?>
<?php if (!empty($f['font_family'])): ?>
    --<?= $sk ?>-family: <?= htmlspecialchars($f['font_family']) ?>;
    --<?= $sh ?>-family: <?= htmlspecialchars($f['font_family']) ?>;
<?php endif; ?>
<?php if (!empty($f['font_size'])): ?>
    --<?= $sk ?>-size: <?= htmlspecialchars($f['font_size']) ?>;
    --<?= $sh ?>-size: <?= htmlspecialchars($f['font_size']) ?>;
<?php endif; ?>
<?php if (!empty($f['font_weight'])): ?>
    --<?= $sk ?>-weight: <?= htmlspecialchars($f['font_weight']) ?>;
    --<?= $sh ?>-weight: <?= htmlspecialchars($f['font_weight']) ?>;
<?php endif; ?>
<?php endforeach; ?>
<?php foreach ($theme['design_settings'] ?? [] as $d):
    if (empty($d['setting_value'])) continue;
    $dk = htmlspecialchars($d['setting_key']);
    $dh = htmlspecialchars(str_replace('_', '-', $d['setting_key']));
    $dv = htmlspecialchars($d['setting_value']);
?>
    --<?= $dk ?>: <?= $dv ?>;
<?php if ($dh !== $dk): ?>    --<?= $dh ?>: <?= $dv ?>;
<?php endif; ?>
<?php endforeach; ?>
<?php
// ── Alias vars — provide the stable names used by CSS files ─────────────────
// These map framework-level CSS var names to values from the DB so that
// CSS var() references work before admin_core.js runs.
$backgroundSecondary = $_emittedVars['--background-secondary'] ?? $_emittedVars['--background_secondary'] ?? null;
$backgroundPrimary   = $_emittedVars['--background-primary']   ?? $_emittedVars['--background_primary']   ?? null;
$backgroundMain      = $_emittedVars['--background-main']      ?? $_emittedVars['--background_main']      ?? null;
$backgroundFallback  = $backgroundSecondary ?? $backgroundPrimary ?? $backgroundMain;
$errorColor          = $_emittedVars['--error-color']  ?? $_emittedVars['--error_color']  ?? null;

// Map: CSS-var-name => resolved value (only emit if not already set from DB)
$themeAliasVars = [
    '--card-bg'          => $_emittedVars['--card-bg']          ?? $_emittedVars['--card_bg']          ?? $backgroundFallback,
    '--input-bg'         => $_emittedVars['--input-bg']         ?? $_emittedVars['--input_bg']         ?? $backgroundFallback,
    '--input-background' => $_emittedVars['--input-background'] ?? $_emittedVars['--input_background'] ?? $backgroundFallback,
    '--thead-bg'         => $_emittedVars['--thead-bg']         ?? $_emittedVars['--thead_bg']         ?? $backgroundFallback,
    '--danger-color'     => $_emittedVars['--danger-color']     ?? $_emittedVars['--danger_color']     ?? $errorColor,
];
foreach ($themeAliasVars as $varName => $varValue):
    if (!$varValue || !empty($_emittedVars[$varName])) continue; // skip if already emitted or no value
?>    <?= $varName ?>: <?= $varValue ?>;
<?php endforeach; ?>
}

/* Apply theme immediately — these rules rely solely on DB CSS vars */
body {
    background: var(--background_main, #0a0a0a);
    color: var(--text_primary, #ffffff);
    font-family: var(--body_font-family, inherit);
}

.admin-header {
    background: var(--header_background, var(--background_secondary, #1e2533));
    color: var(--header_text, var(--text_primary, #FFFFFF));
    border-bottom: 1px solid var(--border_color, #334155);
}

.admin-sidebar {
    background: var(--sidebar_background, #4B0082);
    color: var(--sidebar_text, #ffffff);
}
    </style>

    <!-- Load Google Fonts -->
    <?php 
    $loadedFonts = [];
    $genericFontNames = ['sans-serif','serif','monospace','cursive','fantasy','system-ui','ui-sans-serif','ui-serif','ui-monospace'];
    foreach ($theme['font_settings'] ?? [] as $f): 
        if (empty($f['font_family'])) continue;
        // Extract only the primary font name (DB may store "Roboto, sans-serif"; Google Fonts needs just "Roboto")
        $primaryFont = trim(explode(',', $f['font_family'])[0]);
        // Skip generic/system fonts that cannot be loaded from Google Fonts
        if (in_array(strtolower($primaryFont), $genericFontNames)) continue;
        if (preg_match('/\b(system|arial|verdana|helvetica|georgia|times|courier|impact)\b/i', $primaryFont)) continue;
        if (in_array($primaryFont, $loadedFonts)) continue;
        $loadedFonts[] = $primaryFont;
    ?>
    <?php if (!empty($f['font_url'])): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($f['font_url']) ?>">
    <?php else: ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= urlencode($primaryFont) ?>&display=swap">
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
            echo '<p style="padding:1rem; color:var(--sidebar_text);" data-i18n="menu_unavailable">Menu not available</p>';
        }
        ?>
    </aside>

    <div class="sidebar-backdrop" aria-hidden="true"></div>

    <main id="adminMainContent" class="admin-main" role="main">