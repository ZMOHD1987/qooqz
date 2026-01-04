<?php
declare(strict_types=1);
/**
 * htdocs/api/bootstrap.php
 *
 * Unified defensive bootstrap for the API and admin pages.
 * - Session, config loading
 * - DB connection (mysqli) with many fallbacks
 * - safe include helper and best-effort includes for common helpers/middleware/models
 * - current_user resolution (session OR Bearer token OR session_token cookie)
 * - roles & permissions population using auth_helper (refresh_permissions_from_db) when available
 * - admin UI bootstrap include (bootstrap_admin_ui.php) when serving admin pages
 * - debug endpoint: ?__bootstrap_debug=1
 *
 * Compatibility: PHP 7.2+ (no typed properties). Save as UTF-8 without BOM.
 *
 * NOTE: This file expects api/helpers/auth_helper.php to exist (we try to include it).
 * If it exists it provides get_db_connection(), refresh_permissions_from_db(), auth_get_user_permissions(), auth_get_user_roles(), get_authenticated_user_with_permissions().
 */

$BASE_DIR = rtrim(__DIR__, '/\\');
$API_ERROR_LOG = $BASE_DIR . '/error_log.txt';
if (!file_exists($API_ERROR_LOG)) @touch($API_ERROR_LOG);
@chmod($API_ERROR_LOG, 0664);

