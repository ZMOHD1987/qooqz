<?php
declare(strict_types=1);
/**
 * frontend/partials/header.php
 * QOOQZ — Public Interface Header
 *
 * Responsibilities:
 *   1. HTML <head> with public CSS, fonts, meta tags, SEO
 *   2. <header> bar: logo + hamburger toggle (NO menus — menus live in menu.php)
 *   3. Opens <div class="pub-layout"> and includes menu.php sidebar
 *   4. Opens <main class="pub-main-content"> for page content
 *
 * The footer.php closes </main> and </div>.
 * Menu.php renders the sidebar navigation independently.
 */

// ════════════════════════════════════════════════════════════
// 0. Guard: no CLI, no direct /api/ access
// ════════════════════════════════════════════════════════════
if (php_sapi_name() === 'cli') {
    return;
}
if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    http_response_code(403);
    exit('Direct access denied');
}

// ════════════════════════════════════════════════════════════
// 1. Context — loaded by public_context.php before this file
// ════════════════════════════════════════════════════════════
$_ctx      = $GLOBALS['PUB_CONTEXT'] ?? [];
$lang      = $_ctx['lang'] ?? 'ar';
$dir       = $_ctx['dir']  ?? 'rtl';
$theme     = $_ctx['theme'] ?? [];
$_seo      = $_ctx['seo']  ?? [];
$_user     = $_ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);
$_appName   = $GLOBALS['PUB_APP_NAME']  ?? 'QOOQZ';
$_pageTitle = $GLOBALS['PUB_PAGE_TITLE'] ?? ($_seo['title'] ?? $_appName);
$_pageDesc  = $GLOBALS['PUB_PAGE_DESC']  ?? ($_seo['description'] ?? '');
$_basePath  = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
$_authPath  = '/frontend'; // Auth pages (login, register, profile, logout) at /frontend/ level

// ════════════════════════════════════════════════════════════
// 2. Helpers
// ════════════════════════════════════════════════════════════
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('t')) {
    function t(string $key, array $r = []): string { return $key; }
}

// ════════════════════════════════════════════════════════════
// 3. Theme colors → CSS custom properties
// ════════════════════════════════════════════════════════════
$_themeVars = '';
if (!empty($theme['color_settings']) && is_array($theme['color_settings'])) {
    $parts = [];
    foreach ($theme['color_settings'] as $cs) {
        $key = $cs['setting_key'] ?? '';
        $val = $cs['color_value']  ?? ($cs['setting_value'] ?? '');
        if ($key !== '' && $val !== '') {
            $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
            // Strict CSS color validation — reject anything that could be CSS injection
            $v = trim($val);
            $safeVal = '';
            if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v)) {
                $safeVal = $v; // hex color
            } elseif (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[\d\s%,.]+\)$/i', $v)) {
                $safeVal = $v; // rgb/rgba/hsl/hsla
            } elseif (preg_match('/^[a-zA-Z]{2,30}$/', $v)) {
                $safeVal = $v; // named color
            } elseif (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $v)) {
                $safeVal = $v; // CSS variable reference
            } elseif (preg_match('/^[\d.]+(px|em|rem|%|vh|vw|pt|ch|ex|cm|mm|in|pc)$/', $v)) {
                $safeVal = $v; // CSS length/size
            } elseif (preg_match('/^"[^"]{1,80}"$/', $v) || preg_match("/^'[^']{1,80}'$/", $v)) {
                $safeVal = $v; // quoted font name
            }
            if ($safeKey && $safeVal) {
                $parts[] = '    --' . $safeKey . ': ' . $safeVal . ';';
                // Also set hyphenated variant
                $hk = str_replace('_', '-', $safeKey);
                if ($hk !== $safeKey) {
                    $parts[] = '    --' . $hk . ': ' . $safeVal . ';';
                }
            }
        }
    }
    if ($parts) {
        $_themeVars = implode("\n", $parts);
    }
}

// ════════════════════════════════════════════════════════════
// 4. Font detection
// ════════════════════════════════════════════════════════════
$_fontUrl = $dir === 'rtl'
    ? 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap'
    : 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';

// ════════════════════════════════════════════════════════════
// 5. Logo
// ════════════════════════════════════════════════════════════
$_logoUrl = '';
if (!empty($theme['design_settings']) && is_array($theme['design_settings'])) {
    foreach ($theme['design_settings'] as $d) {
        if (($d['setting_key'] ?? '') === 'logo_url' && !empty($d['setting_value'])) {
            $_logoUrl = $d['setting_value'];
            break;
        }
    }
}
// Fallback: check for static logo files
if (empty($_logoUrl)) {
    foreach (['logo.png', 'logo.svg', 'logo.webp'] as $_lf) {
        if (@file_exists(FRONTEND_BASE . '/assets/images/' . $_lf)) {
            $_logoUrl = '/frontend/assets/images/' . $_lf;
            break;
        }
    }
}

