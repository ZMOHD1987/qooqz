<?php
/**
 * frontend/includes/public_context.php
 *
 * Public context bootstrap for QOOQZ frontend pages.
 * - Loads theme colors/settings from API
 * - Auto-detects language from HTTP_ACCEPT_LANGUAGE (no visible language button)
 * - Loads translations from frontend/languages/{code}.json
 * - Supports all world languages; RTL auto-detected from translation file
 * - No auth required (guest-friendly)
 */

if (!defined('FRONTEND_PUBLIC_CONTEXT')) {
    define('FRONTEND_PUBLIC_CONTEXT', true);
}

/* -------------------------------------------------------
 * 1. Base path & environment
 * ----------------------------------------------------- */
defined('FRONTEND_BASE') || define('FRONTEND_BASE', dirname(__DIR__));

$envFile = FRONTEND_BASE . '/config/app.php';
$appConfig = is_readable($envFile) ? (require $envFile) : [];

$apiConfigFile = FRONTEND_BASE . '/config/api.php';
$apiConfig = is_readable($apiConfigFile) ? (require $apiConfigFile) : [];

/* -------------------------------------------------------
 * 2. Session — mirrors admin/includes/admin_context.php exactly.
 *    DOCUMENT_ROOT is the primary path (same as admin uses).
 * ----------------------------------------------------- */
// If PHP session.auto_start=1 (common on shared hosting) started a session with the
// wrong name/settings before our code runs, close it so we can restart correctly.
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_ACTIVE && session_name() !== 'APP_SESSID') {
    session_write_close();
}

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // Primary: DOCUMENT_ROOT (same as admin_context.php line 28)
    // Fallback: dirname(FRONTEND_BASE) for CLI / non-standard webroot setups
    $__sharedSession = null;
    foreach ([
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php',
        dirname(FRONTEND_BASE)           . '/api/shared/config/session.php',
    ] as $__c) {
        if ($__c && file_exists($__c)) { $__sharedSession = $__c; break; }
    }
    unset($__c);

    if ($__sharedSession) {
        require_once $__sharedSession;
    } else {
        // Last-resort manual fallback
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        if (!is_dir($__sp)) $__sp = dirname(FRONTEND_BASE) . '/api/storage/sessions';
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        if (session_name() !== 'APP_SESSID') session_name('APP_SESSID');
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
        unset($__sp);
    }
    unset($__sharedSession);
}

/* -------------------------------------------------------
 * 2b. Cache-Control — prevent proxies/CDNs from caching
 *     PHP pages (ensures fresh content after every deploy).
 * ----------------------------------------------------- */
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* -------------------------------------------------------
 * 3. Language resolution (no visible language button)
 *    Priority: URL ?lang=xx → session → app default_lang
 *    Browser Accept-Language is NOT used (platform default takes precedence).
 * ----------------------------------------------------- */
if (!function_exists('pub_detect_lang')) {
    /**
     * Detect best language code from HTTP_ACCEPT_LANGUAGE.
     * Returns the 2-letter language code if a translation file exists.
     * (Kept for potential future use but not called in the main flow.)
     */
    function pub_detect_lang(string $default = 'ar'): string {
        $langDir = FRONTEND_BASE . '/languages';
        $avail   = [];

        // Collect available language codes from frontend/languages/*.json
        foreach (glob($langDir . '/*.json') ?: [] as $f) {
            $code = basename($f, '.json');
            if (preg_match('/^[a-z]{2,5}$/', $code)) {
                $avail[$code] = true;
            }
        }

        if (empty($avail)) {
            return $default;
        }

        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (!$header) {
            return isset($avail[$default]) ? $default : array_key_first($avail);
        }

        // Parse quality-weighted list, e.g. "ar,en-US;q=0.9,en;q=0.8,fr;q=0.7"
        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z\-]+)(?:;q=([0-9.]+))?$/', $part, $m)) {
                $q    = isset($m[2]) ? (float)$m[2] : 1.0;
                $code = strtolower(substr($m[1], 0, 2));
                $candidates[$code] = max($candidates[$code] ?? 0, $q);
            }
        }
        // Sort by quality descending
        arsort($candidates);

        foreach (array_keys($candidates) as $code) {
            if (isset($avail[$code])) {
                return $code;
            }
        }
        return isset($avail[$default]) ? $default : array_key_first($avail);
    }
}

/* -------------------------------------------------------
 * 4. Resolve active language
 * ----------------------------------------------------- */
