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
   Helper: Check if table exists
   ------------------------- */
function table_exists(mysqli $db, string $table): bool {
    try {
        $t = $db->real_escape_string($table);
        $res = $db->query("SHOW TABLES LIKE '{$t}'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/* -------------------------
   Fetch user information with session-cached roles & permissions
   ------------------------- */
$userInfo = [
    'id' => null,
    'username' => null,
    'email' => null,
    'preferred_language' => null,
    'avatar' => null,
    'is_active' => false,
    'role_id' => null,
    'permissions' => [],
    'roles' => []
];

if (!empty($currentUser['id'])) {
    $uid = (int)$currentUser['id'];
    
    // Basic user info from currentUser (already fetched by bootstrap_admin_context.php)
    $userInfo['id'] = $uid;
    $userInfo['username'] = $currentUser['username'] ?? null;
    $userInfo['email'] = $currentUser['email'] ?? null;
    $userInfo['role_id'] = $currentUser['role_id'] ?? null;
    $userInfo['preferred_language'] = $currentUser['preferred_language'] ?? null;
    
    // Try to get additional fields if available
    if (isset($currentUser['avatar'])) {
        $userInfo['avatar'] = $currentUser['avatar'];
    }
    if (isset($currentUser['is_active'])) {
        $userInfo['is_active'] = !empty($currentUser['is_active']);
    }
    
    // Get permissions from session cache first
    $perms = $_SESSION['permissions'] ?? [];
    $roles = $_SESSION['roles'] ?? [];
    
    // If session cache is empty and DB is available, fetch from database
    if (empty($perms) && $db && table_exists($db, 'user_permissions')) {
        try {
            $rows = safe_query_all($db, "
                SELECT p.key_name 
                FROM permissions p
                JOIN user_permissions up ON up.permission_id = p.id
                WHERE up.user_id = ?
            ", [$uid], 'i');
            foreach ($rows as $r) {
                $perms[] = $r['key_name'];
            }
            _admin_ui_log("loaded permissions from user_permissions for user {$uid}: " . count($perms) . " permissions");
        } catch (Throwable $e) {
            _admin_ui_log("user_permissions query failed: " . $e->getMessage());
        }
    }
    
    // If still empty, try role_permissions
    if (empty($perms) && $db && !empty($userInfo['role_id']) && table_exists($db, 'role_permissions')) {
        try {
            $rows = safe_query_all($db, "
                SELECT p.key_name 
                FROM permissions p
                JOIN role_permissions rp ON rp.permission_id = p.id
                WHERE rp.role_id = ?
            ", [$userInfo['role_id']], 'i');
            foreach ($rows as $r) {
                $perms[] = $r['key_name'];
            }
            _admin_ui_log("loaded permissions from role_permissions for user {$uid}: " . count($perms) . " permissions");
        } catch (Throwable $e) {
            _admin_ui_log("role_permissions query failed: " . $e->getMessage());
        }
    }
    
    // Fetch roles if empty and DB available
    if (empty($roles) && $db && table_exists($db, 'roles')) {
        try {
            $rows = safe_query_all($db, "
                SELECT r.id, r.key_name 
                FROM roles r
                JOIN users u ON u.role_id = r.id
                WHERE u.id = ?
            ", [$uid], 'i');
            foreach ($rows as $r) {
                $roles[] = $r['key_name'];
                // Populate role_id if it's missing
                if (empty($userInfo['role_id']) && isset($r['id'])) {
                    $userInfo['role_id'] = (int)$r['id'];
                }
            }
            _admin_ui_log("loaded roles for user {$uid}: " . count($roles) . " roles");
        } catch (Throwable $e) {
            _admin_ui_log("roles query failed: " . $e->getMessage());
        }
    }
    
    // Deduplicate and store back to session
    $perms = array_values(array_unique($perms));
    $roles = array_values(array_unique($roles));
    
    $_SESSION['permissions'] = $perms;
    $_SESSION['roles'] = $roles;
    
    // If role_id is still null but we have roles, fetch the role_id from the first role
    if (empty($userInfo['role_id']) && !empty($roles) && $db && table_exists($db, 'roles')) {
        try {
            $firstRole = $roles[0];
            $rows = safe_query_all($db, "
                SELECT id FROM roles WHERE key_name = ? LIMIT 1
            ", [$firstRole], 's');
            if (!empty($rows)) {
                $userInfo['role_id'] = (int)$rows[0]['id'];
                _admin_ui_log("populated role_id {$userInfo['role_id']} from role key_name: {$firstRole}");
            }
        } catch (Throwable $e) {
            _admin_ui_log("role_id lookup failed: " . $e->getMessage());
        }
    }
    
    $userInfo['permissions'] = $perms;
    $userInfo['roles'] = $roles;
} else {
    _admin_ui_log("user info fetch skipped: no current user");
}

$ADMIN_UI_PAYLOAD['user'] = $userInfo;

/* -------------------------
   Determine user language & direction
   ------------------------- */
$userLang = 'en';
$userDirection = 'ltr';
$rtlLangs = ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'];

// Priority: user DB record > session preferred_language > session lang
if (!empty($userInfo['preferred_language'])) {
    $userLang = strtolower(trim((string)$userInfo['preferred_language']));
} elseif (!empty($_SESSION['preferred_language'])) {
    $userLang = strtolower(trim((string)$_SESSION['preferred_language']));
} elseif (!empty($_SESSION['lang'])) {
    $userLang = strtolower(trim((string)$_SESSION['lang']));
}

$userLang = $userLang ?: 'en';
$userDirection = in_array(strtolower(substr($userLang,0,2)), $rtlLangs, true) ? 'rtl' : 'ltr';

$ADMIN_UI_PAYLOAD['lang'] = $userLang;
$ADMIN_UI_PAYLOAD['direction'] = $userDirection;

/* -------------------------
   Module detection (for translations) - Enhanced & Dynamic
   ------------------------- */
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$baseName = basename($currentScript, '.php');
$moduleName = '';

// Extended common mappings
$commonMappings = [
    'delivery_companies' => 'DeliveryCompany',
    'delivery_company'   => 'DeliveryCompany',
    'delivery_zone'      => 'DeliveryZone',
    'delivery_zones'     => 'DeliveryZone',
    'independent_driver' => 'IndependentDriver',
    'independent_drivers'=> 'IndependentDriver',
    'drivers'            => 'Drivers',
    'orders'             => 'Orders',
    'products'           => 'Products',
    'product_studio'     => 'Products',
    'users'              => 'Users',
    'settings'           => 'Settings',
    'vendors'            => 'Vendors',
    'vendor'             => 'Vendors',
    'banners'            => 'Banners',
    'banner'             => 'Banners',
    'categories'         => 'Categories',
    'category'           => 'Categories',
    'menus'              => 'Menus',
    'menu'               => 'Menus',
    'menu_form'          => 'Menus',
    'menus_list'         => 'Menus',
    'roles'              => 'Roles',
    'role'               => 'Roles',
    'permissions'        => 'Permissions',
    'permission'         => 'Permissions',
];

if (isset($commonMappings[$baseName])) {
    $moduleName = $commonMappings[$baseName];
} else {
    // Try to extract from path (e.g., /admin/fragments/products.php -> products)
    $pathInfo = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/([a-z0-9_]+)\.php#i', $pathInfo, $match)) {
        $extracted = $match[1];
        if (isset($commonMappings[$extracted])) {
            $moduleName = $commonMappings[$extracted];
        }
    }
    
    // Fallback to smart conversion
    if (!$moduleName) {
        $singular = preg_replace('/s$/', '', $baseName);
        $moduleName = preg_replace_callback('/_([a-z])/', function($m){ return strtoupper($m[1]); }, ucfirst($singular));
    }
}

_admin_ui_log("detected module: '{$moduleName}' from script: '{$baseName}'");

/* -------------------------
   Load translations (robust with cache & recursive merging)
   ------------------------- */
$languagesCandidates = [
    dirname(__DIR__) . '/languages',
    __DIR__ . '/../languages',
    (($_SERVER['DOCUMENT_ROOT'] ?? '') !== '') ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/languages' : '',
    dirname(__DIR__, 2) . '/languages',
    __DIR__ . '/languages',
];

$languagesBaseDir = null;
foreach ($languagesCandidates as $cand) {
    if ($cand && is_dir($cand)) { 
        $languagesBaseDir = realpath($cand); 
        _admin_ui_log("languages directory found: {$languagesBaseDir}");
        break; 
    }
}

$ADMIN_UI_PAYLOAD['strings'] = [];
if ($languagesBaseDir) {
    $safeLang = preg_replace('/[^a-z0-9_\-]/i', '_', strtolower((string)$userLang));
    $safeModule = $moduleName ? preg_replace('/[^a-z0-9_\-]/i', '_', (string)$moduleName) : 'global';
    $cacheFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . "admin_strings_{$safeModule}_{$safeLang}.json";
    $cacheTtl = 120; // 2 minutes TTL

    $merged = null;
    $cacheHit = false;
    
    // Try to load from cache with TTL check
    if (is_readable($cacheFile)) {
        $cacheAge = time() - filemtime($cacheFile);
        if ($cacheAge < $cacheTtl) {
            $raw = @file_get_contents($cacheFile);
            $decoded = $raw ? @json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $merged = $decoded;
                $cacheHit = true;
                _admin_ui_log("translations cache HIT (age: {$cacheAge}s, TTL: {$cacheTtl}s)");
            } else {
                @unlink($cacheFile);
                _admin_ui_log("translations cache INVALID, deleted");
            }
        } else {
            @unlink($cacheFile);
            _admin_ui_log("translations cache EXPIRED (age: {$cacheAge}s, TTL: {$cacheTtl}s)");
        }
    }
    
    // Load and merge translations if no valid cache
    if ($merged === null) {
        _admin_ui_log("translations cache MISS, loading from files");
        $merged = [];
        
        // Helper function for loading JSON with BOM handling
        $loadJson = function($path) {
            if (!$path || !is_readable($path)) {
                _admin_ui_log("translation file not readable: {$path}");
                return null;
            }
            $json = @file_get_contents($path);
            if ($json === false) {
                _admin_ui_log("translation file read failed: {$path}");
                return null;
            }
            // Remove BOM if present
            if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
                $json = preg_replace('/^\x{FEFF}/u', '', $json);
            }
            $data = @json_decode($json, true);
            if (!is_array($data)) {
                _admin_ui_log("translation file invalid JSON: {$path}");
                return null;
            }
            // Support both direct strings and nested under 'strings' key
            $result = isset($data['strings']) && is_array($data['strings']) ? $data['strings'] : $data;
            _admin_ui_log("loaded translation file: {$path} (" . count($result) . " keys)");
            return $result;
        };
        
        // Recursive merge function for deep merging
        $recursiveMerge = function($base, $overlay) use (&$recursiveMerge) {
            if (!is_array($base)) $base = [];
            if (!is_array($overlay)) return $base;
            
            foreach ($overlay as $key => $value) {
                if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = $recursiveMerge($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
            return $base;
        };

        // Load base admin English translations
        $adminEn = $languagesBaseDir . '/admin/en.json';
        $tmp = $loadJson($adminEn);
        if (is_array($tmp)) {
            $merged = $recursiveMerge($merged, $tmp);
        }

        // Load admin translations for user language (if not English)
        if (!empty($userLang) && strtolower($userLang) !== 'en') {
            $adminLangFile = $languagesBaseDir . "/admin/{$userLang}.json";
            $tmp = $loadJson($adminLangFile);
            if (is_array($tmp)) {
                $merged = $recursiveMerge($merged, $tmp);
            }
        }

        // Load module-specific English translations
        if (!empty($moduleName)) {
            $moduleEn = $languagesBaseDir . "/{$moduleName}/en.json";
            $tmp = $loadJson($moduleEn);
            if (is_array($tmp)) {
                $merged = $recursiveMerge($merged, $tmp);
            }
            
            // Load module-specific translations for user language
            if (!empty($userLang) && strtolower($userLang) !== 'en') {
                $moduleLang = $languagesBaseDir . "/{$moduleName}/{$userLang}.json";
                $tmp = $loadJson($moduleLang);
                if (is_array($tmp)) {
                    $merged = $recursiveMerge($merged, $tmp);
                }
            }
        }

        // Save to cache atomically
        try {
            $tmpCache = $cacheFile . '.tmp.' . getmypid() . '.' . uniqid();
            $writeResult = @file_put_contents($tmpCache, json_encode($merged, JSON_UNESCAPED_UNICODE));
            if ($writeResult !== false) {
                @rename($tmpCache, $cacheFile);
                @chmod($cacheFile, 0644);
                _admin_ui_log("translations cached to: {$cacheFile} (" . count($merged) . " keys)");
            } else {
                _admin_ui_log("failed to write translation cache: {$tmpCache}");
            }
        } catch (Throwable $e) {
            _admin_ui_log("translation cache write error: " . $e->getMessage());
        }
    }
    
    $ADMIN_UI_PAYLOAD['strings'] = is_array($merged) ? $merged : [];
    _admin_ui_log("translations loaded: " . count($ADMIN_UI_PAYLOAD['strings']) . " keys (cache hit: " . ($cacheHit ? 'yes' : 'no') . ")");
} else {
    _admin_ui_log("translations: languages directory not found. Candidates: " . implode(', ', array_filter($languagesCandidates, function($x) { return !empty($x); })));
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
        'user_info' => [
            'id' => $userInfo['id'] ?? null,
            'username' => $userInfo['username'] ?? null,
            'email' => $userInfo['email'] ?? null,
            'preferred_language' => $userInfo['preferred_language'] ?? null,
            'role_id' => $userInfo['role_id'] ?? null,
            'roles' => $userInfo['roles'] ?? [],
            'permissions' => $userInfo['permissions'] ?? [],
            'permissions_count' => count($userInfo['permissions'] ?? []),
            'roles_count' => count($userInfo['roles'] ?? []),
            'is_active' => $userInfo['is_active'] ?? false
        ],
        'session_permissions' => $_SESSION['permissions'] ?? [],
        'session_roles' => $_SESSION['roles'] ?? [],
        'theme' => $ADMIN_UI_PAYLOAD['theme'] ?? [],
        'payload_keys' => array_keys($ADMIN_UI_PAYLOAD)
    ];
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

return;
