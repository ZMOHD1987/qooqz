<?php
// htdocs/api/helpers/rbac.php
// Robust RBAC helper (defensive) — replacement to avoid Unknown column / uncaught exceptions.
// Backup original file first: cp rbac.php rbac.php.bak
declare(strict_types=1);

if (!function_exists('start_session_safe')) {
    function start_session_safe(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
}

if (!function_exists('get_db')) {
    function get_db(): ?mysqli {
        // Try common globals/functions used in various apps
        if (function_exists('acquire_db')) {
            try { $db = @acquire_db(); if ($db instanceof mysqli) return $db; } catch (Throwable $e) { error_log('get_db: acquire_db threw: '.$e->getMessage()); }
        }
        foreach (['conn','mysqli','db'] as $k) {
            if (!empty($GLOBALS[$k]) && $GLOBALS[$k] instanceof mysqli) return $GLOBALS[$k];
        }
        $cfg = __DIR__ . '/../config/db.php';
        if (is_readable($cfg)) {
            @include_once $cfg;
            foreach (['conn','mysqli','db'] as $k) {
                if (!empty($$k) && $$k instanceof mysqli) return $$k;
            }
            // also check globals again after include
            foreach (['conn','mysqli','db'] as $k) {
                if (!empty($GLOBALS[$k]) && $GLOBALS[$k] instanceof mysqli) return $GLOBALS[$k];
            }
        }
        return null;
    }
}

if (!function_exists('get_current_user')) {
    function get_current_user(): ?array {
        start_session_safe();
        $db = get_db();

        // if normalized user in session -> ensure permissions array exists, attempt to refresh some fields safely
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $user = $_SESSION['user'];
            if (empty($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
                // try restore from rbact cache
                $uid = $user['id'] ?? null;
                if ($uid && !empty($_SESSION['rbac_cache_user_' . $uid]['permissions'])) {
                    $_SESSION['permissions'] = (array)$_SESSION['rbac_cache_user_' . $uid]['permissions'];
                } else {
                    if ($db instanceof mysqli && $uid) {
                        try { $_SESSION['permissions'] = _rbac_fetch_permissions_by_user($db, (int)$uid); } catch (Throwable $e) { error_log('get_current_user:_rbac_fetch_permissions_by_user failed: '.$e->getMessage()); $_SESSION['permissions'] = []; }
                    } else {
                        $_SESSION['permissions'] = [];
                    }
                }
            }
            $user['permissions'] = array_values(array_unique($_SESSION['permissions'] ?? []));
            // attempt to refresh other fields from DB in a flexible way
            if ($db instanceof mysqli && !empty($user['id'])) {
                try {
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        $uid = (int)$user['id'];
                        $stmt->bind_param('i', $uid);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if (is_array($row)) {
                            // pick available columns safely
                            if (empty($user['name']) && isset($row['name'])) $user['name'] = $row['name'];
                            if (empty($user['name']) && isset($row['username'])) $user['name'] = $row['username'];
                            if (empty($user['email']) && isset($row['email'])) $user['email'] = $row['email'];
                            if (empty($user['avatar']) && isset($row['avatar'])) $user['avatar'] = $row['avatar'];
                            if (empty($user['role_id']) && isset($row['role_id'])) $user['role_id'] = (int)$row['role_id'];
                            if (isset($row['preferred_language'])) {
                                $lang = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$row['preferred_language']));
                                if ($lang !== '') { $_SESSION['preferred_language'] = $lang; $user['preferred_language'] = $lang; }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log('get_current_user: DB refresh failed: ' . $e->getMessage());
                }
            }
            $_SESSION['user'] = $user;
            $_SESSION['permissions'] = $user['permissions'];
            return $user;
        }

        // If only user_id in session: build
        if (!empty($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
            $built = [
                'id' => $uid,
                'name' => $_SESSION['username'] ?? '',
                'username' => $_SESSION['username'] ?? '',
                'email' => $_SESSION['email'] ?? '',
                'avatar' => $_SESSION['avatar'] ?? '',
                'preferred_language' => $_SESSION['preferred_language'] ?? null,
                'roles' => [],
                'permissions' => []
            ];
            // try rbac_cache
            $rbck = $_SESSION['rbac_cache_user_' . $uid] ?? null;
            if (is_array($rbck)) {
                if (!empty($rbck['permissions']) && is_array($rbck['permissions'])) $built['permissions'] = array_values(array_unique($rbck['permissions']));
                if (!empty($rbck['roles']) && is_array($rbck['roles'])) $built['roles'] = array_values(array_unique($rbck['roles']));
            }
            if (empty($built['permissions']) && $db instanceof mysqli) {
                try { $built['permissions'] = _rbac_fetch_permissions_by_user($db, $uid); } catch (Throwable $e) { error_log('get_current_user:_rbac_fetch_permissions_by_user failed: '.$e->getMessage()); $built['permissions'] = []; }
            }
            if ($db instanceof mysqli) {
                try {
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('i', $uid);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if (is_array($row)) {
                            if (!empty($row['preferred_language'])) {
                                $lang = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$row['preferred_language']));
                                if ($lang !== '') { $built['preferred_language'] = $lang; $_SESSION['preferred_language'] = $lang; }
                            }
                            if (empty($built['name']) && isset($row['name'])) $built['name'] = $row['name'];
                            if (empty($built['name']) && isset($row['username'])) $built['name'] = $row['username'];
                            if (empty($built['email']) && isset($row['email'])) $built['email'] = $row['email'];
                            if (empty($built['avatar']) && isset($row['avatar'])) $built['avatar'] = $row['avatar'];
                            if (empty($built['roles']) && isset($row['role_id'])) $built['roles'] = [(int)$row['role_id']];
                        }
                    }
                } catch (Throwable $e) { error_log('get_current_user: DB user load failed: '.$e->getMessage()); }
            }
            $built['permissions'] = array_values(array_unique((array)$built['permissions']));
            $_SESSION['user'] = $built;
            $_SESSION['permissions'] = $built['permissions'];
            return $built;
        }

        return null;
    }
}

