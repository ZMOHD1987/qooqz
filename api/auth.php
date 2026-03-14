<?php
declare(strict_types=1);

/**
 * htdocs/api/auth.php
 *
 * Direct-access auth endpoint consumed by admin/assets/js/login.js
 * at fetch('/api/auth', ...).
 *
 * Path note: this file lives at htdocs/api/auth.php.
 *   $baseDir = __DIR__   → points to the api/ directory.
 *   dirname(__DIR__, 2)  would go TWO levels above api/, which is wrong here.
 * All shared includes use $baseDir directly.
 */

// ── Security / output ────────────────────────────────────────────────────────
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ── Path root ────────────────────────────────────────────────────────────────
// Correct base for this file: the api/ directory itself.
// Do NOT use dirname(__DIR__, 2) — that would escape above the project root.
$baseDir = __DIR__;

// ── Session — match APP_SESSID used by bootstrap_admin_ui.php ───────────────
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== 'APP_SESSID') {
        session_name('APP_SESSID');
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $_SERVER['HTTP_HOST'] ?? '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', $_SERVER['HTTP_HOST'] ?? '', $secure, true);
    }
    session_start(['use_strict_mode' => true]);
}

// ── JSON helper ──────────────────────────────────────────────────────────────
function _auth_json(bool $success, string $message, array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// ── Database connection ──────────────────────────────────────────────────────
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

if (!$pdo instanceof PDO) {
    // Try shared config — path relative to this file's directory (api/).
    $dbCfgFile = $baseDir . '/shared/config/db.php';
    if (!$pdo instanceof PDO && is_file($dbCfgFile)) {
        $cfg = include $dbCfgFile;
        if (is_array($cfg)) {
            try {
                $host    = $cfg['host']     ?? ($cfg['DB_HOST'] ?? 'localhost');
                $dbname  = $cfg['name']     ?? ($cfg['dbname']  ?? ($cfg['DB_NAME'] ?? ''));
                $user    = $cfg['username'] ?? ($cfg['user']    ?? ($cfg['DB_USER'] ?? ''));
                $pass    = $cfg['password'] ?? ($cfg['pass']    ?? ($cfg['DB_PASS'] ?? ''));
                $charset = $cfg['charset']  ?? 'utf8mb4';
                $dsn     = "mysql:host={$host};dbname={$dbname};charset={$charset}";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                $GLOBALS['ADMIN_DB'] = $pdo;
            } catch (Throwable $e) {
                error_log('[api/auth.php] DB connect failed: ' . $e->getMessage());
            }
        }
    }
    // Fallback: DatabaseConnection class
    if (!$pdo instanceof PDO) {
        $dcFile = $baseDir . '/shared/core/DatabaseConnection.php';
        if (is_file($dcFile) && !class_exists('DatabaseConnection')) {
            @require_once $dcFile;
        }
        if (class_exists('DatabaseConnection')) {
            try {
                $maybe = DatabaseConnection::getConnection();
                if ($maybe instanceof PDO) {
                    $pdo = $maybe;
                    $GLOBALS['ADMIN_DB'] = $pdo;
                }
            } catch (Throwable $e) {
                error_log('[api/auth.php] DatabaseConnection failed: ' . $e->getMessage());
            }
        }
    }
}

if (!$pdo instanceof PDO) {
    _auth_json(false, 'Database unavailable', [], 503);
}

// ── Request method ───────────────────────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ── Payload reader ───────────────────────────────────────────────────────────
function _auth_payload(): array
{
    $raw = (string)@file_get_contents('php://input');
    if ($raw !== '') {
        $d = @json_decode($raw, true);
        if (is_array($d)) {
            return $d;
        }
    }
    return !empty($_POST) ? $_POST : [];
}

// ── Load RBAC (best-effort) ──────────────────────────────────────────────────
function _auth_rbac(PDO $pdo, int $userId, ?int $roleId): array
{
    $perms = [];
    $roles = [];
    try {
        // Check user_roles junction table first (many-to-many)
        $st = $pdo->query("SHOW TABLES LIKE 'user_roles'");
        if ($st && $st->rowCount()) {
            $q = $pdo->prepare(
                "SELECT r.key_name FROM roles r
                 JOIN user_roles ur ON ur.role_id = r.id
                 WHERE ur.user_id = ?"
            );
            $q->execute([$userId]);
            $r = $q->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($r) {
                $roles = array_merge($roles, $r);
            }
        } elseif ($roleId) {
            $q = $pdo->prepare("SELECT key_name FROM roles WHERE id = ? LIMIT 1");
            $q->execute([$roleId]);
            $r = $q->fetchColumn();
            if ($r) {
                $roles[] = $r;
            }
        }
        // Check user_permissions junction table
        $st2 = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
        if ($st2 && $st2->rowCount()) {
            $q2 = $pdo->prepare(
                "SELECT p.key_name FROM permissions p
                 JOIN user_permissions up ON up.permission_id = p.id
                 WHERE up.user_id = ?"
            );
            $q2->execute([$userId]);
            $up = $q2->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($up) {
                $perms = array_merge($perms, $up);
            }
        }
        // Role permissions
        if ($roleId) {
            $q3 = $pdo->prepare(
                "SELECT p.key_name FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = ?"
            );
            $q3->execute([$roleId]);
            $rp = $q3->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp) {
                $perms = array_merge($perms, $rp);
            }
        } elseif (!empty($roles)) {
            $safeRoles = array_values(array_map('strval', $roles));
            $in = implode(',', array_fill(0, count($safeRoles), '?'));
            $q4 = $pdo->prepare(
                "SELECT DISTINCT p.key_name FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN roles r ON r.id = rp.role_id
                 WHERE r.key_name IN ($in)"
            );
            $q4->execute($safeRoles);
            $rp2 = $q4->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp2) {
                $perms = array_merge($perms, $rp2);
            }
        }
    } catch (Throwable $e) {
        error_log('[api/auth.php] RBAC error: ' . $e->getMessage());
    }
    return [
        'roles'       => array_values(array_unique($roles)),
        'permissions' => array_values(array_unique($perms)),
    ];
}

