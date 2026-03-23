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
// 3. Theme → CSS custom properties (matches admin/includes/header.php approach)
//    Processes: color_settings, font_settings, design_settings
//    Creates both underscore AND hyphen variants in a single pass
// ════════════════════════════════════════════════════════════

/** Validate a CSS value — only safe colors, lengths, font names allowed */
if (!function_exists('_pub_safe_css_val')) {
    function _pub_safe_css_val(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        // hex color
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v)) return $v;
        // rgb/rgba/hsl/hsla
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/]+\)$/i', $v)) return $v;
        // named color (2-30 alpha chars)
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v)) return $v;
        // CSS variable reference
        if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $v)) return $v;
        // CSS length (e.g. 16px, 1.5rem, 100%)
        if (preg_match('/^[\d.]+(px|em|rem|%|vh|vw|pt|ch|ex)$/', $v)) return $v;
        // Numeric (e.g. font-weight 400, 700)
        if (preg_match('/^\d{1,4}$/', $v)) return $v;
        // Quoted font name
        if (preg_match('/^"[a-zA-Z0-9\s\-]{1,60}"$/', $v)) return $v;
        // Font stack (comma-separated, alphanumeric + spaces + quotes)
        if (preg_match('/^[a-zA-Z0-9\s,"\'\-]+$/', $v) && strlen($v) <= 200) return $v;
        return '';
    }
}

$_cssVars = []; // ['--var-name' => 'value'] map

$_setVar = function (string $key, string $value) use (&$_cssVars): void {
    if ($value === '') return;
    $safe = _pub_safe_css_val($value);
    if ($safe === '') return;
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    $keyU = '--' . str_replace('-', '_', $sanitized);
    $keyH = '--' . str_replace('_', '-', $sanitized);
    $_cssVars[$keyU] = $safe;
    if ($keyH !== $keyU) {
        $_cssVars[$keyH] = $safe;
    }
};

// ── Color settings ────────────────────────────────────────
foreach ($theme['color_settings'] ?? [] as $cs) {
    $k = trim($cs['setting_key'] ?? '');
    $v = trim($cs['color_value']  ?? ($cs['setting_value'] ?? ''));
    if ($k !== '' && $v !== '') {
        $_setVar($k, $v);
    }
}

// ── Font settings (matches admin approach) ────────────────
foreach ($theme['font_settings'] ?? [] as $f) {
    $k = trim($f['setting_key'] ?? '');
    if ($k === '') continue;
    if (!empty($f['font_family'])) $_setVar("{$k}_family", $f['font_family']);
    if (!empty($f['font_size']))   $_setVar("{$k}_size",   $f['font_size']);
    if (!empty($f['font_weight'])) $_setVar("{$k}_weight", (string)$f['font_weight']);
}

// ── Design settings ───────────────────────────────────────
foreach ($theme['design_settings'] ?? [] as $d) {
    $k = trim($d['setting_key']   ?? '');
    $v = trim($d['setting_value'] ?? '');
    if ($k !== '' && $v !== '' && $k !== 'logo_url') {
        $_setVar($k, $v);
    }
}

// ── Alias vars for compatibility (same as admin _header_build_alias_vars) ──
$_aliasVars = [];
$_getVar = function (string ...$names) use (&$_cssVars): string {
    foreach ($names as $n) {
        if (isset($_cssVars[$n]) && $_cssVars[$n] !== '') return $_cssVars[$n];
    }
    return '';
};
$_alias = function (string $target, string ...$sources) use (&$_cssVars, $_getVar, &$_aliasVars): void {
    if (isset($_cssVars[$target]) && $_cssVars[$target] !== '') return;
    $v = $_getVar(...$sources);
    if ($v !== '') $_aliasVars[$target] = $v;
};

$_alias('--surface-color',       '--surface_color', '--background-secondary', '--background_secondary');
$_alias('--card-bg',             '--card_bg',       '--surface-color', '--background-secondary');
$_alias('--input-bg',            '--input_bg',      '--surface-color', '--background-secondary');
$_alias('--danger-color',        '--danger_color',  '--error-color',   '--error_color');
$_alias('--error-color',         '--error_color',   '--danger-color',  '--danger_color');
$_alias('--info-color',          '--info_color',    '--primary-color', '--primary_color');
$_alias('--text-secondary',      '--text_secondary', '--text-muted', '--text-light');
$_alias('--border-color',        '--border_color',  '--border', '--divider-color');
$_alias('--sidebar-hover',       '--sidebar_hover',  '--primary-color', '--primary_color');
$_alias('--sidebar-active',      '--sidebar_active', '--primary-color', '--primary_color');

