<?php
declare(strict_types=1);

/**
 * /admin/includes/header.php
 * Production v3.0 — Single Source of Truth
 *
 * ─ مبادئ التصميم ─────────────────────────────────────────────
 * 1. مصدر واحد للـ CSS variables  → يُعيَّن هنا فقط، لا في JS ولا في theme_injector
 * 2. لا !important على colors     → القواعد الديناميكية تفوز بالـ specificity الطبيعي
 * 3. لا race condition            → كل CSS يُحقن قبل <body>
 * 4. لا تكرار للـ vars            → underscore + hyphen في مرور واحد
 * 5. generateComponentStyles() في JS للقراءة فقط من المتغيرات المُعيَّنة هنا
 * ─────────────────────────────────────────────────────────────
 */

// ════════════════════════════════════════════════════════════
// 0. حماية: لا تشغيل من CLI ولا من /api/
// ════════════════════════════════════════════════════════════
if (php_sapi_name() === 'cli') {
    return;
}

if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    http_response_code(403);
    exit('Direct access denied');
}

// ════════════════════════════════════════════════════════════
// 1. SESSION
// ════════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) {
    $sessionConfig = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/config/session.php';
    if (file_exists($sessionConfig)) {
        require_once $sessionConfig;
    } else {
        session_start([
            'cookie_secure'   => !empty($_SERVER['HTTPS']),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }
}

// ════════════════════════════════════════════════════════════
// 2. CSRF TOKEN
// ════════════════════════════════════════════════════════════
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');

// ════════════════════════════════════════════════════════════
// 3. BOOTSTRAP — يُحمّل ADMIN_UI من DB مرة واحدة
// ════════════════════════════════════════════════════════════
$bootstrapPath = $_SERVER['DOCUMENT_ROOT'] . '/api/bootstrap_admin_ui.php';
if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
}

// ════════════════════════════════════════════════════════════
// 4. PAYLOAD — مع قيم افتراضية آمنة
// ════════════════════════════════════════════════════════════
$payload = $GLOBALS['ADMIN_UI'] ?? [];

if (empty($payload) || !is_array($payload)) {
    $payload = _header_fallback_payload($csrfToken);
}

// حقن CSRF في الـ payload
$payload['csrf_token'] = $csrfToken;

// ════════════════════════════════════════════════════════════
// 5. مسار الترجمة — يُحدَّد من URL
// ════════════════════════════════════════════════════════════
$payload['translation_path'] = _header_resolve_translation_path($_SERVER['REQUEST_URI'] ?? '');

// ════════════════════════════════════════════════════════════
// 6. استخراج البيانات الأساسية
// ════════════════════════════════════════════════════════════
$user  = $payload['user']  ?? [];
$lang  = $payload['lang']  ?? 'en';
$dir   = $payload['direction'] ?? 'ltr';
$theme = $payload['theme'] ?? [];

// ════════════════════════════════════════════════════════════
// 7. بناء CSS Variables بشكل موحّد (مرور واحد)
// ════════════════════════════════════════════════════════════
$cssVars   = _header_build_css_vars($theme);        // المتغيرات الأساسية
$aliasVars = _header_build_alias_vars($cssVars);    // المتغيرات البديلة (إزالة التعارضات)
$fontLinks = _header_collect_font_links($theme);    // روابط Google Fonts
$logo      = _header_extract_logo($theme);

// ════════════════════════════════════════════════════════════
// 8. JSON آمن للـ ADMIN_UI
// ════════════════════════════════════════════════════════════
$jsonPayload = _header_safe_json($payload);

// ════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ════════════════════════════════════════════════════════════

