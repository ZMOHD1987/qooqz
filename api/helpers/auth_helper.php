<?php
/**
 * api/helpers/auth_helper.php
 *
 * Robust authentication & authorization helper for admin + API.
 * Fixed: defensive DB acquisition, safe statement fetch (works without mysqlnd),
 * tolerant column name discovery (permission keys: key_name|name|permission|code),
 * and reliable population of session permissions/roles.
 *
 * Usage:
 *   require_once __DIR__ . '/auth_helper.php';
 *   start_session_safe();
 *   $db = get_db_connection();
 *   auth_init($db);
 *   $user = get_authenticated_user_with_permissions();
 *
 * Save as UTF-8 without BOM.
 */

declare(strict_types=1);

// ----------------- Session helpers -----------------
if (!function_exists('start_session_safe')) {
    function start_session_safe(): void {
        if (php_sapi_name() === 'cli') return;
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
}

// ----------------- DB helper -----------------
if (!function_exists('get_db_connection')) {
    /**
     * Return a mysqli connection if available from known sources:
     * - container('db') if provided
     * - $GLOBALS['CONTAINER']['db'], $GLOBALS['db'], $GLOBALS['mysqli'], $GLOBALS['conn']
     * - connectDB() if it returns mysqli or config array/object (will attempt connect)
     *
     * @return mysqli|null
     */
    function get_db_connection() {
        // try container helper
        if (function_exists('container')) {
            try {
                $maybe = @container('db');
                if ($maybe instanceof mysqli) return $maybe;
            } catch (Throwable $e) { /* ignore */ }
        }

        // common globals
        foreach (['CONTAINER','db','mysqli','conn'] as $g) {
            if (isset($GLOBALS[$g])) {
                $val = $GLOBALS[$g];
                if ($val instanceof mysqli) return $val;
                if (is_array($val) && isset($val['db']) && $val['db'] instanceof mysqli) return $val['db'];
                if (is_array($val) && isset($val['mysqli']) && $val['mysqli'] instanceof mysqli) return $val['mysqli'];
            }
        }

        // try connectDB() (it may return mysqli or config)
        if (function_exists('connectDB')) {
            try {
                $maybe = @connectDB();
                if ($maybe instanceof mysqli) return $maybe;
                // if config returned (array or object) attempt to connect
                if (is_array($maybe) || is_object($maybe)) {
                    $cfg = array();
                    if (is_array($maybe)) $cfg = $maybe;
                    else {
                        foreach (get_object_vars($maybe) as $k => $v) $cfg[$k] = $v;
                    }
                    // normalize keys
                    $host = $cfg['host'] ?? $cfg['DB_HOST'] ?? $cfg['hostname'] ?? null;
                    $user = $cfg['user'] ?? $cfg['DB_USER'] ?? $cfg['username'] ?? null;
                    $pass = $cfg['pass'] ?? $cfg['DB_PASS'] ?? $cfg['password'] ?? '';
                    $name = $cfg['name'] ?? $cfg['DB_NAME'] ?? $cfg['database'] ?? null;
                    $port = isset($cfg['port']) ? (int)$cfg['port'] : (defined('DB_PORT') ? (int)DB_PORT : 3306);
                    if ($host && $user && $name) {
                        $m = @new mysqli($host, $user, $pass, $name, $port);
                        if ($m && !$m->connect_errno) { @$m->set_charset('utf8mb4'); return $m; }
                    }
                }
            } catch (Throwable $e) {
                error_log('get_db_connection: connectDB threw: ' . $e->getMessage());
            }
        }

        // attempt using defined constants / env
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: null);
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: null);
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: null);
        $name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: null);
        $port = defined('DB_PORT') ? (int)DB_PORT : (int)(getenv('DB_PORT') ?: 3306);
        if ($host && $user && $name) {
            $m = @new mysqli($host, $user, $pass, $name, $port);
            if ($m && !$m->connect_errno) { @$m->set_charset('utf8mb4'); return $m; }
        }

        return null;
    }
}

// ----------------- Safe stmt fetch helpers -----------------
if (!function_exists('stmt_fetch_all_assoc')) {
    function stmt_fetch_all_assoc(mysqli_stmt $stmt): array {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res === false) return [];
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
            return $rows;
        }
        $meta = $stmt->result_metadata();
        if (!$meta) return [];
        $fields = [];
        $row = [];
        $refs = [];
        while ($f = $meta->fetch_field()) {
            $fields[] = $f->name;
            $row[$f->name] = null;
            $refs[] = &$row[$f->name];
        }
        $meta->free();
        call_user_func_array([$stmt, 'bind_result'], $refs);
        $out = [];
        while ($stmt->fetch()) {
            $r = [];
            foreach ($fields as $fn) $r[$fn] = $row[$fn];
            $out[] = $r;
        }
        return $out;
    }
}