// Build the :root CSS block
$_themeVars = '';
$_allVars = array_merge($_cssVars, $_aliasVars);
if (!empty($_allVars)) {
    $parts = [];
    foreach ($_allVars as $name => $value) {
        $parts[] = '    ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                 . ': '   . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ';';
    }
    $_themeVars = implode("\n", $parts);
}

// ════════════════════════════════════════════════════════════
// 4. Font detection — also collect DB font links (like admin)
// ════════════════════════════════════════════════════════════
$_fontUrl = $dir === 'rtl'
    ? 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap'
    : 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';

// Collect additional font URLs from DB font_settings
$_dbFontLinks = [];
$_systemFonts = ['system-ui','sans-serif','serif','monospace','arial','verdana','helvetica','georgia','times','courier','tahoma','inherit','initial','unset'];
$_trustedFontHosts = ['fonts.googleapis.com', 'fonts.gstatic.com'];
foreach ($theme['font_settings'] ?? [] as $_f) {
    if (empty($_f['font_family'])) continue;
    if (!empty($_f['font_url'])) {
        $_url = $_f['font_url'];
        // Validate font URL points to trusted source only
        $_urlHost = parse_url($_url, PHP_URL_HOST);
        if (!$_urlHost || !in_array($_urlHost, $_trustedFontHosts, true)) continue;
    } else {
        $_primary = trim(explode(',', $_f['font_family'])[0], " \"'");
        if ($_primary === '' || in_array(strtolower($_primary), $_systemFonts, true)) continue;
        $_url = 'https://fonts.googleapis.com/css2?family=' . urlencode(str_replace(' ', '+', $_primary)) . ':wght@400;500;600;700&display=swap';
    }
    if (!in_array($_url, $_dbFontLinks, true)) {
        $_dbFontLinks[] = $_url;
    }
}

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

    <!-- Fonts (default + DB fonts) -->
    <link rel="preload" href="<?= e($_fontUrl) ?>" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($_fontUrl) ?>"></noscript>
    <?php foreach ($_dbFontLinks as $_dbFont): ?>
    <link rel="preload" href="<?= e($_dbFont) ?>" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($_dbFont) ?>"></noscript>
    <?php endforeach; ?>

    <!-- ════════════════════════════════════════════════════
         Public Stylesheet — loaded FIRST so DB theme :root overrides its defaults
         ════════════════════════════════════════════════════ -->
    <link rel="stylesheet"
          href="/frontend/assets/css/public.css?v=<?= _pub_asset_ver('/frontend/assets/css/public.css') ?>">

    <!-- ════════════════════════════════════════════════════
         Theme CSS Variables (DB-driven) — loaded AFTER public.css
         so DB colors override the CSS default values
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
     HEADER — hamburger + logo + search
     Menu button on start side (right for RTL, left for LTR).
     Logo from DB (design_settings). Search on all pages.
     Colors come from DB theme via CSS custom properties.
============================================= -->
<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">

        <!-- Hamburger toggle — start side (first in DOM → right in RTL, left in LTR) -->
        <button class="pub-hamburger" id="pubHamburger"
                aria-label="<?= e(t('nav.menu_open')) ?>"
                aria-expanded="false" aria-controls="pubSidebar">
            <span></span><span></span><span></span>
        </button>

        <!-- Logo (DB design_settings or fallback) -->
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

        <!-- Search field — visible on all pages -->
        <form class="pub-header-search" method="get" action="<?= e($_basePath . '/products.php') ?>" id="pubHeaderSearchForm">
            <input type="search" name="q" class="pub-header-search-input"
                   placeholder="<?= e(t('search.placeholder')) ?>"
                   value="<?= e($_GET['q'] ?? '') ?>"
                   autocomplete="off">
            <button type="submit" class="pub-header-search-btn" aria-label="<?= e(t('search.button')) ?>">🔍</button>
        </form>
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
