<?php
// api/bootstrap_admin_ui.php
// Complete admin UI bootstrap: build $ADMIN_UI_PAYLOAD with theme, colors, buttons, cards, fonts, designs, strings, user, direction.
// - Safe, defensive, logs to api/error_log.txt
// - Uses mysqli (best-effort) and helper functions
// - Produces both arrays and maps for colors/buttons/cards for easier client usage
// - No output unless ?__admin_ui_debug=1
declare(strict_types=1);

require_once __DIR__ . '/bootstrap_admin_context.php';

// globals from context (optional)
$db = $GLOBALS['ADMIN_DB'] ?? null;
$currentUser = $GLOBALS['ADMIN_USER'] ?? null;

// logging
$API_LOG = __DIR__ . '/error_log.txt';
if (!file_exists($API_LOG)) @touch($API_LOG);
@chmod($API_LOG, 0664);

function _admin_ui_log(string $msg): void {
    global $API_LOG;
    $line = "[" . date('c') . "] bootstrap_admin_ui: " . $msg . PHP_EOL;
    @file_put_contents($API_LOG, $line, FILE_APPEND | LOCK_EX);
}

// Ensure payload exists
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? [];

/* -------------------------
   DB connection (best-effort)
   ------------------------- */
$db = null;
if (function_exists('connectDB')) {
    try {
        $maybe = @connectDB();
        if ($maybe instanceof mysqli) {
            $db = $maybe;
            @$db->set_charset('utf8mb4');
            _admin_ui_log("Using DB from connectDB()");
        }
    } catch (Throwable $e) {
        _admin_ui_log("connectDB() threw: " . $e->getMessage());
    }
}

if (!$db) {
    try {
        $cfg = [];
        if (is_readable(__DIR__ . '/config/db.php')) {
            $maybe = require __DIR__ . '/config/db.php';
            if (is_array($maybe)) $cfg = $maybe;
        }
        $host = $cfg['host'] ?? ($cfg['DB_HOST'] ?? (defined('DB_HOST') ? DB_HOST : getenv('DB_HOST')));
        $user = $cfg['user'] ?? ($cfg['DB_USER'] ?? (defined('DB_USER') ? DB_USER : getenv('DB_USER')));
        $pass = $cfg['pass'] ?? ($cfg['DB_PASS'] ?? (defined('DB_PASS') ? DB_PASS : getenv('DB_PASS')));
        $name = $cfg['name'] ?? ($cfg['DB_NAME'] ?? (defined('DB_NAME') ? DB_NAME : getenv('DB_NAME')));
        $port = isset($cfg['port']) ? (int)$cfg['port'] : (defined('DB_PORT') ? (int)DB_PORT : (int)(getenv('DB_PORT') ?: 3306));

        if ($host && $user && $name) {
            $mysqli = @new mysqli($host, $user, $pass, $name, $port);
            if ($mysqli && !$mysqli->connect_errno) {
                $db = $mysqli;
                @$db->set_charset('utf8mb4');
                _admin_ui_log("Connected DB using config ({$host})");
            } else {
                _admin_ui_log("mysqli connect failed: " . ($mysqli->connect_error ?? 'unknown'));
            }
        } else {
            _admin_ui_log("DB credentials missing");
        }
    } catch (Throwable $e) {
        _admin_ui_log("DB connection exception: " . $e->getMessage());
    }
}

/* -------------------------
   Helpers
   ------------------------- */

function stmt_get_all_rows(mysqli_stmt $stmt): array {
    $rows = [];
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        }
        return $rows;
    }
    $meta = $stmt->result_metadata();
    if (!$meta) return $rows;
    $fields = []; $refs = []; $row = [];
    while ($f = $meta->fetch_field()) {
        $fields[] = $f->name;
        $row[$f->name] = null;
        $refs[] = &$row[$f->name];
    }
    $meta->free();
    call_user_func_array([$stmt, 'bind_result'], $refs);
    while ($stmt->fetch()) {
        $r = [];
        foreach ($fields as $fn) $r[$fn] = $row[$fn];
        $rows[] = $r;
    }
    return $rows;
}