if (!function_exists('stmt_fetch_one_assoc')) {
    function stmt_fetch_one_assoc(mysqli_stmt $stmt) {
        $rows = stmt_fetch_all_assoc($stmt);
        return !empty($rows) ? $rows[0] : null;
    }
}

// ----------------- Schema helpers -----------------
if (!function_exists('table_exists')) {
    function table_exists(mysqli $db, string $table): bool {
        try {
            $t = $db->real_escape_string($table);
            $res = $db->query("SHOW TABLES LIKE '{$t}'");
            if ($res) { $ok = $res->num_rows > 0; $res->free(); return $ok; }
        } catch (Throwable $e) { /* ignore */ }
        return false;
    }
}
if (!function_exists('column_exists')) {
    function column_exists(mysqli $db, string $table, string $column): bool {
        try {
            $stmt = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
            if (!$stmt) return false;
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            if (method_exists($stmt,'get_result')) {
                $res = $stmt->get_result();
                $ok = (bool)($res && $res->fetch_assoc());
            } else {
                $meta = $stmt->result_metadata();
                $ok = (bool)$meta;
                if ($meta) $meta->free();
            }
            $stmt->close();
            return $ok;
        } catch (Throwable $e) { return false; }
    }
}

// ----------------- Permission/role discovery -----------------
if (!function_exists('discover_permission_column')) {
    function discover_permission_column(mysqli $db, string $permTable): ?string {
        $candidates = ['key_name','name','permission_key','perm_key','permission','code'];
        foreach ($candidates as $c) {
            if (column_exists($db, $permTable, $c)) return $c;
        }
        return null;
    }
}

// ----------------- Populate permissions from DB -----------------
if (!function_exists('refresh_permissions_from_db')) {
    function refresh_permissions_from_db($db, int $user_id): bool {
        try {
            if (!($db instanceof mysqli)) return false;
            $perms = [];

            // Strategy:
            // 1) If user_permissions table exists and has permission_id -> join permissions to get key_name/name
            // 2) If user_permissions has direct 'permission' column, use it
            // 3) If user_roles & role_permissions exist, join to permissions
            // 4) Fallback: users.role_id => roles.name (set as role-based string), and for role_id==1 add super_admin

            // 1) user_permissions mapping to permissions table (permission_id)
            if (table_exists($db,'user_permissions') && table_exists($db,'permissions')) {
                // determine permission name column in permissions table
                $permCol = discover_permission_column($db, 'permissions') ?: 'name';
                $sql = "SELECT p.`{$permCol}` AS perm FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('i', $user_id);
                    $rows = stmt_fetch_all_assoc($stmt);
                    foreach ($rows as $r) if (!empty($r['perm'])) $perms[] = $r['perm'];
                }
            }

            // 2) direct permission strings in user_permissions.permission
            if (empty($perms) && table_exists($db,'user_permissions') && column_exists($db,'user_permissions','permission')) {
                $stmt = $db->prepare("SELECT permission FROM user_permissions WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i',$user_id);
                    $rows = stmt_fetch_all_assoc($stmt);
                    foreach ($rows as $r) if (!empty($r['permission'])) $perms[] = $r['permission'];
                }
            }

            // 3) role-based via role_permissions -> permissions
            if (empty($perms) && table_exists($db,'user_roles') && table_exists($db,'role_permissions') && table_exists($db,'permissions')) {
                $permCol = discover_permission_column($db, 'permissions') ?: 'name';
                $sql = "SELECT DISTINCT p.`{$permCol}` AS perm
                        FROM permissions p
                        JOIN role_permissions rp ON rp.permission_id = p.id
                        JOIN user_roles ur ON ur.role_id = rp.role_id
                        WHERE ur.user_id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param('i',$user_id);
                    $rows = stmt_fetch_all_assoc($stmt);
                    foreach ($rows as $r) if (!empty($r['perm'])) $perms[] = $r['perm'];
                }
            }

            // 4) fallback role_id -> roles.name (useful to set roles)
            $roles = [];
            if (table_exists($db,'user_roles') && table_exists($db,'roles')) {
                $stmt = $db->prepare("SELECT r.name AS role_name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i',$user_id);
                    $rows = stmt_fetch_all_assoc($stmt);
                    foreach ($rows as $r) if (!empty($r['role_name'])) $roles[] = $r['role_name'];
                }
            } else {
                // try users.role_id -> roles.name
                if (table_exists($db,'users') && column_exists($db,'users','role_id') && table_exists($db,'roles') && column_exists($db,'roles','name')) {
                    $stmt = $db->prepare("SELECT u.role_id, r.name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('i',$user_id);
                        $row = stmt_fetch_one_assoc($stmt);
                        if (!empty($row['name'])) $roles[] = $row['name'];
                    }
                }
            }

            // normalize
            $perms = array_values(array_unique(array_filter(array_map('strval', $perms))));
            $roles = array_values(array_unique(array_filter(array_map('strval', $roles))));

            // persist to session
            start_session_safe();
            $_SESSION['permissions'] = $perms;
            $_SESSION['permissions_map'] = array_fill_keys($perms, true);
            if (!empty($roles)) $_SESSION['roles'] = $roles;

            // if still empty and user has role_id==1 (super admin) -> grant defaults
            if (empty($perms)) {
                // try to detect role_id
                $roleId = null;
                if (table_exists($db,'users') && column_exists($db,'users','role_id')) {
                    $s = $db->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1");
                    if ($s) {
                        $s->bind_param('i', $user_id);
                        $row = stmt_fetch_one_assoc($s);
                        if (!empty($row['role_id'])) $roleId = (int)$row['role_id'];
                    }
                }
                if ($roleId === 1) {
                    $_SESSION['permissions'] = ['super_admin','manage_banners','manage_users'];
                    $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);
                    if (empty($_SESSION['roles'])) $_SESSION['roles'] = ['admin'];
                }
            }

            return true;
        } catch (Throwable $e) {
            error_log('refresh_permissions_from_db error: ' . $e->getMessage());
            return false;
        }
    }
}