if (!function_exists('api_log')) {
    function api_log($msg)
    {
        global $API_ERROR_LOG;
        $line = "[" . date('c') . "] " . (is_string($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
        @file_put_contents($API_ERROR_LOG, $line, FILE_APPEND | LOCK_EX);
        @error_log($line);
    }
}

// environment flags
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
if (!defined('DEBUG')) define('DEBUG', ENVIRONMENT === 'development');
@ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);

// start session safely
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    try { @session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']); }
    catch (Throwable $e) { api_log("session_start failed: " . $e->getMessage()); }
}

// helper: detect API request
if (!function_exists('bootstrap_is_api_request')) {
    function bootstrap_is_api_request(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos((string)$uri, '/api/') === 0) return true;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
        if (!empty($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
        return false;
    }
}

// safe include utility
if (!function_exists('safe_include')) {
    function safe_include($candidates) {
        global $BASE_DIR;
        if (is_string($candidates)) $candidates = [$candidates];
        foreach ($candidates as $rel) {
            $path = $BASE_DIR . '/' . ltrim($rel, '/\\');
            if (is_readable($path)) {
                try { require_once $path; api_log("Included: {$rel}"); return $path; }
                catch (Throwable $e) { api_log("Include threw for {$rel}: " . $e->getMessage()); return false; }
            }
        }
        api_log("Optional include not found: " . implode(' | ', (array)$candidates));
        return false;
    }
}

// load config files (best-effort)
$configFiles = [
    $BASE_DIR . '/config/config.php',
    $BASE_DIR . '/config/constants.php',
    $BASE_DIR . '/config/db.php',
    $BASE_DIR . '/config/cors.php',
];
foreach ($configFiles as $f) {
    if (is_readable($f)) {
        try { require_once $f; } catch (Throwable $e) { api_log("Config include failed: {$f} -> " . $e->getMessage()); }
    }
}

// permissive CORS (best-effort)
if (!function_exists('apply_cors_policy')) {
    function apply_cors_policy(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = $GLOBALS['ALLOWED_ORIGINS'] ?? [];
        if ($origin && is_array($allowed) && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        } elseif (defined('DEBUG') && DEBUG) {
            header('Access-Control-Allow-Origin: *');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
    }
}
apply_cors_policy();

// include commonly used helpers (explicitly auth_helper first)
$safe_helpers = [
    array('/helpers/auth_helper.php', '/api/helpers/auth_helper.php', '/helpers/auth.php'),
    array('/helpers/response.php', '/helpers/response_helper.php'),
    array('/helpers/jwt.php', '/helpers/jwt_helper.php'),
    array('/helpers/security.php'),
    array('/helpers/upload.php', '/helpers/file_upload.php'),
    array('/helpers/validator.php', '/middleware/validator.php'),
    array('/helpers/rbac.php', '/helpers/rbac_adapter.php'),
    array('/helpers/i18n.php', '/helpers/lang.php'),
    array('/helpers/utils.php', '/helpers/helpers.php'),
    array('/helpers/CSRF.php', '/helpers/csrf.php'),
    array('/middleware/auth.php'),
    array('/middleware/role.php'),
    array('/middleware/rate_limit.php'),
    array('/models/User.php', '/models/user.php'),
    array('/models/Product.php', '/models/product.php')
];
foreach ($safe_helpers as $group) safe_include($group);

// ---------- DB connection resolution ----------
$container = $GLOBALS['CONTAINER'] ?? [];
try {
    $db = null;
    // 1) auth helper's get_db_connection()
    if (function_exists('get_db_connection')) {
        try {
            $maybe = @get_db_connection();
            if ($maybe instanceof mysqli) $db = $maybe;
        } catch (Throwable $e) { api_log("get_db_connection() threw: " . $e->getMessage()); }
    }
    // 2) connectDB() (if returns mysqli)
    if (!$db && function_exists('connectDB')) {
        try {
            $maybe = @connectDB();
            if ($maybe instanceof mysqli) $db = $maybe;
        } catch (Throwable $e) { api_log("connectDB() threw: " . $e->getMessage()); }
    }
    // 3) globals fallback
    if (!$db) {
        foreach (array('CONTAINER','db','mysqli','conn') as $g) {
            if (!empty($GLOBALS[$g])) {
                $val = $GLOBALS[$g];
                if ($val instanceof mysqli) { $db = $val; break; }
                if (is_array($val) && !empty($val['db']) && $val['db'] instanceof mysqli) { $db = $val['db']; break; }
            }
        }
    }
    // 4) constants/env fallback
    if (!$db) {
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: null);
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: null);
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: null);
        $name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: null);
        $port = defined('DB_PORT') ? (int)DB_PORT : (int)(getenv('DB_PORT') ?: 3306);
        if ($host && $user && $name) {
            $m = @new mysqli($host, $user, $pass, $name, $port);
            if ($m && !$m->connect_errno) { $db = $m; @$db->set_charset('utf8mb4'); }
            else api_log("DB connect error: " . ($m->connect_error ?? 'unknown'));
        } else api_log("DB credentials not provided in constants/env");
    }

    if ($db instanceof mysqli) $container['db'] = $db;
} catch (Throwable $e) {
    api_log("DB detection exception: " . $e->getMessage());
}
$GLOBALS['CONTAINER'] = $container;

// ---------- Helper functions used below (defensive) ----------
if (!function_exists('get_bearer_token')) {
    function get_bearer_token() {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (!$h) return null;
        if (preg_match('/Bearer\s+(.*)$/i', $h, $m)) return trim($m[1]);
        return null;
    }
}
if (!function_exists('table_exists')) {
    function table_exists(mysqli $db, string $table): bool {
        try {
            $t = $db->real_escape_string($table);
            $r = $db->query("SHOW TABLES LIKE '{$t}'");
            if ($r) { $ok = $r->num_rows > 0; $r->free(); return $ok; }
        } catch (Throwable $e) { api_log("table_exists error: " . $e->getMessage()); }
        return false;
    }
}
if (!function_exists('column_exists')) {
    function column_exists(mysqli $db, string $t, string $c): bool {
        try {
            $st = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
            if (!$st) return false;
            $st->bind_param('ss', $t, $c); $st->execute();
            if (method_exists($st,'get_result')) {
                $res = $st->get_result(); $ok = (bool)($res && $res->fetch_assoc());
            } else {
                $meta = $st->result_metadata(); $ok = (bool)$meta; if ($meta) $meta->free();
            }
            $st->close();
            return (bool)$ok;
        } catch (Throwable $e) { api_log("column_exists error: " . $e->getMessage()); return false; }
    }
}