function _header_fallback_payload(string $csrfToken): array
{
    $lang = $_SESSION['preferred_language'] ?? 'en';
    return [
        'user' => [
            'id'                 => $_SESSION['user_id']   ?? 0,
            'username'           => $_SESSION['username']  ?? 'guest',
            'email'              => $_SESSION['email']     ?? '',
            'roles'              => $_SESSION['roles']     ?? [],
            'permissions'        => $_SESSION['permissions'] ?? [],
            'resource_permissions' => $_SESSION['resource_permissions'] ?? [],
            'avatar'             => '/admin/assets/img/default-avatar.png',
            'preferred_language' => $lang,
            'timezone'           => 'UTC',
            'is_active'          => false,
        ],
        'lang'             => $lang,
        'direction'        => in_array($lang, ['ar','fa','he','ur'], true) ? 'rtl' : 'ltr',
        'csrf_token'       => $csrfToken,
        'tenant_id'        => (int)($_SESSION['tenant_id'] ?? 1),
        'is_super_admin'   => false,
        'theme' => [
            'color_settings'  => [],
            'font_settings'   => [],
            'design_settings' => [],
            'button_styles'   => [],
            'card_styles'     => [],
            'generated_css'   => '',
        ],
        'strings'          => [],
        'settings'         => [],
        'translation_path' => '/languages/admin/',
    ];
}

function _header_resolve_translation_path(string $uri): string
{
    $map = [
        '/users'        => '/languages/Users/',
        '/tenant_users' => '/languages/TenantUsers/',
        '/dashboard'    => '/languages/Dashboard/',
    ];
    foreach ($map as $segment => $path) {
        if (str_contains($uri, $segment)) {
            return $path;
        }
    }
    return '/languages/admin/';
}

/**
 * يبني مصفوفة ['--var-name' => 'value'] من إعدادات الـ Theme
 * ─ يُعيَّن كل مفتاح بشكلَيه (underscore + hyphen) في مرور واحد
 * ─ لا يُصدر !important
 */
function _header_build_css_vars(array $theme): array
{
    $vars = [];

    $set = function (string $key, string $value) use (&$vars): void {
        if ($value === '') {
            return;
        }
        $keyU = '--' . str_replace('-', '_', $key);
        $keyH = '--' . str_replace('_', '-', $key);
        $vars[$keyU] = $value;
        if ($keyH !== $keyU) {
            $vars[$keyH] = $value;
        }
    };

    // ── Color settings ────────────────────────────────────────
    foreach ($theme['color_settings'] ?? [] as $c) {
        $k = trim($c['setting_key'] ?? '');
        $v = trim($c['color_value']  ?? '');
        if ($k !== '' && $v !== '') {
            $set($k, $v);
        }
    }

    // ── Font settings ─────────────────────────────────────────
    foreach ($theme['font_settings'] ?? [] as $f) {
        $k = trim($f['setting_key'] ?? '');
        if ($k === '') {
            continue;
        }
        if (!empty($f['font_family'])) {
            $set("{$k}_family", $f['font_family']);
        }
        if (!empty($f['font_size'])) {
            $set("{$k}_size", $f['font_size']);
        }
        if (!empty($f['font_weight'])) {
            $set("{$k}_weight", (string) $f['font_weight']);
        }
    }

    // ── Design settings ───────────────────────────────────────
    foreach ($theme['design_settings'] ?? [] as $d) {
        $k = trim($d['setting_key']   ?? '');
        $v = trim($d['setting_value'] ?? '');
        if ($k !== '' && $v !== '') {
            $set($k, $v);
        }
    }

    return $vars;
}

/**
 * يبني المتغيرات البديلة (aliases) لضمان توافق أسماء الـ CSS vars
 * عبر كل ملفات CSS القديمة — بدون تعارض مع ما يُعيَّن من DB.
 */