// Priority: URL ?lang=xx → user preferred_language → session pub_lang → app default_lang
if (isset($_GET['lang']) && preg_match('/^[a-z]{2,5}$/', $_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['pub_lang'] = $lang;   // save explicit user choice
} elseif (!empty($_SESSION['user']['preferred_language'])) {
    // Use the logged-in user's preferred language
    $lang = (string)$_SESSION['user']['preferred_language'];
} elseif (!empty($_SESSION['pub_lang'])) {
    $lang = $_SESSION['pub_lang'];
} else {
    $lang = pub_detect_lang($appConfig['default_lang'] ?? 'en');
}

// Fallback to 'ar' if no translation file exists
$langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
if (!is_readable($langFile)) {
    $lang     = $appConfig['default_lang'] ?? 'ar';
    $langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
}

/* -------------------------------------------------------
 * 5. Load translations
 * ----------------------------------------------------- */
if (!function_exists('pub_load_translations')) {
    /**
     * Load translation file and return array.
     * Falls back to English then empty array.
     */
    function pub_load_translations(string $langCode): array {
        $base = FRONTEND_BASE . '/languages/';
        $f    = $base . $langCode . '.json';
        if (!is_readable($f)) {
            $f = $base . 'en.json';
        }
        if (!is_readable($f)) {
            return [];
        }
        $data = json_decode(file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('t')) {
    /**
     * Translate a dot-separated key, e.g. t('nav.home') or t('hero.title').
     * Falls back to the key itself.
     */
    function t(string $key, array $replace = []): string {
        $strings = $GLOBALS['PUB_STRINGS'] ?? [];
        $parts   = explode('.', $key, 2);
        $group   = $parts[0] ?? '';
        $sub     = $parts[1] ?? '';

        $val = $sub !== ''
            ? ($strings[$group][$sub] ?? $key)
            : ($strings[$group] ?? $key);

        if (!is_string($val)) {
            $val = $key;
        }

        // Simple placeholder replacement {key} => value
        foreach ($replace as $k => $v) {
            $val = str_replace('{' . $k . '}', (string)$v, $val);
        }
        return $val;
    }
}

$_translations = pub_load_translations($lang);
$dir = $_translations['dir'] ?? (in_array($lang, ['ar','fa','ur','he']) ? 'rtl' : 'ltr');
$GLOBALS['PUB_STRINGS'] = $_translations;

/* -------------------------------------------------------
 * 4. API base URL (used for server-side fetch)
 * ----------------------------------------------------- */
if (!function_exists('pub_api_url')) {
    function pub_api_url(string $path = ''): string {
        // Detect scheme + host for self-referencing API calls
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim($scheme . '://' . $host . '/api', '/');
        return $base . '/' . ltrim($path, '/');
    }
}

/* -------------------------------------------------------
 * 5. Lightweight HTTP fetch (curl/file_get_contents)
 * ----------------------------------------------------- */
if (!function_exists('pub_fetch')) {
    /**
     * Fetch JSON from the internal API.
     * Returns decoded array or [] on failure.
     */
    function pub_fetch(string $url, int $timeout = 4): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx  = stream_context_create(['http' => ['timeout' => $timeout]]);
            $body = @file_get_contents($url, false, $ctx);
        }
        if (!$body) return [];
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}

/* -------------------------------------------------------
 * 6. Theme / color settings — loaded directly from DB via PDO
 *    Also loads: design_settings, font_settings, button_styles, card_styles
 *    Generates complete CSS string stored in $theme['generated_css']
 * ----------------------------------------------------- */
if (!function_exists('pub_load_theme')) {
    function pub_load_theme(int $tenantId = 1): array {
        // Defaults (fallback when DB is unreachable)
        $defaults = [
            'primary'    => '#03874e',
            'secondary'  => '#10B981',
            'accent'     => '#F59E0B',
            'background' => '#0d0d0d',
            'surface'    => '#4f4f4f',
            'text'       => '#FFFFFF',
            'text_muted' => '#B0B0B0',
            'border'     => '#333333',
            'header_bg'        => '#1e2533',
            'header_text_color'=> '#FFFFFF',
            'footer_bg'        => '#1e2a38',
            'footer_text_color'=> '#B0B0B0',
            'logo_url'   => '',          // set from design_settings WHERE setting_key='logo_url'
            'generated_css' => '',
            'fonts'      => [],
            'design'     => [],
            'buttons'    => [],
            'cards'      => [],
        ];

        // Session cache is checked after theme_id lookup (inside PDO block below)
        // to ensure cache key includes theme_id and stale entries are not returned.

        // Reuse the shared PDO connection (avoids opening a second connection per request).
        // pub_get_pdo() is defined later in this file (line ~538) but will already be
        // registered by the time pub_load_theme() is *called* at line ~695.
        $pdo = pub_get_pdo();

        if ($pdo instanceof PDO) {
            try {

                $colors  = [];
                $fonts   = [];
                $designs = [];
                $buttons = [];
                $cards   = [];

                // Look up active theme_id (mirrors AdminUiThemeLoader::getActiveThemeId)
                $thSt = $pdo->prepare('SELECT id FROM themes WHERE tenant_id = ? AND is_active = 1 LIMIT 1');
                $thSt->execute([$tenantId]);
                $thRow = $thSt->fetch(PDO::FETCH_ASSOC);
                if (!$thRow) {
                    $thSt = $pdo->prepare('SELECT id FROM themes WHERE tenant_id = ? AND is_default = 1 LIMIT 1');
                    $thSt->execute([$tenantId]);
                    $thRow = $thSt->fetch(PDO::FETCH_ASSOC);
                }
                $themeDbId  = $thRow ? (int)$thRow['id'] : null;
                $thIdCond   = $themeDbId ? ' AND (theme_id = ? OR theme_id IS NULL)' : '';
                $thP = static function(array $base) use ($themeDbId): array {
                    return $themeDbId ? array_merge($base, [$themeDbId]) : $base;
                };

                // color_settings: setting_key, color_value
                $st = $pdo->prepare('SELECT setting_key, color_value FROM color_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order, id');
                $st->execute($thP([$tenantId]));
                $colorRows = $st->fetchAll(PDO::FETCH_ASSOC);

                // font_settings: setting_key, font_family, font_size, font_weight, line_height
                $st = $pdo->prepare('SELECT setting_key, font_family, font_size, font_weight, line_height FROM font_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order');
                $st->execute($thP([$tenantId]));
                $fonts = $st->fetchAll(PDO::FETCH_ASSOC);

                // design_settings: setting_key, setting_value
                $st = $pdo->prepare('SELECT setting_key, setting_value, setting_type FROM design_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order');
                $st->execute($thP([$tenantId]));
                $designs = $st->fetchAll(PDO::FETCH_ASSOC);

                // button_styles
                $st = $pdo->prepare('SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color FROM button_styles WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY button_type');
                $st->execute($thP([$tenantId]));
                $buttons = $st->fetchAll(PDO::FETCH_ASSOC);

                // card_styles
                $st = $pdo->prepare('SELECT slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio FROM card_styles WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY card_type');
                $st->execute($thP([$tenantId]));
                $cards = $st->fetchAll(PDO::FETCH_ASSOC);

                if ($colorRows || $fonts || $designs || $buttons || $cards) {
                    $theme = $defaults;

                    // Map color_settings keys to theme keys
                    // Covers both possible naming conventions (noun_adjective vs adjective_noun)
                    $colorMap = [
                        'primary_color'        => 'primary',
                        'secondary_color'      => 'secondary',
                        'accent_color'         => 'accent',
                        // "Main Background" — try both naming variants
                        'background_main'      => 'background',
                        'main_background'      => 'background',
                        'background_color_main'=> 'background',
                        // "Secondary Background"
                        'background_secondary' => 'surface',
                        'secondary_background' => 'surface',
                        // "Primary Text"
                        'text_primary'         => 'text',
                        'primary_text'         => 'text',
                        'text_color_primary'   => 'text',
                        // "Secondary Text"
                        'text_secondary'       => 'text_muted',
                        'secondary_text'       => 'text_muted',
                        // Border
                        'border_color'         => 'border',
                        // Header/Footer background and text — all naming variants
                        'header_bg'            => 'header_bg',
                        'header_bg_color'      => 'header_bg',
                        'header_background'    => 'header_bg',
                        'header_text'          => 'header_text_color',
                        'header_text_color'    => 'header_text_color',
                        'footer_bg'            => 'footer_bg',
                        'footer_bg_color'      => 'footer_bg',
                        'footer_background'    => 'footer_bg',
                        'footer_text'          => 'footer_text_color',
                        'footer_text_color'    => 'footer_text_color',
                    ];
                    foreach ($colorRows as $row) {
                        $k = $row['setting_key'] ?? '';
                        $v = $row['color_value'] ?? '';
                        if (!$v) continue;
                        $mapped = $colorMap[$k] ?? null;
                        if ($mapped) $theme[$mapped] = $v;
                        $colors[$k] = $v;
                    }
                    // Track whether any color_settings row explicitly configured header_bg
                    $_headerBgSet = !empty($colors['header_bg']) || !empty($colors['header_bg_color']) || !empty($colors['header_background']);
                    // Also fill theme keys from color-type design_settings when color_settings is absent.
                    // Covers all naming variants; only overwrites if color_settings didn't already set it.
                    $dColorThemeMap = [
                        'header_bg'          => 'header_bg',
                        'header_bg_color'    => 'header_bg',
                        'header_background'  => 'header_bg',
                        'footer_bg'          => 'footer_bg',
                        'footer_bg_color'    => 'footer_bg',
                        'footer_background'  => 'footer_bg',
                        'header_text'        => 'header_text_color',
                        'header_text_color'  => 'header_text_color',
                        'footer_text'        => 'footer_text_color',
                        'footer_text_color'  => 'footer_text_color',
                    ];
                    foreach ($designs as $_d) {
                        if (($_d['setting_type'] ?? '') !== 'color' || empty($_d['setting_value'])) continue;
                        $_dk = $_d['setting_key'] ?? '';
                        if (!isset($dColorThemeMap[$_dk])) continue;
                        $_thKey = $dColorThemeMap[$_dk];
                        // Only overwrite from design_settings if color_settings didn't already set it
                        if (isset($theme[$_thKey], $defaults[$_thKey]) && $theme[$_thKey] === $defaults[$_thKey]) {
                            $theme[$_thKey] = $_d['setting_value'];
                        }
                        // Track that header_bg was explicitly configured (even via design_settings)
                        if ($_thKey === 'header_bg') $_headerBgSet = true;
                    }
                    unset($dColorThemeMap, $_d, $_dk, $_thKey);

                    // header_bg defaults to primary only when NO source explicitly configured it
                    if (!$_headerBgSet) {
                        $theme['header_bg'] = $theme['primary'];
                    }
                    unset($_headerBgSet);

                    $theme['fonts']   = $fonts;
                    $theme['design']  = $designs;
                    $theme['buttons'] = $buttons;
                    $theme['cards']   = $cards;

                    // Extract logo_url from design_settings (setting_key = 'logo_url')
                    foreach ($designs as $d) {
                        if (($d['setting_key'] ?? '') === 'logo_url' && !empty($d['setting_value'])) {
                            $theme['logo_url'] = (string)$d['setting_value'];
                            break;
                        }
                    }
                    // Fallback: check images table for a tenant logo (image_type code='logo' or 'entity_logo')
                    if (empty($theme['logo_url'])) {
                        try {
                            $logoSt = $pdo->prepare(
                                "SELECT i.url FROM images i
                                 LEFT JOIN image_types it ON it.id = i.image_type_id
                                 WHERE i.owner_type = 'tenant' AND i.owner_id = ?
                                   AND (it.code = 'logo' OR it.code = 'entity_logo')
                                 ORDER BY i.id ASC LIMIT 1"
                            );
                            $logoSt->execute([$tenantId]);
                            $logoRow = $logoSt->fetch(PDO::FETCH_ASSOC);
                            if ($logoRow && !empty($logoRow['url'])) {
                                $theme['logo_url'] = (string)$logoRow['url'];
                            }
                        } catch (Throwable $_) {}
                    }

                    // Generate complete CSS string (mirrors AdminUiThemeLoader::generateCss)
                    // Escape values to prevent CSS/HTML injection (</style> breakout)
                    $cssEsc = function(string $v): string { return str_replace('</style', '<\\/style', htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); };
                    $css = ":root {\n";
                    foreach ($colors as $k => $v) {
                        $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower($k)) . ': ' . $cssEsc($v) . ";\n";
                    }
                    // CSS variable aliases: bridge DB setting_key names (underscore) to
                    // --pub-* and --color-* names used in public.css / variables.css so
                    // DB colours render correctly without relying solely on the PHP bridge.
                    $pubAliases = [
                        'primary_color'        => ['color-primary',  'pub-primary'],
                        'secondary_color'      => ['color-secondary', 'pub-secondary'],
                        'accent_color'         => ['color-accent',    'pub-accent'],
                        'background_main'      => ['pub-bg'],
                        'background_secondary' => ['pub-surface'],
                        'text_primary'         => ['pub-text'],
                        'text_secondary'       => ['pub-muted'],
                        'border_color'         => ['pub-border'],
                        // Header background: all naming conventions → --pub-header-bg
                        'header_bg'            => ['pub-header-bg'],
                        'header_bg_color'      => ['pub-header-bg'],
                        'header_background'    => ['pub-header-bg'],
                        // Footer background: all naming conventions → --pub-footer-bg
                        'footer_bg'            => ['pub-footer-bg'],
                        'footer_bg_color'      => ['pub-footer-bg'],
                        'footer_background'    => ['pub-footer-bg'],
                        // Header / footer text
                        'header_text'          => ['pub-header-text'],
                        'header_text_color'    => ['pub-header-text'],
                        'footer_text'          => ['pub-footer-text'],
                        'footer_text_color'    => ['pub-footer-text'],
                    ];
                    foreach ($pubAliases as $srcKey => $aliases) {
                        if (empty($colors[$srcKey])) continue;
                        $val = $cssEsc($colors[$srcKey]);
                        foreach ($aliases as $alias) {
                            $css .= '  --' . $alias . ': ' . $val . ";\n";
                        }
                    }
                    foreach ($fonts as $f) {
                        if (empty($f['setting_key'])) continue;
                        $sk = preg_replace('/[^a-z0-9_\-]/', '-', strtolower($f['setting_key']));
                        if (!empty($f['font_family'])) $css .= '  --' . $sk . '-family: ' . $cssEsc((string)$f['font_family']) . ";\n";
                        if (!empty($f['font_size']))   $css .= '  --' . $sk . '-size: '   . $cssEsc((string)$f['font_size'])   . ";\n";
                        if (!empty($f['font_weight'])) $css .= '  --' . $sk . '-weight: ' . $cssEsc((string)$f['font_weight']) . ";\n";
                    }
                    // design_settings → raw CSS vars + --pub-* aliases for color and layout keys
                    $dColorToCssVar = [
                        'header_bg'          => 'pub-header-bg',
                        'header_bg_color'    => 'pub-header-bg',
                        'header_background'  => 'pub-header-bg',
                        'footer_bg'          => 'pub-footer-bg',
                        'footer_bg_color'    => 'pub-footer-bg',
                        'footer_background'  => 'pub-footer-bg',
                        'header_text'        => 'pub-header-text',
                        'header_text_color'  => 'pub-header-text',
                        'footer_text'        => 'pub-footer-text',
                        'footer_text_color'  => 'pub-footer-text',
                        'sidebar_bg_color'   => 'pub-sidebar-bg',
                        'sidebar_text_color' => 'pub-sidebar-text',
                    ];
                    $dLayoutToCssVar = [
                        'container_max_width' => 'pub-max-width',
                        'header_height'       => 'pub-header-height',
                        'sidebar_width'       => 'pub-sidebar-width',
                        'default_padding'     => 'pub-padding',
                        'logo_height'         => 'pub-logo-height',
                    ];
                    foreach ($designs as $d) {
                        if (empty($d['setting_key']) || empty($d['setting_value'])) continue;
                        $dk = $d['setting_key'];
                        $dv = (string)$d['setting_value'];
                        $dt = $d['setting_type'] ?? '';
                        $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower($dk)) . ': ' . $cssEsc($dv) . ";\n";
                        // Generate --pub-* alias for color-type entries
                        if ($dt === 'color' && isset($dColorToCssVar[$dk])) {
                            $css .= '  --' . $dColorToCssVar[$dk] . ': ' . $cssEsc($dv) . ";\n";
                        }
                        // Generate --pub-* alias for layout/size entries
                        if (in_array($dt, ['number', 'text'], true) && isset($dLayoutToCssVar[$dk])) {
                            $cssVal = $dv;
                            if ($dt === 'number' && !preg_match('/[a-z%]$/i', $cssVal)) {
                                $cssVal .= 'px';
                            }
                            $css .= '  --' . $dLayoutToCssVar[$dk] . ': ' . $cssEsc($cssVal) . ";\n";
                        }
                    }
                    // Always emit the resolved --pub-header/footer vars last in :root {}
                    // so they reflect the correct DB-sourced $theme values regardless of
                    // which setting key / table was used — prevents any earlier rule from
                    // overriding them with a stale or conflicting intermediate value.
                    $css .= '  --pub-header-bg: '   . $cssEsc($theme['header_bg'])         . ";\n";
                    $css .= '  --pub-header-text: ' . $cssEsc($theme['header_text_color'])  . ";\n";
                    $css .= '  --pub-footer-bg: '   . $cssEsc($theme['footer_bg'])          . ";\n";
                    $css .= '  --pub-footer-text: ' . $cssEsc($theme['footer_text_color'])  . ";\n";
                    // --pub-card-bg: generic fallback used by CSS files for cards that have no
                    // card_styles DB entry. pub_card_inline_style() uses the DB background_color
                    // directly so per-card colours from the database are applied correctly.
                    $css .= "  --pub-card-bg: var(--pub-surface);\n";
                    $css .= "}\n";
                    // Apply font_settings variables to relevant UI elements
                    $fontSelMap = [
                        'card_font'       => '.pub-card, .pub-entity-card, .pub-job-card, .pub-deal-card, .pub-cat-card',
                        'footer_font'     => '.pub-footer',
                        'form_font'       => '.pub-form input, .pub-form select, .pub-form textarea, .pub-search-input',
                        'promo_font'      => '.pub-deal-card, .pub-promo-card',
                        'small_text_font' => '.pub-muted, .pub-tag, small',
                        'code_font'       => 'code, pre',
                        'alert_font'      => '.pub-toast, .pub-notice, .pub-alert',
                    ];
                    foreach ($fonts as $f) {
                        if (empty($f['setting_key'])) continue;
                        $sk  = preg_replace('/[^a-z0-9_\-]/', '-', strtolower($f['setting_key']));
                        $sel = $fontSelMap[$f['setting_key']] ?? null;
                        if (!$sel) continue;
                        $props = [];
                        if (!empty($f['font_family'])) $props[] = '  font-family: var(--' . $sk . '-family)';
                        if (!empty($f['font_size']))   $props[] = '  font-size: var(--'   . $sk . '-size)';
                        if (!empty($f['font_weight'])) $props[] = '  font-weight: var(--' . $sk . '-weight)';
                        if ($props) $css .= $sel . " {\n" . implode(";\n", $props) . ";\n}\n";
                    }
                    // Map button_type to .pub-btn-- class names used in HTML
                    $pubBtnTypeMap = ['transpa' => 'ghost', 'transparent' => 'ghost'];
                    foreach ($buttons as $b) {
                        if (empty($b['slug'])) continue;
                        $slugB    = preg_replace('/[^a-z0-9_\-]/', '-', (string)$b['slug']);
                        $btnType  = strtolower(trim((string)($b['button_type'] ?? '')));
                        $pubClass = $pubBtnTypeMap[$btnType] ?? $btnType;
                        $isDisabled = strpos($slugB, '-disabled') !== false;
                        // Combine .btn-{slug} with .pub-btn--{type} for non-disabled buttons
                        $sel = ".btn-{$slugB}";
                        if (!$isDisabled && $pubClass && preg_match('/^[a-z][a-z0-9\-]*$/', $pubClass)) {
                            $sel .= ", .pub-btn--{$pubClass}";
                        }
                        $css .= "{$sel} {\n";
                        if (!empty($b['background_color'])) $css .= '  background-color: ' . $cssEsc((string)$b['background_color']) . ";\n";
                        if (!empty($b['text_color']))       $css .= '  color: '            . $cssEsc((string)$b['text_color'])       . ";\n";
                        if (!empty($b['border_color']))     $css .= '  border: '           . (int)$b['border_width'] . 'px solid ' . $cssEsc((string)$b['border_color']) . ";\n";
                        if (isset($b['border_radius']))     $css .= '  border-radius: '    . (int)$b['border_radius'] . "px;\n";
                        if (!empty($b['padding']))          $css .= '  padding: '          . $cssEsc((string)$b['padding'])          . ";\n";
                        if (!empty($b['font_size']))        $css .= '  font-size: '        . $cssEsc((string)$b['font_size'])        . ";\n";
                        if (!empty($b['font_weight']))      $css .= '  font-weight: '      . $cssEsc((string)$b['font_weight'])      . ";\n";
                        $css .= "}\n";
                        if (!empty($b['hover_background_color'])) {
                            $hoverSel = ".btn-{$slugB}:hover";
                            if (!$isDisabled && $pubClass && preg_match('/^[a-z][a-z0-9\-]*$/', $pubClass)) {
                                $hoverSel .= ", .pub-btn--{$pubClass}:hover";
                            }
                            $css .= "{$hoverSel} {\n  background-color: " . $cssEsc((string)$b['hover_background_color']) . ";\n";
                            if (!empty($b['hover_text_color'])) $css .= '  color: ' . $cssEsc((string)$b['hover_text_color']) . ";\n";
                            $css .= "}\n";
                        }
                    }
                    foreach ($cards as $c) {
                        if (empty($c['slug'])) continue;
                        $slugC = preg_replace('/[^a-z0-9_\-]/', '-', (string)$c['slug']);
                        $hoverEffect = strtolower(trim((string)($c['hover_effect'] ?? '')));
                        $css .= ".card-{$slugC} {\n";
                        if (!empty($c['background_color'])) $css .= '  background-color: ' . $cssEsc((string)$c['background_color']) . ";\n";
                        if (!empty($c['border_color']))     $css .= '  border: '           . (int)$c['border_width'] . 'px solid ' . $cssEsc((string)$c['border_color']) . ";\n";
                        if (isset($c['border_radius']))     $css .= '  border-radius: '    . (int)$c['border_radius'] . "px;\n";
                        if (!empty($c['shadow_style']))     $css .= '  box-shadow: '       . $cssEsc((string)$c['shadow_style'])     . ";\n";
                        if (!empty($c['padding']))          $css .= '  padding: '          . $cssEsc((string)$c['padding'])          . ";\n";
                        if (!empty($c['text_align']))       $css .= '  text-align: '       . $cssEsc((string)$c['text_align'])       . ";\n";
                        // Only add transition when a hover effect is configured
                        if ($hoverEffect && $hoverEffect !== 'none') {
                            $css .= "  transition: transform .2s ease, box-shadow .2s ease;\n";
                        }
                        $css .= "}\n";
                        // Image wrapper aspect ratio (e.g. "1:1" stored in DB → CSS "1/1")
                        if (!empty($c['image_aspect_ratio'])) {
                            $ratio = preg_replace('/[^0-9:]/', '', (string)$c['image_aspect_ratio']);
                            $ratio = str_replace(':', '/', $ratio);
                            if ($ratio) $css .= ".card-{$slugC} .pub-cat-img-wrap { aspect-ratio: {$ratio}; }\n";
                        }
                        // Hover effect — translate/scale/shadow values match standard UX conventions
                        if ($hoverEffect && $hoverEffect !== 'none') {
                            $css .= ".card-{$slugC}:hover {\n";
                            if ($hoverEffect === 'lift')   $css .= "  transform: translateY(-4px);\n  box-shadow: 0 8px 24px rgba(0,0,0,0.18);\n";
                            if ($hoverEffect === 'zoom')   $css .= "  transform: scale(1.03);\n";
                            if ($hoverEffect === 'shadow') $css .= "  box-shadow: 0 8px 28px rgba(0,0,0,0.22);\n";
                            if ($hoverEffect === 'border' && !empty($c['border_color'])) {
                                // Increase border by 1px on hover to create a highlight effect
                                $css .= "  border-width: " . (max(1, (int)$c['border_width']) + 1) . "px;\n";
                            }
                            $css .= "}\n";
                        }
                    }
                    $theme['generated_css'] = $css;

                    return $theme;
                }
            } catch (Throwable $_) {
                // Silently fall through to HTTP fallback
            }
        }

        // Fallback: try HTTP call to /api/public/ui
        $url  = pub_api_url('public/ui') . '?tenant_id=' . $tenantId;
        $resp = pub_fetch($url, 3);
        if (!empty($resp['data']['generated_css'])) {
            $theme = $defaults;
            $theme['generated_css'] = $resp['data']['generated_css'];
            // Also apply color map from response
            $httpColorMap = [
                'primary_color'        => 'primary',
                'secondary_color'      => 'secondary',
                'accent_color'         => 'accent',
                'background_main'      => 'background',
                'background_secondary' => 'surface',
                'text_primary'         => 'text',
                'text_secondary'       => 'text_muted',
                'border_color'         => 'border',
                'header_bg_color'      => 'header_bg',
                'header_background'    => 'header_bg',
                'header_text'          => 'header_text_color',
                'footer_bg_color'      => 'footer_bg',
                'footer_background'    => 'footer_bg',
                'footer_text'          => 'footer_text_color',
            ];
            foreach ($resp['data']['colors'] ?? [] as $item) {
                $k = $item['key'] ?? '';
                $v = $item['value'] ?? '';
                if ($k && $v && isset($httpColorMap[$k])) $theme[$httpColorMap[$k]] = $v;
            }
            if (empty($theme['header_bg']) || $theme['header_bg'] === $defaults['header_bg']) {
                $theme['header_bg'] = $theme['primary'];
            }
            return $theme;
        }

        return $defaults;
    }
}