// ---------- Resolve current_user ----------
$container['current_user'] = $container['current_user'] ?? null;
try {
    // 1) Prefer session snapshot
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        $container['current_user'] = $_SESSION['user'];
        api_log("User from session snapshot: " . json_encode(['id'=>$container['current_user']['id'] ?? null]));
    }

    // 2) If no session snapshot, try session user_id -> users table
    if (empty($container['current_user']) && !empty($_SESSION['user_id']) && !empty($container['db']) && $container['db'] instanceof mysqli) {
        $uid = (int)$_SESSION['user_id'];
        $db = $container['db'];
        $stmt = $db->prepare("SELECT id, username, email, role_id, preferred_language, is_active FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            if ($stmt->execute()) {
                if (method_exists($stmt,'get_result')) {
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                } else {
                    $meta = $stmt->result_metadata();
                    $row = null;
                    if ($meta) {
                        $fields = $meta->fetch_fields(); $meta->free();
                        $bind = []; $r = [];
                        foreach ($fields as $f) $bind[] = &$r[$f->name];
                        call_user_func_array([$stmt,'bind_result'],$bind);
                        if ($stmt->fetch()) $row = $r;
                    }
                }
                if (!empty($row)) {
                    $container['current_user'] = [
                        'id' => (int)$row['id'],
                        'username' => $row['username'] ?? null,
                        'email' => $row['email'] ?? null,
                        'role_id' => isset($row['role_id']) ? (int)$row['role_id'] : null,
                        'preferred_language' => $row['preferred_language'] ?? null,
                        'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
                        'auth_via' => 'session'
                    ];
                    api_log("User from DB by session user_id: " . json_encode($container['current_user']));
                }
            }
            $stmt->close();
        }
    }

    // 3) session_token cookie lookup (common)
    if (empty($container['current_user']) && !empty($_COOKIE['session_token']) && !empty($container['db']) && $container['db'] instanceof mysqli) {
        $db = $container['db'];
        $raw = (string)$_COOKIE['session_token'];
        $hash = hash('sha256', $raw);
        $sessionTables = ['user_sessions','sessions','session_tokens','auth_sessions','user_tokens'];
        $tokenCols = ['token','session_token','token_hash','hash','value'];
        $foundUid = null;
        foreach ($sessionTables as $tbl) {
            if (!table_exists($db,$tbl)) continue;
            foreach ($tokenCols as $col) {
                if (!column_exists($db,$tbl,$col)) continue;
                try {
                    $st = $db->prepare("SELECT user_id FROM `{$tbl}` WHERE `{$col}` = ? AND (revoked = 0 OR revoked IS NULL) AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
                    if (!$st) continue;
                    $st->bind_param('s', $raw);
                    $st->execute();
                    $res = $st->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $st->close();
                    if (!empty($row['user_id'])) { $foundUid = (int)$row['user_id']; break 2; }
                } catch (Throwable $e) { api_log("session lookup failed ({$tbl}.{$col}): " . $e->getMessage()); }
            }
            // try hashed
            foreach ($tokenCols as $col) {
                if (!column_exists($db,$tbl,$col)) continue;
                try {
                    $st = $db->prepare("SELECT user_id FROM `{$tbl}` WHERE `{$col}` = ? LIMIT 1");
                    if (!$st) continue;
                    $st->bind_param('s', $hash);
                    $st->execute();
                    $res = $st->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $st->close();
                    if (!empty($row['user_id'])) { $foundUid = (int)$row['user_id']; break 2; }
                } catch (Throwable $e) { api_log("session hashed lookup failed ({$tbl}.{$col}): " . $e->getMessage()); }
            }
        }
        if (!empty($foundUid)) {
            $s = $db->prepare("SELECT id, username, email, role_id, preferred_language, is_active FROM users WHERE id = ? LIMIT 1");
            if ($s) {
                $s->bind_param('i', $foundUid);
                if ($s->execute()) {
                    if (method_exists($s,'get_result')) {
                        $res = $s->get_result();
                        $u = $res ? $res->fetch_assoc() : null;
                    } else {
                        $meta = $s->result_metadata(); $u = null;
                        if ($meta) {
                            $fields = $meta->fetch_fields(); $meta->free();
                            $bind = []; $r = [];
                            foreach ($fields as $f) $bind[] = &$r[$f->name];
                            call_user_func_array([$s,'bind_result'],$bind);
                            if ($s->fetch()) $u = $r;
                        }
                    }
                    if ($u) {
                        $container['current_user'] = [
                            'id' => (int)$u['id'],
                            'username' => $u['username'] ?? null,
                            'email' => $u['email'] ?? null,
                            'role_id' => isset($u['role_id']) ? (int)$u['role_id'] : null,
                            'preferred_language' => $u['preferred_language'] ?? null,
                            'is_active' => isset($u['is_active']) ? (bool)$u['is_active'] : true,
                            'auth_via' => 'session_token'
                        ];
                        api_log("User from session_token table: id={$u['id']}");
                    }
                }
                $s->close();
            }
        }
    }

    // 4) bearer token via api_tokens
    if (empty($container['current_user'])) {
        $token = get_bearer_token();
        if ($token && !empty($container['db']) && $container['db'] instanceof mysqli) {
            $db = $container['db'];
            $st = $db->prepare("SELECT t.user_id, u.id AS uid, u.username, u.email, u.role_id, u.preferred_language, u.is_active FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token = ? LIMIT 1");
            if ($st) {
                $st->bind_param('s', $token);
                if ($st->execute()) {
                    if (method_exists($st,'get_result')) {
                        $res = $st->get_result();
                        $r = $res ? $res->fetch_assoc() : null;
                    } else {
                        $meta = $st->result_metadata(); $r = null;
                        if ($meta) {
                            $fields = $meta->fetch_fields(); $meta->free();
                            $bind = []; $rr = [];
                            foreach ($fields as $f) $bind[] = &$rr[$f->name];
                            call_user_func_array([$st,'bind_result'],$bind);
                            if ($st->fetch()) $r = $rr;
                        }
                    }
                    if ($r && !empty($r['user_id'])) {
                        $container['current_user'] = [
                            'id' => (int)$r['uid'],
                            'username' => $r['username'] ?? null,
                            'email' => $r['email'] ?? null,
                            'role_id' => isset($r['role_id']) ? (int)$r['role_id'] : null,
                            'preferred_language' => $r['preferred_language'] ?? null,
                            'is_active' => isset($r['is_active']) ? (bool)$r['is_active'] : true,
                            'auth_via' => 'token'
                        ];
                        api_log("User from api_tokens: id={$r['uid']}");
                    }
                }
                $st->close();
            }
        }
    }
} catch (Throwable $e) {
    api_log("current_user resolution error: " . $e->getMessage());
}