function safe_query_all(mysqli $db, string $sql, array $params = [], string $types = ''): array {
    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            _admin_ui_log("prepare failed: " . $db->error . " | SQL: " . $sql);
            return [];
        }
        if (!empty($params)) {
            if ($types === '') {
                $t = '';
                foreach ($params as $p) {
                    $t .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
                }
                $types = $t;
            }
            $bind = array_merge([$types], $params);
            $refs = [];
            foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        if (!$stmt->execute()) {
            _admin_ui_log("execute failed: " . $stmt->error . " | SQL: " . $sql);
            $stmt->close();
            return [];
        }
        $rows = stmt_get_all_rows($stmt);
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        _admin_ui_log("safe_query_all exception: " . $e->getMessage() . " | SQL: " . $sql);
        return [];
    }
}

function safeSlug(string $s): string {
    $s = preg_replace('/[^a-z0-9_\-]+/i', '-', trim($s));
    $s = preg_replace('/-+/', '-', $s);
    $s = trim($s, '-');
    return strtolower($s);
}

function normalize_color_value(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $v)) return strtoupper($v);
    if (preg_match('/^(rgb|rgba|hsl|hsla)\(/i', $v)) return $v;
    if (strtolower($v) === 'transparent') return 'transparent';
    if (preg_match('/^[A-Fa-f0-9]{6}$/', $v)) return '#' . strtoupper($v);
    if (preg_match('/^[a-z\-]+$/i', $v)) return strtolower($v);
    return null;
}

function normalize_color_key(string $k): string {
    $k = trim($k);
    $k = preg_replace('/(_color|_main|_bg|_background)$/i', '', $k);
    $k = str_replace('_', '-', $k);
    return safeSlug($k);
}

/* -------------------------
   Determine user language & direction
   ------------------------- */
$userLang = 'en';
$userDirection = 'ltr';
$rtlLangs = ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'];

if (!empty($_SESSION['preferred_language'])) {
    $userLang = strtolower(trim((string)$_SESSION['preferred_language']));
} elseif (!empty($_SESSION['lang'])) {
    $userLang = strtolower(trim((string)$_SESSION['lang']));
}

if ($db && $currentUser && !empty($currentUser['id'])) {
    try {
        $uid = (int)$currentUser['id'];
        $r = safe_query_all($db, "SELECT preferred_language FROM users WHERE id = ? LIMIT 1", [$uid], 'i');
        if (!empty($r) && !empty($r[0]['preferred_language'])) $userLang = strtolower(trim((string)$r[0]['preferred_language']));
    } catch (Throwable $e) {
        _admin_ui_log("user language lookup failed: " . $e->getMessage());
    }
}

$userLang = $userLang ?: 'en';
$userDirection = in_array(strtolower(substr($userLang,0,2)), $rtlLangs, true) ? 'rtl' : 'ltr';

$ADMIN_UI_PAYLOAD['lang'] = $userLang;
$ADMIN_UI_PAYLOAD['direction'] = $userDirection;

/* -------------------------
   Module detection (for translations)
   ------------------------- */
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$baseName = basename($currentScript, '.php');
$moduleName = '';

$commonMappings = [
    'delivery_companies' => 'DeliveryCompany',
    'delivery_company'   => 'DeliveryCompany',
    'drivers'            => 'Drivers',
    'orders'             => 'Orders',
    'products'           => 'Products',
    'users'              => 'Users',
    'settings'           => 'Settings',
];

if (isset($commonMappings[$baseName])) {
    $moduleName = $commonMappings[$baseName];
} else {
    $singular = preg_replace('/s$/', '', $baseName);
    $moduleName = preg_replace_callback('/_([a-z])/', function($m){ return strtoupper($m[1]); }, ucfirst($singular));
}

/* -------------------------
   Load translations (robust with cache)
   ------------------------- */
$languagesCandidates = [
    dirname(__DIR__) . '/languages',
    __DIR__ . '/../languages',
    (($_SERVER['DOCUMENT_ROOT'] ?? '') !== '') ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/languages' : '',
    dirname(__DIR__, 2) . '/languages',
];

$languagesBaseDir = null;
foreach ($languagesCandidates as $cand) {
    if ($cand && is_dir($cand)) { $languagesBaseDir = realpath($cand); break; }
}