// ----------------- Get authenticated user snapshot (main) -----------------
if (!function_exists('get_authenticated_user_with_permissions')) {
    function get_authenticated_user_with_permissions(): ?array {
        start_session_safe();
        $db = get_db_connection();

        // If session user exists return it (enrich from DB if possible)
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $user = $_SESSION['user'];

            // ensure permissions
            if (empty($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
                if ($db instanceof mysqli && !empty($user['id'])) {
                    refresh_permissions_from_db($db, (int)$user['id']);
                } else {
                    $_SESSION['permissions'] = $_SESSION['permissions'] ?? [];
                    $_SESSION['permissions_map'] = $_SESSION['permissions_map'] ?? array_fill_keys($_SESSION['permissions'], true);
                }
            }

            $user['permissions'] = array_values(array_unique($_SESSION['permissions'] ?? []));
            $user['roles'] = array_values(array_unique($_SESSION['roles'] ?? []));
            // preferred_language: prefer DB value if present
            if ($db instanceof mysqli && !empty($user['id'])) {
                try {
                    $s = $db->prepare("SELECT preferred_language FROM users WHERE id = ? LIMIT 1");
                    if ($s) {
                        $s->bind_param('i', $user['id']);
                        $row = stmt_fetch_one_assoc($s);
                        if (!empty($row['preferred_language'])) {
                            $user['preferred_language'] = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$row['preferred_language']));
                            $_SESSION['preferred_language'] = $user['preferred_language'];
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }
            }

            $_SESSION['user'] = $user;
            return $user;
        }

        // If session has user_id but not full snapshot
        if (!empty($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
            $profile = [
                'id' => $uid,
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'avatar' => $_SESSION['avatar'] ?? null,
                'preferred_language' => $_SESSION['preferred_language'] ?? null,
                'roles' => [],
                'permissions' => []
            ];

            if ($db instanceof mysqli) {
                // load user row
                try {
                    $s = $db->prepare("SELECT id, username, email, preferred_language, role_id FROM users WHERE id = ? LIMIT 1");
                    if ($s) {
                        $s->bind_param('i', $uid);
                        $u = stmt_fetch_one_assoc($s);
                        if ($u) {
                            $profile['username'] = $u['username'] ?? $profile['username'];
                            $profile['email'] = $u['email'] ?? $profile['email'];
                            if (!empty($u['preferred_language'])) {
                                $profile['preferred_language'] = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$u['preferred_language']));
                                $_SESSION['preferred_language'] = $profile['preferred_language'];
                            }
                            if (!empty($u['role_id'])) $profile['role_id'] = (int)$u['role_id'];
                        }
                    }
                } catch (Throwable $e) { /* ignore */ }

                // refresh permissions from DB
                refresh_permissions_from_db($db, $uid);
                $profile['permissions'] = $_SESSION['permissions'] ?? [];
                $profile['roles'] = $_SESSION['roles'] ?? [];
            }

            $_SESSION['user'] = $profile;
            return $profile;
        }

        return null;
    }
}

