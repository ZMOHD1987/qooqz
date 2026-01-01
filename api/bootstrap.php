<?php
/**
 * htdocs/api/bootstrap.php
 *
 * Improved unified bootstrap for the API and for pages that include it.
 * - Session, config loading
 * - DB connection (mysqli)
 * - unified error logging to api/error_log.txt
 * - safe includes of helpers/middleware/models (best-effort)
 * - current_user resolution (session or Bearer token)
 * - optional admin UI bootstrap include (bootstrap_admin_ui.php) when serving admin pages
 * - robust debug endpoint for quick inspection: add ?__bootstrap_debug=1
 *
 * Notes:
 * - Save as UTF-8 without BOM.
 * - This file is defensive: never throws uncaught exceptions; logs problems to api/error_log.txt.
 */

declare(strict_types=1);

// ---------- Basic paths & log ----------
$BASE_DIR = rtrim(__DIR__, '/\\');
$API_ERROR_LOG = $BASE_DIR . '/error_log.txt';

// ensure log exists (best-effort)
if (!file_exists($API_ERROR_LOG)) {
    @touch($API_ERROR_LOG);
}
@chmod($API_ERROR_LOG, 0664);

// simple logger (non-fatal)
if (!function_exists('api_log')) {
    function api_log(string $msg): void {
        global $API_ERROR_LOG;
        $line = "[" . date('c') . "] " . $msg . PHP_EOL;
        @file_put_contents($API_ERROR_LOG, $line, FILE_APPEND | LOCK_EX);
        @error_log($line);
    }
}

// ---------- environment flags ----------
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
if (!defined('DEBUG')) define('DEBUG', ENVIRONMENT === 'development');

@ini_set('display_errors', DEBUG ? '1' : '0');
@ini_set('display_startup_errors', DEBUG ? '1' : '0');
@ini_set('log_errors', '0'); // we log ourselves
error_reporting(E_ALL);

// ---------- normalize request when using index.php in URL ----------
if (!empty($_SERVER['REQUEST_URI']) && !empty($_SERVER['SCRIPT_NAME'])) {
    $scriptBasename = '/' . basename($_SERVER['SCRIPT_NAME']);
    if (strpos($_SERVER['REQUEST_URI'], $scriptBasename) !== false) {
        $_SERVER['REQUEST_URI'] = preg_replace('#' . preg_quote($scriptBasename, '#') . '#', '', $_SERVER['REQUEST_URI'], 1);
        if ($_SERVER['REQUEST_URI'] === '') $_SERVER['REQUEST_URI'] = '/';
    } else {
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($scriptDir !== '' && strpos($_SERVER['REQUEST_URI'], $scriptDir . '/') === 0) {
            $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($scriptDir));
            if ($_SERVER['REQUEST_URI'] === '') $_SERVER['REQUEST_URI'] = '/';
        }
    }
}
if (!isset($_SERVER['PATH_INFO'])) {
    $req = $_SERVER['REQUEST_URI'] ?? '/';
    $_SERVER['PATH_INFO'] = parse_url($req, PHP_URL_PATH) ?: '/';
}

// ---------- global exception & error handlers ----------
set_exception_handler(function (Throwable $e) use ($API_ERROR_LOG) {
    $msg = "[" . date('c') . "] EXCEPTION: " . $e->getMessage()
        . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL
        . $e->getTraceAsString() . PHP_EOL;
    @file_put_contents($API_ERROR_LOG, $msg, FILE_APPEND | LOCK_EX);
    if (function_exists('bootstrap_is_api_request') && bootstrap_is_api_request()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Server error'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo "Server error";
    }
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($API_ERROR_LOG) {
    $msg = "[" . date('c') . "] ERROR [$errno] $errstr in $errfile:$errline" . PHP_EOL;
    @file_put_contents($API_ERROR_LOG, $msg, FILE_APPEND | LOCK_EX);
    // convert to exception for API requests so exception handler returns JSON
    if (function_exists('bootstrap_is_api_request') && bootstrap_is_api_request()) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    // otherwise allow native handling (warnings/notices will not stop execution)
    return false;
});

// ---------- Start session safely ----------
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // session settings: cookie httponly, samesite Lax for compatibility
    try {
        @session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    } catch (Throwable $e) {
        api_log("session_start failed: " . $e->getMessage());
    }
}

// ---------- Helper: detect API request ----------
if (!function_exists('bootstrap_is_api_request')) {
    function bootstrap_is_api_request(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos((string)$uri, '/api/') === 0) return true;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
        if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
        return false;
    }
}

// ---------- Load config files (best-effort) ----------
$configFiles = [
    $BASE_DIR . '/config/config.php',
    $BASE_DIR . '/config/constants.php',
    $BASE_DIR . '/config/db.php',
    $BASE_DIR . '/config/cors.php'
];
foreach ($configFiles as $f) {
    if (is_readable($f)) {
        try {
            require_once $f;
        } catch (Throwable $e) {
            api_log("Config include failed: {$f} -> " . $e->getMessage());
        }
    } else {
        api_log("Config missing/not readable: {$f}");
    }
}