$ADMIN_UI_PAYLOAD['strings'] = [];
if ($languagesBaseDir) {
    $safeLang = preg_replace('/[^a-z0-9_\-]/i', '_', strtolower((string)$userLang));
    $safeModule = $moduleName ? preg_replace('/[^a-z0-9_\-]/i', '_', (string)$moduleName) : 'global';
    $cacheFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . "admin_strings_{$safeModule}_{$safeLang}.json";
    $cacheTtl = 120;

    $merged = null;
    if (is_readable($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $raw = @file_get_contents($cacheFile);
        $decoded = $raw ? @json_decode($raw, true) : null;
        if (is_array($decoded)) $merged = $decoded;
        else @unlink($cacheFile);
    }
    if ($merged === null) {
        $merged = [];
        $loadJson = function($path) {
            if (!$path || !is_readable($path)) return null;
            $json = @file_get_contents($path);
            if ($json === false) return null;
            if (substr($json, 0, 3) === "\xEF\xBB\xBF") $json = preg_replace('/^\x{FEFF}/u', '', $json);
            $data = @json_decode($json, true);
            if (!is_array($data)) return null;
            return isset($data['strings']) && is_array($data['strings']) ? $data['strings'] : $data;
        };

        $adminEn = $languagesBaseDir . '/admin/en.json';
        $tmp = $loadJson($adminEn);
        if (is_array($tmp)) $merged = $tmp;

        if (!empty($userLang) && strtolower($userLang) !== 'en') {
            $adminLangFile = $languagesBaseDir . "/admin/{$userLang}.json";
            $tmp = $loadJson($adminLangFile);
            if (is_array($tmp)) $merged = array_replace_recursive($merged, $tmp);
        }

        if (!empty($moduleName)) {
            $moduleEn = $languagesBaseDir . "/{$moduleName}/en.json";
            $tmp = $loadJson($moduleEn);
            if (is_array($tmp)) $merged = array_replace_recursive($merged, $tmp);
            if (!empty($userLang) && strtolower($userLang) !== 'en') {
                $moduleLang = $languagesBaseDir . "/{$moduleName}/{$userLang}.json";
                $tmp = $loadJson($moduleLang);
                if (is_array($tmp)) $merged = array_replace_recursive($merged, $tmp);
            }
        }

        $tmpCache = $cacheFile . '.tmp';
        @file_put_contents($tmpCache, json_encode($merged, JSON_UNESCAPED_UNICODE));
        @rename($tmpCache, $cacheFile);
    }
    $ADMIN_UI_PAYLOAD['strings'] = is_array($merged) ? $merged : [];
} else {
    _admin_ui_log("translations: languages directory not found. Candidates: " . implode(', ', $languagesCandidates));
}

/* -------------------------
   Themes selection
   ------------------------- */
$themeId = null; $themeName = null; $themeSlug = null;
if ($db) {
    try {
        if (!empty($_GET['admin_theme_id'])) {
            $themeId = (int)$_GET['admin_theme_id'];
        } else {
            $rows = safe_query_all($db, "SELECT id, name, slug FROM themes WHERE is_active = 1 ORDER BY is_default DESC LIMIT 1");
            if (!empty($rows)) {
                $r = $rows[0];
                $themeId = (int)$r['id'];
                $themeName = $r['name'] ?? null;
                $themeSlug = $r['slug'] ?? null;
            } else {
                $rows2 = safe_query_all($db, "SELECT id, name, slug FROM themes ORDER BY id DESC LIMIT 1");
                if (!empty($rows2)) {
                    $r2 = $rows2[0];
                    $themeId = (int)$r2['id'];
                    $themeName = $r2['name'] ?? null;
                    $themeSlug = $r2['slug'] ?? null;
                }
            }
        }
    } catch (Throwable $e) {
        _admin_ui_log("theme selection error: " . $e->getMessage());
    }
}

/* -------------------------
   Default theme structure
   ------------------------- */
$ADMIN_UI_PAYLOAD['theme'] = $ADMIN_UI_PAYLOAD['theme'] ?? [
    'id' => $themeId ?? null,
    'name' => $themeName ?? 'Default',
    'slug' => $themeSlug ?? 'default',
    'colors' => [],
    'colors_map' => [],
    'buttons' => [],
    'buttons_map' => [],
    'cards' => [],
    'cards_map' => [],
    'fonts' => [],
    'designs' => []
];

/* -------------------------
   Populate theme arrays (full)
   ------------------------- */
if ($db && $themeId) {
    try {
        // Colors (permissive normalization)
        // Note: removed 'other' column to match schema
        $rows = safe_query_all($db, "SELECT id, theme_id, setting_key, setting_name, color_value, category, is_active, sort_order FROM color_settings WHERE theme_id = ? ORDER BY sort_order ASC", [$themeId], 'i');
        $colors = [];
        $colorsMap = [];
        foreach ($rows as $r) {
            $rawVal = isset($r['color_value']) ? trim((string)$r['color_value']) : '';
            $norm = normalize_color_value($rawVal);
            $valToExpose = $norm ?? ($rawVal !== '' ? $rawVal : null);
            $keyNormalized = normalize_color_key((string)($r['setting_key'] ?? ''));
            $entry = [
                'id' => isset($r['id']) ? (int)$r['id'] : null,
                'setting_key' => $r['setting_key'] ?? null,
                'setting_name' => $r['setting_name'] ?? null,
                'color_value' => $valToExpose,
                'category' => $r['category'] ?? null,
                'is_active' => (int)($r['is_active'] ?? 0),
                'sort_order' => (int)($r['sort_order'] ?? 0)
            ];
            $colors[] = $entry;
            if ($keyNormalized) $colorsMap[$keyNormalized] = $valToExpose;
        }
        if ($colors) {
            $ADMIN_UI_PAYLOAD['theme']['colors'] = $colors;
            $ADMIN_UI_PAYLOAD['theme']['colors_map'] = $colorsMap;
        } else {
            _admin_ui_log("theme.colors: no rows for theme_id {$themeId}");
        }
        _admin_ui_log("loaded color_settings: " . count($colors) . " rows");

        // Buttons (full)
        $btnRows = safe_query_all($db, "SELECT id, theme_id, name, slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color, hover_border_color, is_active FROM button_styles WHERE theme_id = ? ORDER BY id ASC", [$themeId], 'i');
        $buttons = [];
        $buttonsMap = [];
        foreach ($btnRows as $r) {
            $rawSlug = trim((string)($r['slug'] ?? $r['name'] ?? ''));
            $slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $rawSlug));
            $slug = preg_replace('/^btn-/', '', $slug);
            $bg = normalize_color_value($r['background_color'] ?? '');
            $text = normalize_color_value($r['text_color'] ?? '');
            $hover_bg = normalize_color_value($r['hover_background_color'] ?? '');
            $hover_text = normalize_color_value($r['hover_text_color'] ?? '');
            $entry = [
                'id' => (int)($r['id'] ?? 0),
                'slug' => $slug,
                'name' => $r['name'] ?? null,
                'button_type' => $r['button_type'] ?? null,
                'background_color' => $bg ?? ($r['background_color'] ?? null),
                'text_color' => $text ?? ($r['text_color'] ?? null),
                'border_color' => normalize_color_value($r['border_color'] ?? '') ?? ($r['border_color'] ?? null),
                'border_width' => isset($r['border_width']) ? (int)$r['border_width'] : 0,
                'border_radius' => isset($r['border_radius']) ? (int)$r['border_radius'] : 4,
                'padding' => $r['padding'] ?? '10px 20px',
                'font_size' => $r['font_size'] ?? '14px',
                'font_weight' => $r['font_weight'] ?? 'normal',
                'hover_background_color' => $hover_bg ?? ($r['hover_background_color'] ?? null),
                'hover_text_color' => $hover_text ?? ($r['hover_text_color'] ?? null),
                'hover_border_color' => normalize_color_value($r['hover_border_color'] ?? '') ?? ($r['hover_border_color'] ?? null),
                'is_active' => (int)($r['is_active'] ?? 0)
            ];
            $buttons[] = $entry;
            if ($slug) $buttonsMap[$slug] = $entry;
        }
        if ($buttons) {
            $ADMIN_UI_PAYLOAD['theme']['buttons'] = $buttons;
            $ADMIN_UI_PAYLOAD['theme']['buttons_map'] = $buttonsMap;
        }
        _admin_ui_log("loaded button_styles: " . count($buttons) . " rows");

        // Cards
        $cardRows = safe_query_all($db, "SELECT id, theme_id, name, slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding, hover_effect, text_align, image_aspect_ratio, is_active FROM card_styles WHERE theme_id = ? ORDER BY id ASC", [$themeId], 'i');
        $cards = [];
        $cardsMap = [];
        foreach ($cardRows as $r) {
            $rawSlug = trim((string)($r['slug'] ?? $r['name'] ?? ''));
            $slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $rawSlug));
            $slug = preg_replace('/^card-/', '', $slug);
            $entry = [
                'id' => (int)($r['id'] ?? 0),
                'slug' => $slug,
                'name' => $r['name'] ?? null,
                'card_type' => $r['card_type'] ?? null,
                'background_color' => normalize_color_value($r['background_color'] ?? '') ?? ($r['background_color'] ?? null),
                'border_color' => normalize_color_value($r['border_color'] ?? '') ?? ($r['border_color'] ?? null),
                'border_width' => isset($r['border_width']) ? (int)$r['border_width'] : 1,
                'border_radius' => isset($r['border_radius']) ? (int)$r['border_radius'] : 8,
                'shadow_style' => $r['shadow_style'] ?? 'none',
                'padding' => $r['padding'] ?? '16px',
                'hover_effect' => $r['hover_effect'] ?? 'none',
                'text_align' => $r['text_align'] ?? 'left',
                'image_aspect_ratio' => $r['image_aspect_ratio'] ?? null,
                'is_active' => (int)($r['is_active'] ?? 0)
            ];
            $cards[] = $entry;
            if ($slug) $cardsMap[$slug] = $entry;
        }
        if ($cards) {
            $ADMIN_UI_PAYLOAD['theme']['cards'] = $cards;
            $ADMIN_UI_PAYLOAD['theme']['cards_map'] = $cardsMap;
        }
        _admin_ui_log("loaded card_styles: " . count($cards) . " rows");

        // Fonts
        $fontRows = safe_query_all($db, "SELECT id, theme_id, setting_key, setting_name, font_family, font_size, font_weight, line_height, category, is_active, sort_order FROM font_settings WHERE theme_id = ? ORDER BY sort_order ASC", [$themeId], 'i');
        $fonts = [];
        foreach ($fontRows as $r) {
            $fonts[] = [
                'id' => (int)($r['id'] ?? 0),
                'setting_key' => $r['setting_key'] ?? null,
                'setting_name' => $r['setting_name'] ?? null,
                'font_family' => $r['font_family'] ?? null,
                'font_size' => $r['font_size'] ?? null,
                'font_weight' => $r['font_weight'] ?? null,
                'line_height' => $r['line_height'] ?? null,
                'category' => $r['category'] ?? 'other',
                'is_active' => (int)($r['is_active'] ?? 0),
                'sort_order' => (int)($r['sort_order'] ?? 0)
            ];
        }
        if ($fonts) $ADMIN_UI_PAYLOAD['theme']['fonts'] = $fonts;
        _admin_ui_log("loaded font_settings: " . count($fonts) . " rows");

        // Designs (key/value)
        $designsRows = safe_query_all($db, "SELECT setting_key, setting_name, setting_value, setting_type, category FROM design_settings WHERE theme_id = ? ORDER BY sort_order ASC", [$themeId], 'i');
        $designs = [];
        foreach ($designsRows as $r) {
            if (isset($r['setting_key'])) $designs[$r['setting_key']] = $r['setting_value'];
        }
        if ($designs) $ADMIN_UI_PAYLOAD['theme']['designs'] = $designs;
        _admin_ui_log("loaded design_settings: " . count($designs) . " rows");

    } catch (Throwable $e) {
        _admin_ui_log("theme load error: " . $e->getMessage());
    }
} else {
    _admin_ui_log("theme load skipped (no DB or themeId)");
}

/* -------------------------
   Normalize & expose final payload
   ------------------------- */
if (!isset($ADMIN_UI_PAYLOAD['strings']) || !is_array($ADMIN_UI_PAYLOAD['strings'])) $ADMIN_UI_PAYLOAD['strings'] = [];
$GLOBALS['ADMIN_UI'] = $ADMIN_UI_PAYLOAD;
$ADMIN_UI = $ADMIN_UI_PAYLOAD;

/* -------------------------
   Debug JSON output when requested (safe)
   ------------------------- */
$debug = (php_sapi_name() === 'cli') || (!empty($_GET['__admin_ui_debug']) && $_GET['__admin_ui_debug'] == '1');
if ($debug) {
    if (php_sapi_name() !== 'cli') header('Content-Type: application/json; charset=utf-8');
    $out = [
        'ok' => true,
        'db_connected' => $db ? true : false,
        'user_lang' => $userLang,
        'user_direction' => $userDirection,
        'detected_module' => $moduleName,
        'languages_dir' => $languagesBaseDir ?? null,
        'strings_count' => count($ADMIN_UI_PAYLOAD['strings'] ?? []),
        'theme' => $ADMIN_UI_PAYLOAD['theme'] ?? []
    ];
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

return;