// ----------------- Initialization & checks -----------------
if (!function_exists('auth_get_user_permissions')) {
    function auth_get_user_permissions($user_id) {
        start_session_safe();
        $db = get_db_connection();
        if ($db instanceof mysqli) {
            refresh_permissions_from_db($db, (int)$user_id);
        }
        return $_SESSION['permissions'] ?? [];
    }
}

if (!function_exists('auth_get_user_roles')) {
    function auth_get_user_roles($user_id) {
        start_session_safe();
        $db = get_db_connection();
        if (!($db instanceof mysqli)) return $_SESSION['roles'] ?? [];
        $out = [];

        // try user_roles join
        try {
            $stmt = $db->prepare("SELECT r.name AS role_name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $rows = stmt_fetch_all_assoc($stmt);
                foreach ($rows as $r) if (!empty($r['role_name'])) $out[] = $r['role_name'];
            } else {
                // fallback users.role_id -> roles.name
                $s = $db->prepare("SELECT u.role_id, r.name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
                if ($s) {
                    $s->bind_param('i', $user_id);
                    $row = stmt_fetch_one_assoc($s);
                    if (!empty($row['name'])) $out[] = $row['name'];
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        $out = array_values(array_unique(array_merge($out, $_SESSION['roles'] ?? [])));
        $_SESSION['roles'] = $out;
        return $out;
    }
}

// ----------------- Permission checks / helpers (unchanged) -----------------
if (!function_exists('auth_init')) {
    function auth_init($db = null): void {
        start_session_safe();
        if (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) return;
        if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);
            return;
        }
        if (!empty($_SESSION['user']['id'])) {
            $k = 'rbac_cache_user_' . (int)$_SESSION['user']['id'];
            if (!empty($_SESSION[$k]) && is_array($_SESSION[$k]) && !empty($_SESSION[$k]['permissions'])) {
                $_SESSION['permissions'] = (array)$_SESSION[$k]['permissions'];
                $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);
                return;
            }
        }
        if ($db instanceof mysqli && !empty($_SESSION['user']['id'])) {
            refresh_permissions_from_db($db, (int)$_SESSION['user']['id']);
            return;
        }
        if (empty($_SESSION['permissions'])) $_SESSION['permissions'] = [];
        if (empty($_SESSION['permissions_map'])) $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);
    }
}

if (!function_exists('is_superadmin')) {
    function is_superadmin(): bool {
        start_session_safe();
        if (!empty($_SESSION['user']['role_id']) && (int)$_SESSION['user']['role_id'] === 1) return true;
        if (!empty($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) return true;
        if (!empty($_SESSION['permissions_map']['super_admin']) || !empty($_SESSION['permissions_map']['superadmin'])) return true;
        return false;
    }
}

if (!function_exists('has_permission')) {
    function has_permission($perm, bool $requireAll = false): bool {
        start_session_safe();
        auth_init(get_db_connection());
        if (is_superadmin()) return true;
        if (is_array($perm)) {
            if ($requireAll) {
                foreach ($perm as $p) if (!has_permission($p, false)) return false;
                return true;
            } else {
                foreach ($perm as $p) if (has_permission($p, false)) return true;
                return false;
            }
        }
        if (!is_string($perm) && !is_numeric($perm)) return false;
        $p = (string)$perm;
        if (!empty($_SESSION['permissions_map'][$p])) return true;
        if (substr($p, -2) === ':*') {
            $prefix = substr($p, 0, -2);
            foreach ($_SESSION['permissions'] as $ex) { if (strpos($ex, $prefix . ':') === 0) return true; }
            return false;
        }
        if (strpos($p, ':') !== false) {
            $parts = explode(':', $p, 2);
            $resource = $parts[0];
            foreach ($_SESSION['permissions'] as $ex) { if ($ex === $resource . ':*') return true; }
        }
        return false;
    }
}

if (!function_exists('require_permission')) {
    function require_permission($perm, bool $redirect = true): bool {
        start_session_safe();
        $user = get_authenticated_user_with_permissions();
        if (!$user) {
            if ($redirect) {
                $_SESSION['after_login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
                header('Location: /admin/login.php'); exit;
            }
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'message'=>'Not authenticated'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        auth_init(get_db_connection());
        if (has_permission($perm)) return true;
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Convenience wrappers
if (!function_exists('require_any_permission')) {
    function require_any_permission(array $perms): void { require_permission($perms, true); }
}
if (!function_exists('require_all_permissions')) {
    function require_all_permissions(array $perms): void { require_permission($perms, true); }
}
