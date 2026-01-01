<?php
// htdocs/admin/includes/init.php
// Robust init: ensure DB connection ($mysqli), session, load UI translations (DB or language file),
// set $preferred_lang, $html_direction, $csrf_token and $ui_strings.
// Added helper functions: ui_t(), csrf_field(), enqueue_css(), enqueue_js(), print_enqueued_assets(), print_admin_ui_js()
// Save as UTF-8 without BOM.

declare(strict_types=1);

error_reporting(E_ALL);
@ini_set('display_errors', 0);

// Start session if not started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Ensure DB connection available as $mysqli
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    $dbConfigFile = __DIR__ . '/../../api/config/db.php';
    if (is_readable($dbConfigFile)) {
        require_once $dbConfigFile;
        try {
            if (function_exists('connectDB')) {
                $tmp = connectDB();
                if ($tmp && ($tmp instanceof mysqli) && !$tmp->connect_errno) {
                    $mysqli = $tmp;
                    @$mysqli->set_charset('utf8mb4');
                } else {
                    unset($mysqli);
                }
            }
        } catch (Throwable $e) {
            unset($mysqli);
        }
    }
}

// Minimal $ui_strings container
$ui_strings = $ui_strings ?? [];
if (!isset($ui_strings['strings']) || !is_array($ui_strings['strings'])) $ui_strings['strings'] = [];
if (!isset($ui_strings['nav']) || !is_array($ui_strings['nav'])) $ui_strings['nav'] = [];
if (!isset($ui_strings['buttons']) || !is_array($ui_strings['buttons'])) $ui_strings['buttons'] = [];

// Determine preferred language: session -> user -> fallback 'en'
$preferred_lang = $_SESSION['preferred_language'] ?? ($_SESSION['user']['preferred_language'] ?? ($ui_strings['lang'] ?? 'en'));
$preferred_lang = is_string($preferred_lang) ? $preferred_lang : 'en';
$preferred_lang = strtolower(explode('-', $preferred_lang)[0]);
$preferred_lang = preg_replace('/[^a-z0-9_-]/i', '', $preferred_lang);
if ($preferred_lang === '') $preferred_lang = 'en';

// HTML direction
$html_direction = $_SESSION['html_direction'] ?? ($ui_strings['direction'] ?? (($preferred_lang === 'ar' || $preferred_lang === 'fa' || $preferred_lang === 'he') ? 'rtl' : 'ltr'));

// ---- Simple session cache for translations to reduce DB / FS hits ----
$ui_cache_key = 'ui_strings_' . $preferred_lang;
$ui_cache_ttl = 300; // seconds (adjust as needed)

// Try to restore from session cache
if (!empty($_SESSION[$ui_cache_key]) && is_array($_SESSION[$ui_cache_key]) && (!empty($_SESSION[$ui_cache_key]['__cached_at']) && (time() - (int)$_SESSION[$ui_cache_key]['__cached_at'] < $ui_cache_ttl))) {
    $ui_strings = array_replace_recursive($ui_strings, $_SESSION[$ui_cache_key]['data'] ?? []);
} else {
    // Try to load translations from DB (namespace approach) if available
    $loadedFromDB = false;
    if (isset($mysqli) && ($mysqli instanceof mysqli)) {
        try {
            $res = $mysqli->query("SHOW TABLES LIKE 'translations'");
            if ($res && $res->num_rows > 0) {
                $loadedFromDB = true;
                $stmt = $mysqli->prepare("SELECT namespace, t_key, value FROM translations WHERE language_code = ? ORDER BY namespace, t_key");
                if ($stmt) {
                    $stmt->bind_param('s', $preferred_lang);
                    $stmt->execute();
                    $r = $stmt->get_result();
                    $db_strings = [];
                    while ($row = $r->fetch_assoc()) {
                        $ns = $row['namespace'] ?? 'strings';
                        $key = $row['t_key'] ?? '';
                        $val = $row['value'] ?? '';
                        if ($ns === '') $ns = 'strings';
                        if (!isset($db_strings[$ns]) || !is_array($db_strings[$ns])) $db_strings[$ns] = [];
                        $db_strings[$ns][$key] = $val;
                    }
                    $stmt->close();
                    if (!empty($db_strings)) {
                        $ui_strings = array_replace_recursive($ui_strings, $db_strings);
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore DB translation loading errors; fallback to file
        }
    }

    // If not loaded from DB or still sparse, attempt to load language file
    $possibleLangPaths = [
        rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/admin/languages/admin/' . $preferred_lang . '.json',
        rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/languages/admin/' . $preferred_lang . '.json',
        __DIR__ . '/../languages/admin/' . $preferred_lang . '.json',
    ];
    foreach ($possibleLangPaths as $langFile) {
        if (is_readable($langFile)) {
            $j = @file_get_contents($langFile);
            $decoded = $j ? @json_decode($j, true) : null;
            if (is_array($decoded)) {
                $ui_strings = array_replace_recursive((array)$ui_strings, (array)$decoded);
                break;
            }
        }
    }

    // store into session cache
    $_SESSION[$ui_cache_key] = [
        '__cached_at' => time(),
        'data' => $ui_strings,
    ];
}

// Ensure keys exist
$ui_strings['lang'] = $preferred_lang;
$ui_strings['direction'] = $html_direction;
if (!isset($ui_strings['nav'])) $ui_strings['nav'] = [];
if (!isset($ui_strings['strings'])) $ui_strings['strings'] = [];
if (!isset($ui_strings['buttons'])) $ui_strings['buttons'] = [];

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $_SESSION['csrf_token'] = substr(md5(uniqid('', true)), 0, 32);
    }
}
$csrf_token = $_SESSION['csrf_token'];

// -------------------- Helper functions --------------------

// ui_t: resolve translation key like "strings.some_key" or "nav.home"
if (!function_exists('ui_t')) {
    function ui_t(string $key, $default = '') {
        global $ui_strings;
        $parts = preg_split('/[\\.\\/]/', $key);
        $cur = $ui_strings;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) return $default !== '' ? $default : $key;
            $cur = $cur[$p];
        }
        if (is_string($cur)) return $cur;
        return $default !== '' ? $default : $key;
    }
}

