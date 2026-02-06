<?php
declare(strict_types=1);
/**
 * routes/auth.php (improved)
 *
 * - Robust session handling: ensure session started, consistent cookie params
 * - session_regenerate_id(true) on successful login
 * - Sets Cache-Control: no-store on responses to avoid caching auth responses
 * - Supports JSON and form payloads
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Defensive session cookie params — adjust domain as needed
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    // session_name can be set centrally in bootstrap; set here only if not set
    if (session_name() === '') session_name('APP_SESSID');
    // some PHP versions accept array param
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
    }
    @session_start();
}

// helper to emit no-cache header for auth
function _no_cache(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

// Ensure DB
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    _no_cache();
    ResponseFormatter::serverError('Database unavailable');
    exit;
}

// Read dispatcher segments / action
$segments = $_GET['segments'] ?? [];
$firstSeg = strtolower($segments[0] ?? '');
$action = $firstSeg ?: (isset($_GET['__action']) ? strtolower($_GET['__action']) : '');

// read payload (JSON preferred)
function _read_payload(): array {
    $raw = @file_get_contents('php://input');
    if ($raw) {
        $d = @json_decode($raw, true);
        if (is_array($d)) return $d;
    }
    return $_POST ?: [];
}

// current user helper
function _current_user(): ?array {
    $u = $GLOBALS['ADMIN_USER'] ?? null;
    if (!$u && !empty($_SESSION['user'])) $u = $_SESSION['user'];
    return is_array($u) ? $u : null;
}

// RBAC loader (best-effort)
function _load_user_rbac(PDO $pdo, int $userId, ?int $roleId = null): array {
    $perms = []; $roles = [];
    try {
        // user_roles
        $st = $pdo->query("SHOW TABLES LIKE 'user_roles'");
        if ($st && $st->rowCount()) {
            $q = $pdo->prepare("SELECT r.key_name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?");
            $q->execute([$userId]);
            $r = $q->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($r) $roles = array_merge($roles, $r);
        } elseif ($roleId) {
            $q = $pdo->prepare("SELECT key_name FROM roles WHERE id = ? LIMIT 1");
            $q->execute([$roleId]);
            $r = $q->fetch(PDO::FETCH_COLUMN);
            if ($r) $roles[] = $r;
        }
        // user_permissions
        $st2 = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
        if ($st2 && $st2->rowCount()) {
            $q2 = $pdo->prepare("SELECT p.key_name FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?");
            $q2->execute([$userId]);
            $up = $q2->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($up) $perms = array_merge($perms, $up);
        }
        // role_permissions
        if ($roleId) {
            $q3 = $pdo->prepare("SELECT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?");
            $q3->execute([$roleId]);
            $rp = $q3->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp) $perms = array_merge($perms, $rp);
        } elseif (!empty($roles)) {
            $in = implode(',', array_fill(0, count($roles), '?'));
            $q4 = $pdo->prepare("SELECT DISTINCT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.key_name IN ($in)");
            $q4->execute($roles);
            $rp2 = $q4->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp2) $perms = array_merge($perms, $rp2);
        }
    } catch (Throwable $e) {
        if (class_exists('Logger')) Logger::error('RBAC load error: ' . $e->getMessage());
    }
    return ['permissions' => array_values(array_unique($perms)), 'roles' => array_values(array_unique($roles))];
}

// ---------------- GET actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    _no_cache();

    if ($action === 'logout') {
        // clear session
        unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['permissions'], $_SESSION['roles']);
        $GLOBALS['ADMIN_USER'] = null;
        // optionally destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_regenerate_id(true);
        ResponseFormatter::success(['ok' => true, 'message' => 'Logged out']);
        exit;
    }

    if ($action === 'me') {
        $u = _current_user();
        if (!$u) ResponseFormatter::notFound('Not authenticated');
        else ResponseFormatter::success(['ok' => true, 'user' => $u]);
        exit;
    }

    if ($action === 'csrf') {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        ResponseFormatter::success(['ok' => true, 'csrf' => $_SESSION['csrf_token']]);
        exit;
    }

    if ($action === 'check') {
        $u = _current_user();
        ResponseFormatter::success(['ok' => true, 'authenticated' => (bool)$u, 'user' => $u]);
        exit;
    }

    // default: accept check for GET /api/auth
    ResponseFormatter::error('Invalid GET action. Use: me, csrf, check or logout', 400);
    exit;
}

// ---------------- POST: login ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    _no_cache();
    if ($action !== '' && $action !== 'login') {
        ResponseFormatter::notFound('Auth POST route not found');
        exit;
    }

    $payload = _read_payload();
    $username = trim((string)($payload['username'] ?? $payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');

    if ($username === '' || $password === '') {
        ResponseFormatter::error('Missing credentials', 400);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            ResponseFormatter::error('Invalid credentials', 401);
            exit;
        }

        $hash = $row['password_hash'] ?? $row['password'] ?? $row['pass'] ?? null;
        $verified = false;
        if ($hash !== null) {
            if (function_exists('password_verify')) $verified = @password_verify($password, $hash);
            if (!$verified && $hash === $password) $verified = true; // dev fallback
        } else {
            ResponseFormatter::serverError('Password not found for user');
            exit;
        }

        if (!$verified) {
            ResponseFormatter::error('Invalid credentials', 401);
            exit;
        }

        if (isset($row['is_active']) && !$row['is_active']) {
            ResponseFormatter::error('Account disabled', 403);
            exit;
        }

        // regenerate session id to prevent fixation and ensure Set-Cookie
        session_regenerate_id(true);

        // set session + global user
        $user = [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'username' => $row['username'] ?? $row['email'] ?? null,
            'email' => $row['email'] ?? null,
            'role_id' => isset($row['role_id']) ? (int)$row['role_id'] : null,
            'preferred_language' => $row['preferred_language'] ?? null,
            'is_active' => !empty($row['is_active']),
        ];

        $rbac = _load_user_rbac($pdo, (int)$user['id'], $user['role_id'] ?? null);
        $user['permissions'] = $rbac['permissions'] ?? [];
        $user['roles'] = $rbac['roles'] ?? [];
        $user['permissions_count'] = count($user['permissions']);
        $user['roles_count'] = count($user['roles']);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['roles'] = $user['roles'];
        $GLOBALS['ADMIN_USER'] = $user;

        ResponseFormatter::success(['ok' => true, 'message' => 'Authenticated', 'user' => $user]);
        exit;

    } catch (Throwable $e) {
        if (class_exists('Logger')) Logger::error('Auth error: ' . $e->getMessage());
        ResponseFormatter::serverError(app_env('debug') ? $e->getMessage() : 'Authentication failed');
        exit;
    }
}

// fallback
ResponseFormatter::notFound('Auth route not supported');
exit;