/* -------------------------------------------------------
 * 7a. SEO meta helper — loads seo_meta + seo_meta_translations for any entity.
 * Returns array with SEO fields or [] if no row / DB error.
 * ----------------------------------------------------- */
if (!function_exists('pub_get_seo_meta')) {
    function pub_get_seo_meta(string $entityType, int $entityId, string $lang = 'en'): array {
        if (!$entityId || !$entityType) return [];
        $pdo = pub_get_pdo();
        if (!$pdo) return [];
        try {
            $st = $pdo->prepare(
                "SELECT sm.canonical_url, sm.robots, sm.schema_markup,
                        smt.meta_title, smt.meta_description, smt.meta_keywords,
                        smt.og_title, smt.og_description, smt.og_image
                   FROM seo_meta sm
              LEFT JOIN seo_meta_translations smt
                     ON smt.seo_meta_id = sm.id AND smt.language_code = ?
                  WHERE sm.entity_type = ? AND sm.entity_id = ?
                  LIMIT 1"
            );
            $st->execute([$lang, $entityType, $entityId]);
            $row = $st->fetch();
            if (!$row) return [];
            return [
                'title'          => $row['meta_title']        ?? '',
                'description'    => $row['meta_description']  ?? '',
                'keywords'       => $row['meta_keywords']     ?? '',
                'canonical_url'  => $row['canonical_url']     ?? '',
                'robots'         => $row['robots']            ?? '',
                'og_title'       => $row['og_title']          ?? '',
                'og_description' => $row['og_description']    ?? '',
                'og_image'       => $row['og_image']          ?? '',
                'schema_markup'  => $row['schema_markup']     ?? '',
            ];
        } catch (Throwable $_) { return []; }
    }
}