function _header_build_alias_vars(array $vars): array
{
    $aliases = [];

    $get = static function (string ...$names) use ($vars): string {
        foreach ($names as $n) {
            if (isset($vars[$n]) && $vars[$n] !== '') {
                return $vars[$n];
            }
        }
        return '';
    };

    $alias = static function (string $target, string ...$sources) use ($vars, $get, &$aliases): void {
        // لا تُعيَّن alias إذا كانت مُعيَّنة بالفعل من DB
        if (isset($vars[$target]) && $vars[$target] !== '') {
            return;
        }
        $v = $get(...$sources);
        if ($v !== '') {
            $aliases[$target] = $v;
        }
    };

    // Surface / background
    $bg2 = $get('--background_secondary', '--background-secondary',
                '--background_primary',   '--background-primary',
                '--background_main',      '--background-main');

    $alias('--surface-color',      '--surface_color', '--background-secondary', '--background_secondary');
    $alias('--card-bg',            '--card_bg',       '--surface-color', '--background-secondary');
    $alias('--input-bg',           '--input_bg',      '--surface-color', '--background-secondary');
    $alias('--input-background',   '--input_background', '--input-bg', '--input_bg');
    $alias('--thead-bg',           '--thead_bg',      '--background-tertiary', '--background_tertiary',
                                                      '--background-secondary', '--background_secondary');
    $alias('--background-tertiary','--background_tertiary', '--background-secondary');

    // Colors
    $alias('--danger-color',  '--danger_color',  '--error-color',   '--error_color');
    $alias('--error-color',   '--error_color',   '--danger-color',  '--danger_color');
    $alias('--info-color',    '--info_color',    '--primary-color', '--primary_color');

    // Text
    $alias('--text-secondary', '--text_secondary', '--text-muted', '--text-light');
    $alias('--text-tertiary',  '--text_tertiary',  '--text-secondary', '--text_secondary');

    // Border
    $alias('--border-color', '--border_color', '--border', '--divider-color');

    // Input placeholder
    $alias('--input-placeholder', '--input_placeholder',
           '--text-secondary', '--text_secondary');

    return $aliases;
}

/**
 * يجمع روابط Google Fonts بدون تكرار
 */
function _header_collect_font_links(array $theme): array
{
    static $systemFonts = [
        'system-ui','ui-sans-serif','ui-serif','ui-monospace',
        'sans-serif','serif','monospace','cursive','fantasy',
        'inherit','initial','unset',
        'arial','verdana','helvetica','helvetica neue','georgia',
        'times','times new roman','courier','courier new',
        'impact','trebuchet ms','comic sans ms','tahoma',
        'lucida','palatino','garamond',
    ];

    $links   = [];
    $loaded  = [];

    foreach ($theme['font_settings'] ?? [] as $f) {
        if (empty($f['font_family'])) {
            continue;
        }

        if (!empty($f['font_url'])) {
            $url = $f['font_url'];
        } else {
            // استخرج الخط الأول فقط من المكدّس
            $primary = trim(explode(',', $f['font_family'])[0], " \"'");
            if ($primary === '' || in_array(strtolower($primary), $systemFonts, true)) {
                continue;
            }
            $url = 'https://fonts.googleapis.com/css2?family='
                 . urlencode(str_replace(' ', '+', $primary))
                 . ':wght@400;500;600;700&display=swap';
        }

        if (!in_array($url, $loaded, true)) {
            $loaded[] = $url;
            $links[]  = $url;
        }
    }

    return $links;
}

function _header_extract_logo(array $theme): string
{
    foreach ($theme['design_settings'] ?? [] as $d) {
        if (($d['setting_key'] ?? '') === 'logo_url' && !empty($d['setting_value'])) {
            return $d['setting_value'];
        }
    }
    return '';
}

function _header_safe_json(array $data): string
{
    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
    );
    return ($json !== false) ? $json : '{}';
}

/**
 * Cache-busting: يُرجع timestamp الملف أو '0' إذا غير موجود
 */
function assetVer(string $path = ''): string
{
    if ($path === '') {
        return (string) time();
    }
    static $cache = [];
    if (!isset($cache[$path])) {
        $full         = $_SERVER['DOCUMENT_ROOT'] . $path;
        $cache[$path] = file_exists($full) ? (string) filemtime($full) : '0';
    }
    return $cache[$path];
}