// csrf_field: returns HTML hidden input for CSRF
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        $token = $_SESSION['csrf_token'] ?? '';
        return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($token, ENT_QUOTES).'">';
    }
}

// Simple enqueuer: collect CSS/JS to print later in header; avoids duplicates.
$GLOBALS['__admin_enqueued_css'] = $GLOBALS['__admin_enqueued_css'] ?? [];
$GLOBALS['__admin_enqueued_js']  = $GLOBALS['__admin_enqueued_js'] ?? [];

if (!function_exists('enqueue_css')) {
    function enqueue_css(string $href): void {
        $href = (string)$href;
        if (in_array($href, $GLOBALS['__admin_enqueued_css'], true)) return;
        $GLOBALS['__admin_enqueued_css'][] = $href;
        if (headers_sent()) {
            echo '<link rel="stylesheet" href="'.htmlspecialchars($href, ENT_QUOTES).'">'."\n";
        }
    }
}

if (!function_exists('enqueue_js')) {
    function enqueue_js(string $src, bool $defer = false): void {
        $src = (string)$src;
        if (in_array($src, $GLOBALS['__admin_enqueued_js'], true)) return;
        $GLOBALS['__admin_enqueued_js'][] = $src;
        if (headers_sent()) {
            echo '<script src="'.htmlspecialchars($src, ENT_QUOTES).'"'.($defer ? ' defer' : '').'></script>'."\n";
        }
    }
}

// print enqueued assets (to be called from includes/header.php inside <head>)
if (!function_exists('print_enqueued_assets')) {
    function print_enqueued_assets(): void {
        if (!empty($GLOBALS['__admin_enqueued_css'])) {
            foreach ($GLOBALS['__admin_enqueued_css'] as $css) {
                echo '<link rel="stylesheet" href="'.htmlspecialchars($css, ENT_QUOTES).'">' . PHP_EOL;
            }
        }
        if (!empty($GLOBALS['__admin_enqueued_js'])) {
            foreach ($GLOBALS['__admin_enqueued_js'] as $js) {
                echo '<script src="'.htmlspecialchars($js, ENT_QUOTES).'" defer></script>' . PHP_EOL;
            }
        }
    }
}

// Print window.ADMIN_UI JS object for client-side translations (call in header.php before other scripts)
if (!function_exists('print_admin_ui_js')) {
    function print_admin_ui_js(): void {
        global $ui_strings;
        $safe = json_encode($ui_strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        if ($safe === false) $safe = '{}';
        echo '<script>window.ADMIN_UI = window.ADMIN_UI || {}; try{ Object.assign(window.ADMIN_UI, ' . $safe . '); window.ADMIN_LANG = window.ADMIN_UI.lang || window.ADMIN_LANG || \'' . addslashes($ui_strings['lang'] ?? 'en') . '\'; }catch(e){console.error("admin ui init failed",e);}</script>' . PHP_EOL;
    }
}

// -------------------- End helpers --------------------

// Expose globals for includes
// $preferred_lang, $html_direction, $ui_strings, $csrf_token, $mysqli are available to requiring files.
return;