// ---------- CORS policy ----------
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

// ---------- Safe includes for helpers, middleware, models (best-effort) ----------
$includes = [
    '/helpers/response.php', '/helpers/jwt.php', '/helpers/security.php', '/helpers/upload.php',
    '/helpers/validator.php', '/helpers/utils.php', '/helpers/CSRF.php', '/helpers/RBAC.php',
    '/helpers/rbac_adapter.php', '/helpers/i18n.php',
    '/middleware/auth.php', '/middleware/role.php', '/middleware/rate_limit.php', '/middleware/TimezoneMiddleware.php',
    '/models/User.php', '/models/Vendor.php', '/models/Product.php', '/models/DeliveryCompanyModel.php',
];

foreach ($includes as $inc) {
    $path = $BASE_DIR . $inc;
    if (is_readable($path)) {
        try {
            require_once $path;
        } catch (Throwable $e) {
            api_log("Include error {$inc}: " . $e->getMessage());
        }
    } else {
        // not fatal; many helpers may be optional
        api_log("Optional include not found: {$inc}");
    }
}

// ---------- Utility helpers fallback ----------
if (!function_exists('json_response')) {
    function json_response($data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('json_error')) {
    function json_error(string $msg = 'Server error', int $code = 500): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('get_request_path')) {
    function get_request_path(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') ?: '/';
    }
}

if (!function_exists('get_bearer_token')) {
    function get_bearer_token(): ?string {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (!$h) return null;
        if (preg_match('/Bearer\s+(.*)$/i', $h, $m)) return trim($m[1]);
        return null;
    }
}

// ---------- Database connection (mysqli) ----------
$container = [];
try {
    // 1) allow custom connectDB defined in config
    if (function_exists('connectDB')) {
        try {
            $maybe = @connectDB();
            if ($maybe instanceof mysqli) {
                $container['db'] = $maybe;
            }
        } catch (Throwable $e) {
            api_log("connectDB() threw: " . $e->getMessage());
        }
    }

    // 2) fallback to globals if present
    if (empty($container['db'])) {
        foreach (['db', 'mysqli', 'conn'] as $g) {
            if (isset($GLOBALS[$g]) && $GLOBALS[$g] instanceof mysqli) {
                $container['db'] = $GLOBALS[$g];
                break;
            }
        }
    }

    // 3) final fallback to config/env
    if (empty($container['db'])) {
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: null);
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: null);
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: null);
        $name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: null);
        $port = defined('DB_PORT') ? (int)DB_PORT : (int)(getenv('DB_PORT') ?: 3306);
        if ($host && $user && $name) {
            $mysqli = @new mysqli($host, $user, $pass, $name, $port);
            if ($mysqli && !$mysqli->connect_errno) {
                $container['db'] = $mysqli;
                @$mysqli->set_charset('utf8mb4');
            } else {
                api_log("DB connect error: " . ($mysqli->connect_error ?? 'unknown'));
            }
        } else {
            api_log("DB credentials not provided in config or environment.");
        }
    }
} catch (Throwable $e) {
    api_log("DB exception: " . $e->getMessage());
}

// expose container globally (helper)
$GLOBALS['CONTAINER'] = $container;