// ════════════════════════════════════════════════════════════
// بناء كتلة :root CSS — فصل الـ vars الأساسية عن الـ aliases
// ════════════════════════════════════════════════════════════
ob_start();
foreach ($cssVars as $name => $value) {
    echo '    ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
       . ': '   . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
       . ";\n";
}
// Aliases — مُعيَّنة فقط إذا لم تُوجد من DB
foreach ($aliasVars as $name => $value) {
    echo '    ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
       . ': '   . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
       . ";\n";
}
$rootVarsBlock = ob_get_clean();

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"
      dir="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <!-- Security / Meta -->
    <meta name="csrf-token"      content="<?= $csrfToken ?>">
    <meta name="robots"          content="noindex, nofollow">
    <meta name="referrer"        content="strict-origin">

    <!-- i18n -->
    <meta name="i18n-primary-file"
          content="<?= htmlspecialchars($payload['translation_path'] . $lang . '.json', ENT_QUOTES, 'UTF-8') ?>">

    <!-- PWA / Mobile -->
    <meta name="mobile-web-app-capable"               content="yes">
    <meta name="apple-mobile-web-app-capable"         content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title"           data-i18n="brand" content="Admin">
    <meta name="application-name"                     data-i18n="brand" content="Admin">
    <meta name="theme-color"                          content="#1e2533">
    <link rel="manifest" href="/admin/manifest.json">

    <title data-i18n="brand">Admin Panel</title>

    <!-- DNS / Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

    <!-- ════════════════════════════════════════════════════
         CSS VARIABLES  (مصدر الحقيقة الوحيد)
         كل ما يأتي بعده يقرأ من هنا — لا JS ولا ملف آخر
         ════════════════════════════════════════════════════ -->
    <style id="theme-root-vars">
:root {
<?= $rootVarsBlock ?>}

/* ── القواعد الأساسية التي تعتمد فقط على vars ── */
*,
*::before,
*::after {
    box-sizing: border-box;
}