/* -------------------------------------------------------
 * 7b. Direct PDO helper — reuse the same DB connection as the API
 *    Returns PDO instance or null on failure.
 *    Used by product.php and other pages to avoid HTTP loopback
 *    self-referencing requests that may fail on shared hosting.
 *    Uses DOCUMENT_ROOT as primary path (same as admin_context.php).
 * ----------------------------------------------------- */
if (!function_exists('pub_get_pdo')) {
    function pub_get_pdo(): ?PDO {
        // Cache per request — avoid opening multiple connections (one from pub_load_theme + one from here)
        static $__pdo = false;
        if ($__pdo !== false) return $__pdo;

        // DOCUMENT_ROOT first (same as admin_context.php line 45), then relative path as fallback
        $candidates = [
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/db.php',
            FRONTEND_BASE . '/../api/shared/config/db.php',
            realpath(FRONTEND_BASE . '/../api/shared/config/db.php') ?: '',
        ];
        $dbConf = null;
        foreach ($candidates as $f) {
            if ($f && is_readable($f)) {
                $dbConf = require $f;
                if (is_array($dbConf)) break;
                $dbConf = null;
            }
        }
        // Fallback: use already-defined DB_HOST constants (set by API bootstrap or db.php
        // loaded in the same request). This covers shared hosting where path resolution
        // fails but the constants are already in scope from admin/session bootstrap.
        if (!$dbConf && defined('DB_HOST') && defined('DB_NAME')) {
            $dbConf = [
                'host'    => DB_HOST,
                'user'    => defined('DB_USER')    ? DB_USER    : '',
                'pass'    => defined('DB_PASS')    ? DB_PASS    : '',
                'name'    => DB_NAME,
                'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
                'port'    => defined('DB_PORT')    ? (int)DB_PORT : 3306,
            ];
        }
        if (!$dbConf) { $__pdo = null; return null; }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConf['host'] ?? 'localhost',
                (int)($dbConf['port'] ?? 3306),
                $dbConf['name'],
                $dbConf['charset'] ?? 'utf8mb4'
            );
            $__pdo = new PDO($dsn, $dbConf['user'], $dbConf['pass'], [
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,  // ensures LIMIT/OFFSET bound params work on MySQL 5.x
            ]);
            return $__pdo;
        } catch (Throwable $_) {
            $__pdo = null;
            return null;
        }
    }
}