/* internal flexible permission fetch */
if (!function_exists('_rbac_fetch_permissions_by_user')) {
    function _rbac_fetch_permissions_by_user(mysqli $db, int $userId): array {
        $perms = [];
        
        try {
            // Fetch permissions based on standard RBAC schema:
            // users.role_id -> role_permissions.role_id -> permissions.key_name
            // This matches the database schema: users, roles, role_permissions, permissions tables
            
            $roleId = 0;
            
            // Get user's role_id from users table
            if ($stmt = @$db->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1")) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if ($row && isset($row['role_id'])) {
                    $roleId = (int)$row['role_id'];
                }
            }
            
            // If user has a role, fetch permissions for that role
            if ($roleId) {
                $q = "SELECT p.key_name FROM permissions p 
                      JOIN role_permissions rp ON rp.permission_id = p.id 
                      WHERE rp.role_id = ?";
                if ($stmt = @$db->prepare($q)) {
                    $stmt->bind_param('i', $roleId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        if (!empty($r['key_name'])) {
                            $perms[] = $r['key_name'];
                        }
                    }
                    $stmt->close();
                }
            }
        } catch (Throwable $e) { 
            error_log('_rbac_fetch_permissions_by_user query failed: ' . $e->getMessage()); 
        }

        return array_values(array_filter(array_unique(array_map('strval', $perms))));
    }
}

/* public wrappers */
if (!function_exists('load_user_permissions_into_session')) {
    function load_user_permissions_into_session(int $userId): array {
        start_session_safe();
        $db = get_db();
        $perms = [];
        if ($db instanceof mysqli) {
            try { $perms = _rbac_fetch_permissions_by_user($db, $userId); } catch (Throwable $e) { error_log('load_user_permissions_into_session failed: '.$e->getMessage()); }
        }
        $_SESSION['permissions'] = array_values((array)$perms);
        return $_SESSION['permissions'];
    }
}

if (!function_exists('user_has')) {
    function user_has(string $perm): bool {
        start_session_safe();
        if (!empty($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) return true;
        if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) return in_array($perm, $_SESSION['permissions'], true);
        if (!empty($_SESSION['user_id'])) {
            $user = get_current_user();
            if (!empty($user['permissions']) && is_array($user['permissions'])) return in_array($perm, $user['permissions'], true);
        }
        return false;
    }
}