body {
    background:  var(--background-main, var(--background_main, #0a0a0a));
    color:       var(--text-primary, var(--text_primary, #ffffff));
    font-family: var(--body-font-family, var(--body_font-family, system-ui, sans-serif));
    margin: 0;
    padding: 0;
}

.admin-header {
    background:   var(--header-background, var(--header_background,
                      var(--background-secondary, var(--background_secondary, #1e2533))));
    color:        var(--header-text, var(--header_text,
                      var(--text-primary, #ffffff)));
    border-bottom: 1px solid var(--border-color, var(--border_color, #334155));
}

.admin-sidebar {
    background: var(--sidebar-background, var(--sidebar_background, #4B0082));
    color:      var(--sidebar-text,       var(--sidebar_text,       #ffffff));
}
    </style>

    <?php if (!empty($theme['generated_css'])): ?>
    <!-- ── CSS مُولَّد من DB (Button/Card styles) ── -->
    <style id="theme-generated">
<?= $theme['generated_css'] ?>
    </style>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════════════
         GOOGLE FONTS (بعد الـ vars لضمان تطابق الخطوط)
         ════════════════════════════════════════════════════ -->
    <?php foreach ($fontLinks as $fontUrl): ?>
    <link rel="preload"
          href="<?= htmlspecialchars($fontUrl, ENT_QUOTES, 'UTF-8') ?>"
          as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?= htmlspecialchars($fontUrl, ENT_QUOTES, 'UTF-8') ?>">
    </noscript>
    <?php endforeach; ?>

    <!-- ════════════════════════════════════════════════════
         STYLESHEETS
         ════════════════════════════════════════════════════ -->
    <link rel="stylesheet"
          href="/admin/assets/css/admin.css?v=<?= assetVer('/admin/assets/css/admin.css') ?>">
    <link rel="stylesheet"
          href="/admin/assets/css/admin-overrides.css?v=<?= assetVer('/admin/assets/css/admin-overrides.css') ?>">
    <link rel="stylesheet"
          href="/admin/assets/css/modal.css?v=<?= assetVer('/admin/assets/css/modal.css') ?>">
    <link rel="stylesheet"
          href="/admin/assets/css/color-slider.css?v=<?= assetVer('/admin/assets/css/color-slider.css') ?>">
    <link rel="stylesheet"
          href="/admin/assets/css/mobile-responsive.css?v=<?= assetVer('/admin/assets/css/mobile-responsive.css') ?>">
    <link rel="stylesheet"
          href="/admin/assets/css/admin_framework.css?v=<?= assetVer('/admin/assets/css/admin_framework.css') ?>">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
          crossorigin="anonymous"
          referrerpolicy="no-referrer">

    <!-- Admin Framework JS (يجب قبل admin_core.js لأنه يُعرِّف AdminFramework) -->
    <script src="/admin/assets/js/admin_framework.js?v=<?= assetVer('/admin/assets/js/admin_framework.js') ?>"></script>

    <!-- ════════════════════════════════════════════════════
         ADMIN_UI INJECTION
         يُحقن مرة واحدة — JS يقرأ منه ولا يُعدِّل vars الـ CSS
         ════════════════════════════════════════════════════ -->
    <script id="admin-ui-injection">
(function () {
    'use strict';

    /* ── البيانات ── */
    window.ADMIN_UI    = <?= $jsonPayload ?>;
    window.ADMIN_LANG  = <?= json_encode($lang) ?>;
    window.ADMIN_DIR   = <?= json_encode($dir) ?>;
    window.CSRF_TOKEN  = <?= json_encode($csrfToken) ?>;
    window.ADMIN_USER  = window.ADMIN_UI.user || {};

    /* ── HTML attributes ── */
    document.documentElement.lang = window.ADMIN_LANG;
    document.documentElement.dir  = window.ADMIN_DIR;

    /*
     * ⚠ CSS variables مُعيَّنة بالكامل في <style id="theme-root-vars"> أعلاه.
     * admin_core.js لا يحتاج لإعادة تعيينها على :root.
     * generateComponentStyles() تقرأ من window.ADMIN_UI.theme فقط لتوليد
     * قواعد .btn-{slug} و .card-{slug} التي تعتمد على بيانات button_styles/card_styles
     * وليس على CSS variables مباشرة.
     */
    window.__THEME_VARS_INJECTED = true;

<?php if (defined('ADMIN_DEBUG') && ADMIN_DEBUG): ?>
    console.groupCollapsed('%c✓ ADMIN_UI', 'color:#10B981;font-weight:bold');
    console.log('Lang:',        window.ADMIN_LANG);
    console.log('Dir:',         window.ADMIN_DIR);
    console.log('User:',        window.ADMIN_USER.username);
    console.log('Tenant:',      window.ADMIN_UI.tenant_id);
    console.log('Super Admin:', window.ADMIN_UI.is_super_admin);
    console.log('Colors:',      window.ADMIN_UI?.theme?.color_settings?.length  ?? 0);
    console.log('Buttons:',     window.ADMIN_UI?.theme?.button_styles?.length   ?? 0);
    console.log('Trans Path:',  window.ADMIN_UI.translation_path);
    console.groupEnd();
<?php endif; ?>
}());
    </script>

    <!-- ════════════════════════════════════════════════════
         CORE SCRIPTS (defer — لا يُعطّل عرض الصفحة)
         ════════════════════════════════════════════════════ -->
    <script defer
            src="/admin/assets/js/admin_core.js?v=<?= assetVer('/admin/assets/js/admin_core.js') ?>">
    </script>
    <script defer
            src="/admin/assets/js/sidebar-toggle.js?v=<?= assetVer('/admin/assets/js/sidebar-toggle.js') ?>">
    </script>
    <script defer
            src="/admin/assets/js/modal.js?v=<?= assetVer('/admin/assets/js/modal.js') ?>">
    </script>
</head>

<body class="admin<?= ($dir === 'rtl') ? ' rtl' : '' ?>">

<!-- ════════════════════════════════════════════════════════════
     HEADER
     ════════════════════════════════════════════════════════════ -->
<header class="admin-header" role="banner">

    <div class="header-left">
        <!-- زر فتح/إغلاق السايدبار -->
        <button id="sidebarToggle"
                class="icon-btn"
                type="button"
                aria-controls="adminSidebar"
                aria-expanded="false"
                aria-label="Toggle sidebar"
                data-i18n-aria-label="toggle_sidebar">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>

        <!-- الشعار / الاسم -->
        <a class="brand" href="/admin/" aria-label="Admin Home">
            <?php
            $logoValid = $logo !== ''
                && (
                    filter_var($logo, FILTER_VALIDATE_URL)
                    || file_exists($_SERVER['DOCUMENT_ROOT'] . $logo)
                );
            ?>
            <?php if ($logoValid): ?>
                <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>"
                     alt="Logo"
                     class="brand-logo"
                     width="140"
                     height="40"
                     loading="eager"
                     decoding="async">
            <?php else: ?>
                <span class="brand-text" data-i18n="brand">Admin Panel</span>
            <?php endif; ?>
        </a>
    </div>

    <div class="header-right">
        <!-- إشعارات -->
        <button id="notifBtn"
                class="icon-btn"
                type="button"
                aria-label="Notifications"
                data-i18n-aria-label="notifications">
            <i class="fas fa-bell" aria-hidden="true"></i>
            <span id="notifCount"
                  class="badge badge-danger"
                  style="display:none;"
                  aria-live="polite">0</span>
        </button>

        <!-- قائمة المستخدم -->
        <div class="user-menu" role="navigation" aria-label="User menu">
            <a href="/admin/profile.php"
               class="user-link"
               aria-label="View profile">
                <img class="avatar"
                     src="<?= htmlspecialchars($user['avatar'] ?? '/admin/assets/img/default-avatar.png', ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($user['username'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>"
                     width="36"
                     height="36"
                     loading="lazy"
                     decoding="async"
                     onerror="this.src='/admin/assets/img/default-avatar.png'">
            </a>

            <div class="user-info" aria-hidden="true">
                <div class="username">
                    <?= htmlspecialchars($user['username'] ?? 'Guest', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="user-role">
                    <?= htmlspecialchars($user['roles'][0] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <!-- تسجيل الخروج -->
            <form method="POST"
                  action="/admin/logout.php"
                  style="display:inline;"
                  onsubmit="return confirm('Logout?')">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <button type="submit"
                        class="btn-logout icon-btn"
                        aria-label="Logout"
                        data-i18n-aria-label="logout">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>
</header>

<!-- ════════════════════════════════════════════════════════════
     LAYOUT
     ════════════════════════════════════════════════════════════ -->
<div class="admin-layout">

    <!-- السايدبار -->
    <aside id="adminSidebar"
           class="admin-sidebar"
           role="navigation"
           aria-label="Main navigation">
        <?php
        $menuFile = __DIR__ . '/menu.php';
        if (is_readable($menuFile)) {
            include $menuFile;
        } else {
            echo '<p style="padding:1rem;color:var(--sidebar-text,var(--sidebar_text,#fff));"'
               . ' data-i18n="menu_unavailable">Menu not available</p>';
        }
        ?>
    </aside>

    <!-- Backdrop (موبايل فقط — يُخفى بـ CSS على الـ desktop) -->
    <div class="sidebar-backdrop" aria-hidden="true"></div>

    <!-- المحتوى الرئيسي -->
    <main id="adminMainContent"
          class="admin-main"
          role="main">