// ---------- Populate permissions & roles using auth_helper (schema-aware) ----------
try {
    if (!empty($container['current_user'])) {
        $uid = (int)$container['current_user']['id'];
        $db = $container['db'] ?? null;

        // 1) If auth helper has refresh_permissions_from_db, call it (will discover columns like key_name)
        if ($db instanceof mysqli && function_exists('refresh_permissions_from_db')) {
            try {
                refresh_permissions_from_db($db, $uid);
                api_log("refresh_permissions_from_db executed for uid={$uid}");
            } catch (Throwable $e) {
                api_log("refresh_permissions_from_db error: " . $e->getMessage());
            }
        }

        // 2) Prefer explicit helper getters if present
        if (function_exists('auth_get_user_permissions')) {
            try { $_SESSION['permissions'] = (array)auth_get_user_permissions($uid); } catch (Throwable $e) { api_log("auth_get_user_permissions failed: " . $e->getMessage()); }
        }
        if (function_exists('auth_get_user_roles')) {
            try { $_SESSION['roles'] = (array)auth_get_user_roles($uid); } catch (Throwable $e) { api_log("auth_get_user_roles failed: " . $e->getMessage()); }
        }

        // Ensure arrays exist
        $_SESSION['permissions'] = !empty($_SESSION['permissions']) && is_array($_SESSION['permissions']) ? array_values(array_unique($_SESSION['permissions'])) : [];
        $_SESSION['roles'] = !empty($_SESSION['roles']) && is_array($_SESSION['roles']) ? array_values(array_unique($_SESSION['roles'])) : [];

        // 3) Fallback: if still empty and role_id == 1 grant temporary admin perms to avoid lockout
        $roleId = isset($container['current_user']['role_id']) ? (int)$container['current_user']['role_id'] : null;
        if (empty($_SESSION['permissions']) && $roleId === 1) {
            $_SESSION['permissions'] = ['super_admin','manage_users','manage_settings','manage_banners','manage_products','manage_orders'];
            $_SESSION['roles'] = $_SESSION['roles'] ?: ['admin'];
            api_log("TEMP: granted default admin perms to role_id=1 (uid={$uid})");
        }

        // 4) Persist to container & globals
        $container['current_user']['permissions'] = $_SESSION['permissions'];
        $container['current_user']['roles'] = $_SESSION['roles'];
        $GLOBALS['ADMIN_USER'] = isset($GLOBALS['ADMIN_USER']) && is_array($GLOBALS['ADMIN_USER']) ? $GLOBALS['ADMIN_USER'] : [];
        $GLOBALS['ADMIN_USER'] = array_merge($GLOBALS['ADMIN_USER'], $container['current_user']);

        api_log("Permissions for uid={$uid}: " . (empty($_SESSION['permissions']) ? 'NONE' : implode(',', $_SESSION['permissions'])));
        api_log("Roles for uid={$uid}: " . (empty($_SESSION['roles']) ? 'NONE' : implode(',', $_SESSION['roles'])));
    }
} catch (Throwable $e) {
    api_log("permissions population error: " . $e->getMessage());
}