/* -------------------------------------------------------
 * 8. XSS escape helper
 * ----------------------------------------------------- */
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* -------------------------------------------------------
 * 9. Pagination helper
 * ----------------------------------------------------- */
if (!function_exists('pub_paginate')) {
    /**
     * Returns pagination info array.
     */
    function pub_paginate(int $total, int $page, int $perPage): array {
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        return [
            'total'       => $total,
            'page'        => max(1, $page),
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $totalPages,
        ];
    }
}

/* -------------------------------------------------------
 * 9. Image URL helper
 *    Resolves uploaded image URLs for products, categories,
 *    entities, brands, etc.
 *    image_types reference:
 *      category / product / product_thumb / entity_logo /
 *      entity_cover / banner / gallery / brand / avatar …
 * ----------------------------------------------------- */
if (!function_exists('pub_img')) {
    /**
     * Build an absolute-path image URL for items stored in /uploads.
     *
     * Handles all path formats from the DB:
     *   /admin/uploads/images/general/2026/02/02/img_xxx.webp  → as-is (absolute)
     *   /uploads/images/img_xxx.webp                           → as-is
     *   uploads/images/img_xxx.webp                            → /uploads/images/img_xxx.webp
     *   img_xxx.webp                                           → /uploads/images/img_xxx.webp
     *   https://cdn.example.com/x.jpg                          → passthrough
     *
     * @param string|null $path   Raw path from DB
     * @param string      $type   image_types.code  (category / product / entity_logo …)
     * @param string      $fallback Returned when no image available
     */
    function pub_img(?string $path, string $type = 'product', string $fallback = ''): string {
        if (empty($path)) {
            return $fallback;
        }

        // Already a full URL → return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        // Absolute path starting with / → return as-is (covers /admin/uploads/... and /uploads/...)
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // Relative path already starting with uploads/
        $clean = ltrim($path, '/');
        if (str_starts_with($clean, 'uploads/')) {
            return '/' . $clean;
        }
        if (str_starts_with($clean, 'admin/uploads/')) {
            return '/' . $clean;
        }

        // Bare filename — place under /uploads/images/
        return '/uploads/images/' . $clean;
    }
}