// ════════════════════════════════════════════════════════════════════════════
// GET — check / csrf / logout / me
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'GET') {
    $action = strtolower(trim($_GET['action'] ?? ''));

    if ($action === 'logout') {
        unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['permissions'], $_SESSION['roles']);
        $GLOBALS['ADMIN_USER'] = null;
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_regenerate_id(true);
        _auth_json(true, 'Logged out');
    }

    if ($action === 'me' || $action === 'check') {
        $u = $_SESSION['user'] ?? null;
        _auth_json(true, 'ok', ['authenticated' => (bool)$u, 'user' => $u]);
    }

    if ($action === 'csrf') {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        }
        _auth_json(true, 'ok', ['csrf' => $_SESSION['csrf_token']]);
    }

    // Default GET: return session status
    $u = $_SESSION['user'] ?? null;
    _auth_json(true, 'ok', ['authenticated' => (bool)$u, 'user' => $u]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST — login / register
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST') {
    $payload = _auth_payload();
    $action  = strtolower(trim((string)($payload['action'] ?? $_GET['action'] ?? 'login')));

    // ── REGISTER ─────────────────────────────────────────────────────────────
    if ($action === 'register') {
        $username = trim((string)($payload['username'] ?? ''));
        $email    = trim((string)($payload['email']    ?? ''));
        $password = (string)($payload['password']      ?? '');
        $phone    = trim((string)($payload['phone']    ?? ''));
        $lang     = preg_replace('/[^a-z\-]/', '', strtolower((string)($payload['preferred_language'] ?? 'en')));

        $errors = [];
        if ($username === '') {
            $errors['username'] = 'Username is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $errors['username'] = 'Username must be 3-50 alphanumeric characters or underscores';
        }
        if ($email === '') {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }

        if ($errors) {
            _auth_json(false, 'Validation failed', ['errors' => $errors], 422);
        }

        try {
            $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                _auth_json(false, 'Username or email already exists', [], 409);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, phone, preferred_language, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())'
            );
            $ins->execute([$username, $email, $hash, $phone ?: null, $lang ?: 'en']);
            $newId = (int)$pdo->lastInsertId();

            session_regenerate_id(true);
            $user = [
                'id'                 => $newId,
                'username'           => $username,
                'email'              => $email,
                'role_id'            => null,
                'tenant_id'          => 1,
                'preferred_language' => $lang ?: 'en',
                'is_active'          => true,
                'roles'              => [],
                'permissions'        => [],
            ];
            $_SESSION['user_id']     = $newId;
            $_SESSION['user']        = $user;
            $_SESSION['permissions'] = [];
            $_SESSION['roles']       = [];
            $GLOBALS['ADMIN_USER']   = $user;

            _auth_json(true, 'Registration successful', ['user' => $user]);
        } catch (Throwable $e) {
            error_log('[api/auth.php] Register error: ' . $e->getMessage());
            _auth_json(false, 'Registration failed', [], 500);
        }
    }

    // ── LOGIN ─────────────────────────────────────────────────────────────────
    $identifier = trim((string)($payload['username'] ?? $payload['email'] ?? $payload['identifier'] ?? ''));
    $password   = (string)($payload['password'] ?? '');

    if ($identifier === '' || $password === '') {
        _auth_json(false, 'Missing credentials', [], 400);
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1"
        );
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            _auth_json(false, 'Invalid credentials', [], 401);
        }

        $hash     = $row['password_hash'] ?? $row['password'] ?? null;
        $verified = ($hash !== null) && password_verify($password, $hash);

        if (!$verified) {
            _auth_json(false, 'Invalid credentials', [], 401);
        }

        if (isset($row['is_active']) && !(bool)$row['is_active']) {
            _auth_json(false, 'Account disabled', [], 403);
        }

        $dbUserId = isset($row['id']) ? (int)$row['id'] : 0;

        // Fetch role_id and tenant_id from tenant_users (not stored on users table)
        $tenantRow = null;
        if ($dbUserId > 0) {
            try {
                $tuStmt = $pdo->prepare(
                    "SELECT tenant_id, role_id FROM tenant_users
                     WHERE user_id = ? AND is_active = 1
                     ORDER BY joined_at DESC LIMIT 1"
                );
                $tuStmt->execute([$dbUserId]);
                $tenantRow = $tuStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                error_log('[api/auth.php] tenant_users lookup error: ' . $e->getMessage());
            }
        }

        $roleId   = isset($tenantRow['role_id'])   ? (int)$tenantRow['role_id']   : null;
        $tenantId = isset($tenantRow['tenant_id']) ? (int)$tenantRow['tenant_id'] : 1;

        $rbac = _auth_rbac($pdo, $dbUserId, $roleId);

        session_regenerate_id(true);

        $user = [
            'id'                 => $dbUserId ?: null,
            'username'           => $row['username'] ?? null,
            'email'              => $row['email']    ?? null,
            'role_id'            => $roleId,
            'tenant_id'          => $tenantId,
            'preferred_language' => $row['preferred_language'] ?? 'en',
            'is_active'          => !empty($row['is_active']),
            'roles'              => $rbac['roles'],
            'permissions'        => $rbac['permissions'],
        ];

        $_SESSION['user_id']     = $user['id'];
        $_SESSION['tenant_id']   = $tenantId;
        $_SESSION['user']        = $user;
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['roles']       = $user['roles'];
        $GLOBALS['ADMIN_USER']   = $user;

        _auth_json(true, 'Login successful', ['user' => $user]);

    } catch (Throwable $e) {
        error_log('[api/auth.php] Login error: ' . $e->getMessage());
        _auth_json(false, 'Authentication failed', [], 500);
    }
}

// ── Unsupported method ────────────────────────────────────────────────────────
_auth_json(false, 'Method not allowed', [], 405);