if (!function_exists('can_modify_entity')) {
    function can_modify_entity(string $perm, int $ownerId): bool {
        start_session_safe();
        if (user_has($perm)) return true;
        $user = get_current_user();
        if ($user && isset($user['id']) && (int)$user['id'] === (int)$ownerId) return true;
        return false;
    }
}

if (!function_exists('require_permission')) {
    function require_permission(string $perm, array $opts = []): bool {
        start_session_safe();
        $redirect = $opts['redirect'] ?? true;
        $forceJson = $opts['json_on_fail'] ?? null;
        $isApi = false;
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $xhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if (strpos($uri, '/api/') === 0 || $xhr || $acceptJson) $isApi = true;
        if ($forceJson === true) $isApi = true;
        if ($forceJson === false) $isApi = false;

        $user = get_current_user();
        if (!$user) {
            if ($isApi) { header('Content-Type: application/json', true, 401); echo json_encode(['success'=>false,'message'=>'Authentication required']); exit; }
            if ($redirect) { $_SESSION['after_login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/'; header('Location: /login.php'); exit; }
            return false;
        }
        if (user_has($perm)) return true;
        if ($isApi) { header('Content-Type: application/json', true, 403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }
        if ($redirect) { header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403); echo "<h1>403 Forbidden</h1><p>You don't have permission to access this resource.</p>"; exit; }
        return false;
    }
}

if (!function_exists('reload_user_permissions')) {
    function reload_user_permissions(int $userId): array { return load_user_permissions_into_session($userId); }
}
if (!function_exists('clear_user_permissions_cache')) {
    function clear_user_permissions_cache(int $userId = 0): void {
        start_session_safe();
        if ($userId) {
            unset($_SESSION['rbac_cache_user_' . $userId]);
            if (!empty($_SESSION['user']) && isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] === (int)$userId) unset($_SESSION['permissions']);
        } else {
            unset($_SESSION['permissions']);
            foreach (array_keys($_SESSION) as $k) if (strpos($k, 'rbac_cache_user_') === 0) unset($_SESSION[$k]);
        }
    }
}

if (!function_exists('seed_permissions')) {
    function seed_permissions(array $definitions, array $bindRoleIds = []): array {
        $db = get_db();
        $inserted = [];
        if (!($db instanceof mysqli)) return $inserted;
        foreach ($definitions as $def) {
            $key = $def['key_name'] ?? null;
            if (!$key) continue;
            $display = $def['display_name'] ?? $key;
            $desc = $def['description'] ?? '';
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO permissions (key_name, display_name, description, created_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description), created_at = VALUES(created_at)");
            if ($stmt) {
                $stmt->bind_param('ssss', $key, $display, $desc, $now);
                $stmt->execute();
                $stmt->close();
            }
            $s2 = $db->prepare("SELECT id FROM permissions WHERE key_name = ? LIMIT 1");
            if ($s2) {
                $s2->bind_param('s', $key);
                $s2->execute();
                $r = $s2->get_result();
                $row = $r ? $r->fetch_assoc() : null;
                $s2->close();
                if ($row && !empty($row['id'])) {
                    $pid = (int)$row['id'];
                    $inserted[$key] = $pid;
                    foreach ($bindRoleIds as $rid) {
                        $s3 = $db->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1");
                        if ($s3) {
                            $s3->bind_param('ii', $rid, $pid);
                            $s3->execute();
                            $res = $s3->get_result();
                            $exists = $res ? $res->fetch_assoc() : null;
                            $s3->close();
                            if (!$exists) {
                                $s4 = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, ?)");
                                if ($s4) {
                                    $s4->bind_param('iis', $rid, $pid, $now);
                                    $s4->execute();
                                    $s4->close();
                                }
                            }
                        }
                    }
                }
            }
        }
        return $inserted;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool { start_session_safe(); return !empty($_SESSION['user']) || !empty($_SESSION['user_id']); }
}

if (!function_exists('json_error')) {
    function json_error(string $message = 'Error', int $code = 400, $extra = []) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
        $out = array_merge(['success' => false, 'message' => $message], is_array($extra) ? $extra : []);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

return;