/* -------------------------------------------------------
 * 10. Image HTML tag helper
 * ----------------------------------------------------- */
if (!function_exists('pub_img_tag')) {
    /**
     * Render an <img> tag or a placeholder div.
     *
     * @param string|null $path
     * @param string      $alt
     * @param string      $type  image_types.code
     * @param string      $cssClass
     * @param string      $placeholderIcon  Emoji used as placeholder
     */
    function pub_img_tag(?string $path, string $alt = '', string $type = 'product',
                          string $cssClass = '', string $placeholderIcon = '🖼️'): string {
        $url = empty($path) ? '' : pub_img($path, $type);
        if ($url) {
            return '<img data-src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
                 . ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"'
                 . ' loading="lazy"'
                 . ($cssClass ? ' class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '"' : '')
                 . ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">'
                 . '<span class="pub-img-placeholder" style="display:none;" aria-hidden="true">' . $placeholderIcon . '</span>';
        }
        return '<span class="pub-img-placeholder" aria-hidden="true">' . $placeholderIcon . '</span>';
    }
}

/* -------------------------------------------------------
 * 11. Card helpers — DB-driven card_styles
 * ----------------------------------------------------- */

/** @internal Shared lookup for card_styles row by card_type or slug */
function _pub_card_row(string $cardType): ?array {
    $cards = $GLOBALS['PUB_CONTEXT']['theme']['cards'] ?? [];
    foreach ($cards as $c) {
        if (($c['card_type'] ?? '') === $cardType || ($c['slug'] ?? '') === $cardType) {
            return $c;
        }
    }
    // Fallback 1: match by the base of the slug (e.g. 'entities-default' base 'entities' matches 'entities').
    // Works for ALL cards regardless of card_type value (covers mis-typed DB rows).
    foreach ($cards as $c) {
        $slug     = $c['slug'] ?? '';
        $dashPos  = strpos($slug, '-');
        $slugBase = $dashPos !== false ? substr($slug, 0, $dashPos) : $slug;
        if ($slugBase === $cardType) {
            return $c;
        }
    }
    // Fallback 2: singular/plural alias using an explicit map of known card types.
    // Covers 'entities'↔'entity', 'tenants'↔'tenant', 'products'↔'product', etc.
    static $aliasMap = [
        'entities'  => 'entity',   'entity'    => 'entities',
        'tenants'   => 'tenant',   'tenant'    => 'tenants',
        'products'  => 'product',  'product'   => 'products',
        'categories'=> 'category', 'category'  => 'categories',
        'auctions'  => 'auction',  'auction'   => 'auctions',
        'jobs'      => 'job',      'job'       => 'jobs',
        'blogs'     => 'blog',     'blog'      => 'blogs',
        'discounts' => 'discount', 'discount'  => 'discounts',
        'features'  => 'feature',  'feature'   => 'features',
        'banners'   => 'banner',   'banner'    => 'banners',
    ];
    if (isset($aliasMap[$cardType])) {
        $alias = $aliasMap[$cardType];
        foreach ($cards as $c) {
            if (($c['card_type'] ?? '') === $alias || ($c['slug'] ?? '') === $alias) {
                return $c;
            }
            $slug     = $c['slug'] ?? '';
            $dashPos  = strpos($slug, '-');
            $slugBase = $dashPos !== false ? substr($slug, 0, $dashPos) : $slug;
            if ($slugBase === $alias) {
                return $c;
            }
        }
    }
    return null;
}

if (!function_exists('pub_card_inline_style')) {
    /**
     * Return an inline CSS style string for a card element, sourced from the
     * DB card_styles table (already loaded into $GLOBALS['PUB_CONTEXT']['theme']['cards']).
     *
     * Matches by card_type first, then by slug. Returns '' when no matching row exists.
     *
     * @param string $cardType  e.g. 'entity', 'tenant', 'product'
     */
    function pub_card_inline_style(string $cardType): string {
        $row = _pub_card_row($cardType);
        if (!$row) return '';

        // Escape a CSS value: HTML-encode and also strip characters that could
        // break out of a style="..." attribute or inject extra CSS properties.
        $esc = function(string $v): string {
            $v = str_replace(['"', "'", ';', '{', '}', '\\'], '', $v);
            return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $parts = [];
        // Use the DB background_color directly so per-card colours stored in card_styles
        // are applied correctly. --pub-card-bg (aliased to --pub-surface) remains available
        // as a generic CSS fallback for cards that have no DB card_styles entry.
        if (!empty($row['background_color'])) $parts[] = 'background-color:' . $esc($row['background_color']);
        if (!empty($row['border_color'])) {
            $bw = max(0, (int)($row['border_width'] ?? 1));
            $parts[] = 'border:' . $bw . 'px solid ' . $esc($row['border_color']);
        }
        if (isset($row['border_radius']) && $row['border_radius'] !== '') $parts[] = 'border-radius:' . (int)$row['border_radius'] . 'px';
        if (!empty($row['shadow_style']))  $parts[] = 'box-shadow:' . $esc($row['shadow_style']);
        if (!empty($row['padding']))       $parts[] = 'padding:' . $esc($row['padding']);
        if (!empty($row['text_align']))    $parts[] = 'text-align:' . $esc($row['text_align']);
        return implode(';', $parts);
    }
}

if (!function_exists('pub_card_css_class')) {
    /**
     * Return the generated CSS class name for a card type, e.g. "card-product-default".
     * This class is emitted by pub_load_theme() as .card-{slug} in generated_css,
     * and includes hover effects. Returns '' when no matching row exists.
     */
    function pub_card_css_class(string $cardType): string {
        $row = _pub_card_row($cardType);
        if (empty($row['slug'])) return '';
        return 'card-' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$row['slug']));
    }
}