// ---------- current_user resolution (session OR Bearer token) ----------
$container['current_user'] = null;
try {
    // 1) session user object
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        $container['current_user'] = $_SESSION['user'];
    } elseif (!empty($_SESSION['user_id']) && !empty($container['db'])) {
        $uid = (int)$_SESSION['user_id'];
        $stmt = $container['db']->prepare("SELECT id, username, email, role_id, preferred_language, is_active FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $container['current_user'] = [
                    'id' => (int)$row['id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'role_id' => isset($row['role_id']) ? (int)$row['role_id'] : null,
                    'preferred_language' => $row['preferred_language'] ?? null,
                    'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
                    'auth_via' => 'session'
                ];
            }
            $stmt->close();
        }
    } else {
        // 2) bearer token
        $token = get_bearer_token();
        if ($token && !empty($container['db'])) {
            $db = $container['db'];
            $sql = "SELECT t.id AS token_id, t.user_id, t.is_active AS token_active, u.id AS uid, u.username, u.email, u.role_id, u.preferred_language, u.is_active AS user_active
                    FROM api_tokens t
                    JOIN users u ON u.id = t.user_id
                    WHERE t.token = ? LIMIT 1";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if ($row && (int)$row['token_active'] === 1 && (int)$row['user_active'] === 1) {
                    $container['current_user'] = [
                        'id' => (int)$row['uid'],
                        'username' => $row['username'],
                        'email' => $row['email'],
                        'role_id' => isset($row['role_id']) ? (int)$row['role_id'] : null,
                        'preferred_language' => $row['preferred_language'] ?? null,
                        'auth_via' => 'token',
                        'token_id' => (int)$row['token_id']
                    ];
                    // update last_used_at (best-effort, non-blocking)
                    try {
                        $upd = $db->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?");
                        if ($upd) {
                            $upd->bind_param('i', $row['token_id']);
                            $upd->execute();
                            $upd->close();
                        }
                    } catch (Throwable $e) {
                        api_log("Failed to update api_tokens.last_used_at: " . $e->getMessage());
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    api_log("current_user resolution error: " . $e->getMessage());
}

// Expose current user & DB to legacy code expecting $GLOBALS['ADMIN_USER'] / ADMIN_DB
$GLOBALS['CONTAINER'] = $container;
$GLOBALS['ADMIN_DB'] = $container['db'] ?? null;
$GLOBALS['ADMIN_USER'] = $container['current_user'] ?? null;

// ---------- If serving admin pages, try to include bootstrap_admin_ui.php to prepare $ADMIN_UI_PAYLOAD ----------
try {
    $isAdminRequest = false;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (stripos((string)$uri, '/admin') === 0 || stripos((string)$uri, '/admin/') === 0) {
        $isAdminRequest = true;
    }
    // Also allow override by GET param for debug/testing
    if (isset($_GET['__force_admin_bootstrap']) && $_GET['__force_admin_bootstrap'] == '1') $isAdminRequest = true;

    if ($isAdminRequest) {
        $adminBootstrap = $BASE_DIR . '/bootstrap_admin_ui.php';
        if (is_readable($adminBootstrap)) {
            // include safely (capture output) and ensure no fatal bubbles up
            try {
                ob_start();
                require_once $adminBootstrap;
                @ob_end_clean();
                api_log("Included bootstrap_admin_ui.php for admin request.");
            } catch (Throwable $e) {
                @ob_end_clean();
                api_log("bootstrap_admin_ui include failed: " . $e->getMessage());
            }
        } else {
            api_log("bootstrap_admin_ui.php not found or unreadable at: {$adminBootstrap}");
        }
    }
} catch (Throwable $e) {
    api_log("admin bootstrap attempt failed: " . $e->getMessage());
}

// ---------- require_auth helper fallback ----------
if (!function_exists('require_auth')) {
    function require_auth(array &$container): void {
        if (!empty($container['current_user']) && is_array($container['current_user'])) return;
        json_error('Unauthorized', 401);
    }
}

// ---------- expose container() helper ----------
if (!function_exists('container')) {
    function container($key = null) {
        global $container;
        return $key === null ? $container : ($container[$key] ?? null);
    }
}

// ---------- debug endpoint (safe) ----------
if (!empty($_GET['__bootstrap_debug']) && $_GET['__bootstrap_debug'] == '1') {
    $out = [
        'ok' => true,
        'env' => ENVIRONMENT,
        'debug' => DEBUG,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'is_api_request' => bootstrap_is_api_request(),
        'db_connected' => !empty($container['db']) && $container['db'] instanceof mysqli,
        'current_user' => $container['current_user'] ?? null,
        'admin_ui_payload_exists' => isset($ADMIN_UI_PAYLOAD) || (isset($GLOBALS['ADMIN_UI']) && is_array($GLOBALS['ADMIN_UI'])) ? true : false,
        'admin_ui_sample' => isset($ADMIN_UI_PAYLOAD) ? (is_array($ADMIN_UI_PAYLOAD) ? array_slice($ADMIN_UI_PAYLOAD, 0, 10, true) : null) : (isset($GLOBALS['ADMIN_UI']) ? (is_array($GLOBALS['ADMIN_UI']) ? array_slice($GLOBALS['ADMIN_UI'], 0, 10, true) : null) : null),
        'log_file' => $API_ERROR_LOG,
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ---------- log bootstrap load ----------
try {
    api_log("bootstrap loaded | current_user: " . json_encode($container['current_user'] ?? null, JSON_UNESCAPED_UNICODE));
} catch (Throwable $e) {
    // ignore logging failure
}

// ---------- Routes dispatch (if present) ----------
$routesFile = $BASE_DIR . '/routes.php';
if (is_readable($routesFile)) {
    try {
        require_once $routesFile;
    } catch (Throwable $e) {
        api_log("routes.php include failed: " . $e->getMessage());
        json_error('Server error', 500);
    }
} else {
    // Minimal built-in endpoints
    $path = get_request_path();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET' && ($path === '/api/health' || $path === '/health' || $path === '/api')) {
        $dbOk = false;
        try {
            if (!empty($container['db']) && $container['db'] instanceof mysqli) {
                $res = @$container['db']->query('SELECT 1');
                $dbOk = $res !== false;
            }
        } catch (Throwable $e) {
            $dbOk = false;
        }
        json_response(['status' => 'ok', 'db' => $dbOk, 'time' => date('c')]);
    }

    if ($method === 'GET' && ($path === '/' || $path === '/api' || $path === '/api/')) {
        json_response(['message' => 'API bootstrap loaded. Routes not found. Use index.php or add routes.php.']);
    }

    // Not found
    json_error('Not Found', 404);
}