// ════════════════════════════════════════════════════════════
// 6. Cache-busting helper
// ════════════════════════════════════════════════════════════
if (!function_exists('_pub_asset_ver')) {
    function _pub_asset_ver(string $path): string {
        $full = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $path;
        return file_exists($full) ? (string)filemtime($full) : '1';
    }
}

// ════════════════════════════════════════════════════════════
// 7. (Reserved — nav/notification removed; all navigation in menu.php)
// ════════════════════════════════════════════════════════════
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="index, follow">
    <meta name="referrer" content="strict-origin-when-cross-origin">

    <!-- SEO -->
    <title><?= e($_pageTitle) ?></title>
    <?php if ($_pageDesc): ?>
    <meta name="description" content="<?= e($_pageDesc) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title"       content="<?= e($_pageTitle) ?>">
    <meta property="og:description" content="<?= e($_pageDesc) ?>">
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= e($_appName) ?>">

    <!-- PWA / Mobile -->
    <meta name="mobile-web-app-capable"               content="yes">
    <meta name="apple-mobile-web-app-capable"         content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#2d8cf0">

    <!-- DNS / Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Fonts -->
    <link rel="preload" href="<?= e($_fontUrl) ?>" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($_fontUrl) ?>"></noscript>

    <!-- ════════════════════════════════════════════════════
         Theme CSS Variables (DB-driven)
         ════════════════════════════════════════════════════ -->
    <?php if ($_themeVars): ?>
    <style id="pub-theme-vars">
:root {
<?= $_themeVars . "\n" ?>}
    </style>
    <?php endif; ?>

    <?php if (!empty($theme['generated_css'])): ?>
    <style id="pub-theme-generated"><?= $theme['generated_css'] ?></style>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════════════
         Public Stylesheet (single entry — imports variables, base, layout, etc.)
         ════════════════════════════════════════════════════ -->
    <link rel="stylesheet"
          href="/frontend/assets/css/public.css?v=<?= _pub_asset_ver('/frontend/assets/css/public.css') ?>">

    <!-- Homepage engine JS (deferred) -->
    <script defer
            src="/frontend/assets/js/homepage-engine.js?v=<?= _pub_asset_ver('/frontend/assets/js/homepage-engine.js') ?>">
    </script>

    <!-- Slider JS (deferred) -->
    <script defer
            src="/frontend/assets/js/slider.js?v=<?= _pub_asset_ver('/frontend/assets/js/slider.js') ?>">
    </script>
</head>

<body class="pub-body <?= e($dir) ?>">

<!-- =============================================
     HEADER — Clean bar: logo + hamburger ONLY
     All navigation lives in menu.php (sidebar).
     Colors come from DB theme via CSS custom properties.
============================================= -->
<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">

        <!-- Logo -->
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-logo" aria-label="<?= e($_appName) ?>">
            <?php if (!empty($_logoUrl)): ?>
                <img src="<?= e($_logoUrl) ?>" alt="<?= e($_appName) ?>" class="pub-logo-img"
                     loading="eager" decoding="async">
                <span class="pub-logo-name"><?= e($_appName) ?></span>
            <?php else: ?>
                <span class="pub-logo-icon" aria-hidden="true">🌐</span>
                <span class="pub-logo-name"><?= e($_appName) ?></span>
            <?php endif; ?>
        </a>

        <!-- Hamburger toggle (controls sidebar in menu.php) -->
        <button class="pub-hamburger" id="pubHamburger"
                aria-label="<?= e(t('nav.menu_open')) ?>"
                aria-expanded="false" aria-controls="pubSidebar">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<!-- =============================================
     LAYOUT: sidebar (from menu.php) + main content
============================================= -->
<div class="pub-layout">

    <?php
    // Include the sidebar menu — completely separate from header
    $menuFile = __DIR__ . '/menu.php';
    if (is_readable($menuFile)) {
        include $menuFile;
    }
    ?>

    <!-- Mobile sidebar backdrop -->
    <div class="pub-sidebar-backdrop" id="pubSidebarOverlay" aria-hidden="true"></div>

    <!-- Main content area — page content goes here -->
    <main class="pub-main-content">