// expose container & admin globals
$GLOBALS['CONTAINER'] = $container;
$GLOBALS['ADMIN_DB'] = $container['db'] ?? null;
$GLOBALS['ADMIN_USER'] = $container['current_user'] ?? null;

// include admin UI bootstrap if request seems admin-like
try {
    $isAdminRequest = false;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (stripos((string)$uri, '/admin') === 0 || stripos((string)$uri, '/admin/') === 0) $isAdminRequest = true;
    if (!empty($_GET['__force_admin_bootstrap']) && $_GET['__force_admin_bootstrap'] == '1') $isAdminRequest = true;
    if ($isAdminRequest) {
        $adminBootstrap = $BASE_DIR . '/api/bootstrap_admin_ui.php';
        if (is_readable($adminBootstrap)) {
            try { ob_start(); require_once $adminBootstrap; @ob_end_clean(); api_log("Included bootstrap_admin_ui.php"); }
            catch (Throwable $e) { @ob_end_clean(); api_log("bootstrap_admin_ui include failed: " . $e->getMessage()); }
        } else {
            api_log("bootstrap_admin_ui.php not found at: {$adminBootstrap}");
        }
    }
} catch (Throwable $e) {
    api_log("admin bootstrap attempt failed: " . $e->getMessage());
}

// debug endpoint
if (!empty($_GET['__bootstrap_debug']) && $_GET['__bootstrap_debug'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'env' => ENVIRONMENT,
        'debug' => DEBUG,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'db_connected' => !empty($container['db']) && $container['db'] instanceof mysqli,
        'current_user' => $container['current_user'] ?? null,
        'session_permissions' => $_SESSION['permissions'] ?? null,
        'session_roles' => $_SESSION['roles'] ?? null,
        'log_file' => $API_ERROR_LOG
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// final log
try { api_log("bootstrap loaded | current_user: " . json_encode($container['current_user'] ?? null, JSON_UNESCAPED_UNICODE)); } catch (Throwable $e) {}