if (!function_exists('pub_card_img_style')) {
    /**
     * Return an inline style string for the image wrapper of a card, providing
     * the aspect-ratio from card_styles.image_aspect_ratio (e.g. "1:1" → "aspect-ratio:1/1").
     * Falls back to the provided default ratio string if no DB row exists.
     */
    function pub_card_img_style(string $cardType, string $fallback = '1/1'): string {
        $row = _pub_card_row($cardType);
        $ratio = $fallback;
        if (!empty($row['image_aspect_ratio'])) {
            $r = preg_replace('/[^0-9:]/', '', (string)$row['image_aspect_ratio']);
            $r = str_replace(':', '/', $r);
            if ($r) $ratio = $r;
        }
        return 'aspect-ratio:' . htmlspecialchars($ratio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pub_entity_card_style')) {
    /**
     * Return an inline CSS style string for an entity card, applying the per-entity
     * card color from entity_settings.additional_settings JSON (card_color, card_text_color)
     * when set. When no per-entity color is set, returns the non-background parts of
     * $globalCardStyle (border, shadow, padding, etc.) so the CSS class can control
     * the background without being overridden by a redundant inline value.
     *
     * @param array  $entity         Entity data row (must include 'additional_settings' key if available)
     * @param string $globalCardStyle Fallback from pub_card_inline_style('entities')
     * @return string Inline CSS style string (without surrounding style="")
     */
    function pub_entity_card_style(array $entity, string $globalCardStyle = ''): string {
        if (!empty($entity['additional_settings'])) {
            $addSettings = json_decode((string)$entity['additional_settings'], true);
            if (is_array($addSettings) && !empty($addSettings['card_color'])) {
                $cc = preg_replace('/[^#a-zA-Z0-9(). ,%]/', '', (string)$addSettings['card_color']);
                $style = 'background-color:' . htmlspecialchars($cc, ENT_QUOTES, 'UTF-8');
                if (!empty($addSettings['card_text_color'])) {
                    $tc = preg_replace('/[^#a-zA-Z0-9(). ,%]/', '', (string)$addSettings['card_text_color']);
                    $style .= ';color:' . htmlspecialchars($tc, ENT_QUOTES, 'UTF-8');
                }
                return $style;
            }
        }
        // Strip the background-color declaration from the global fallback so the CSS class
        // (card-entities-default / .pub-entity-card) controls the background. This prevents
        // entities without a specific card color from having their background locked to the
        // same value as the page background via an inline style override.
        if ($globalCardStyle === '') return '';
        $parts = array_filter(array_map('trim', explode(';', $globalCardStyle)), function (string $p): bool {
            if ($p === '') return false;
            $prop = strtolower(strtok($p, ':'));
            return $prop !== 'background-color' && $prop !== 'background';
        });
        return implode(';', $parts);
    }
}


/* -------------------------------------------------------
 * 11b. Notifications loader — reads recent notifications for a tenant
 *      directly via the shared PDO connection. Returns array of rows
 *      sorted newest-first. Silently returns [] on error.
 *
 *      Columns used: id, tenant_id, title, message, sent_at,
 *                    notification_type_id, priority
 *      Compatible with the current `notifications` table schema.
 * ----------------------------------------------------- */
if (!function_exists('pub_load_notifications')) {
    function pub_load_notifications(int $tenantId, int $limit = 8): array {
        $pdo = pub_get_pdo();
        if (!$pdo) return [];
        // Resolve user from session (supports both session formats used across the app)
        $userId = (int)(
            $_SESSION['user_id'] ??
            ($_SESSION['user']['id'] ?? 0)
        );
        if (!$userId) return [];
        try {
            // Join notification_recipients so we only return notifications addressed
            // to this specific user, and include the is_read flag for the bell badge.
            $st = $pdo->prepare(
                "SELECT n.id, n.title, n.message, n.sent_at, n.priority,
                        nr.is_read,
                        nt.code AS type_code, nt.name AS type_name
                   FROM notification_recipients nr
                   JOIN notifications n          ON n.id  = nr.notification_id
              LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
                  WHERE nr.recipient_type = 'user'
                    AND nr.recipient_id   = ?
                    AND n.tenant_id       = ?
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())
                  ORDER BY n.sent_at DESC
                  LIMIT ?"
            );
            $st->execute([$userId, $tenantId, $limit]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $row['is_read'] = (bool)$row['is_read'];
            }
            unset($row);
            return $rows;
        } catch (Throwable $e) {
            error_log('[pub_load_notifications] ' . $e->getMessage());
            return [];
        }
    }
}


/* -------------------------------------------------------
 * 12. Compose the shared context globals
 * ----------------------------------------------------- */
$tenantId = (int)($_GET['tenant_id'] ?? $_SESSION['pub_tenant_id'] ?? 1);
$_SESSION['pub_tenant_id'] = $tenantId;

$theme = pub_load_theme($tenantId);
$_pubNotifications = pub_load_notifications($tenantId);

// Resolve logged-in user from session.
// Supports two formats set by different auth paths:
//   - $_SESSION['user'] = [...] (full array, set by API auth)
//   - $_SESSION['user_id'] = 7  (scalar, load user from DB)
$_pubUser = $_SESSION['user'] ?? $_SESSION['current_user'] ?? null;
if (empty($_pubUser['id']) && !empty($_SESSION['user_id'])) {
    // user_id is set but full user array is missing — load from DB
    $_pdo2 = pub_get_pdo();
    if ($_pdo2) {
        try {
            $__us = $_pdo2->prepare('SELECT id, name, username, email, preferred_language, is_active FROM users WHERE id = ? LIMIT 1');
            $__us->execute([(int)$_SESSION['user_id']]);
            $_pubUser = $__us->fetch() ?: null;
            if ($_pubUser) $_SESSION['user'] = $_pubUser; // cache for next request
        } catch (Throwable $_) {}
    }
    unset($_pdo2, $__us);
}

$GLOBALS['PUB_CONTEXT'] = [
    'lang'          => $lang,
    'dir'           => $dir,
    'tenant_id'     => $tenantId,
    'theme'         => $theme,
    'app'           => $appConfig,
    'user'          => $_pubUser,
    'notifications' => $_pubNotifications,
];

// Export user and login state as global PHP variables so any PHP page can use
// them BEFORE including partials/header.php (which also sets these from PUB_CONTEXT).
// Without this, pages like wishlist.php that check $_isLoggedIn before header.php
// is included would always see the variable as undefined (= null = not logged in).
$_user       = $_pubUser;
$_isLoggedIn = !empty($_pubUser['id']);

unset($_pubUser, $_pubNotifications);