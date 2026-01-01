<?php
/**
 * api/helpers/auth_helper.php
 *
 * Robust authentication & authorization helper for admin + API.
 * - start_session_safe()
 * - get_db_connection()
 * - get_authenticated_user_with_permissions()
 * - auth_init(), refresh_permissions_from_db()
 * - is_superadmin(), has_permission(), require_permission()
 * - helper helpers: authorize_scope(), require_any_permission(), require_all_permissions()
 *
 * Usage:
 *   require_once __DIR__ . '/auth_helper.php';
 *   start_session_safe();
 *   $db = get_db_connection(); // optional
 *   auth_init($db);
 *   if (!has_permission('drivers:view_all')) { ... }
 *
 * Save as UTF-8 without BOM.
 */

declare(strict_types=1);

// ----------------- Session helpers -----------------
if (!function_exists('start_session_safe')) {
    function start_session_safe(): void {
        if (php_sapi_name() === 'cli') return;
        if (session_status() === PHP_SESSION_NONE) {
            // suppress warnings but keep attempt
            @session_start();
        }
    }
}

// ----------------- DB helper -----------------
if (!function_exists('get_db_connection')) {
    /**
     * Return a mysqli connection if available in globals or via connectDB()
     * Caller may pass this into auth_init() or refresh_permissions_from_db()
     *
     * @return mysqli|null
     */
    function get_db_connection() {
        // try common global names
        global $mysqli, $db, $conn;
        if (!empty($mysqli) && $mysqli instanceof mysqli) return $mysqli;
        if (!empty($db) && $db instanceof mysqli) return $db;
        if (!empty($conn) && $conn instanceof mysqli) return $conn;
        // try user-provided connectDB()
        if (function_exists('connectDB')) {
            try {
                $c = @connectDB();
                if ($c instanceof mysqli) return $c;
            } catch (Throwable $e) {
                error_log('get_db_connection: connectDB threw: ' . $e->getMessage());
            }
        }
        return null;
    }
}

// ----------------- Build authenticated user snapshot -----------------
if (!function_exists('get_authenticated_user_with_permissions')) {
    /**
     * Returns normalized user array with permissions, or null if unauthenticated.
     *
     * Attempts to:
     *  - use $_SESSION['user'] if present
     *  - ensure $_SESSION['permissions'] and permissions_map exist (from session caches or DB)
     *  - fallback to building from $_SESSION keys (user_id etc.) and DB if available
     *
     * @return array|null
     */
    function get_authenticated_user_with_permissions(): ?array {
        start_session_safe();
        $db = get_db_connection();

        // If session already has a full user snapshot, normalize and return
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $user = $_SESSION['user'];

            // Ensure permissions array exists
            if (empty($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
                if (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) {
                    $_SESSION['permissions'] = array_keys(array_filter($_SESSION['permissions_map'], function($v){ return (bool)$v; }));
                } elseif (!empty($_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]) && is_array($_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]) && !empty($_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]['permissions'])) {
                    $_SESSION['permissions'] = (array)$_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]['permissions'];
                } else {
                    $_SESSION['permissions'] = [];
                }
            }

            // attach permissions to returned user
            $user['permissions'] = array_values(array_unique($_SESSION['permissions']));

            // preferred language: try DB authoritative value if DB exists
            $sessionLang = !empty($_SESSION['preferred_language']) ? preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$_SESSION['preferred_language'])) : null;
            if ($db instanceof mysqli && !empty($user['id'])) {
                try {
                    $uid = (int)$user['id'];
                    $stmt = $db->prepare("SELECT preferred_language FROM users WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('i', $uid);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if ($row && !empty($row['preferred_language'])) {
                            $dbLang = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$row['preferred_language']));
                            if ($dbLang !== '') {
                                if ($sessionLang !== $dbLang) $_SESSION['preferred_language'] = $dbLang;
                                $user['preferred_language'] = $dbLang;
                            } else {
                                if ($sessionLang) $user['preferred_language'] = $sessionLang;
                            }
                        } else {
                            if ($sessionLang) $user['preferred_language'] = $sessionLang;
                        }
                    } else {
                        if ($sessionLang) $user['preferred_language'] = $sessionLang;
                    }
                } catch (Throwable $e) {
                    error_log('get_authenticated_user_with_permissions: db preferred_language failed: ' . $e->getMessage());
                    if ($sessionLang) $user['preferred_language'] = $sessionLang;
                }
            } else {
                if ($sessionLang) $user['preferred_language'] = $sessionLang;
            }

            // ensure roles array exists
            if (empty($user['roles']) || !is_array($user['roles'])) $user['roles'] = $user['roles'] ?? [];

            // normalize and persist snapshot
            $_SESSION['user'] = $user;
            $_SESSION['permissions'] = $user['permissions'];
            if (empty($_SESSION['permissions_map'])) $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);

            return $user;
        }

        // If only user_id present in session, build snapshot from session + DB
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

            // permissions_map => list
            if (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) {
                $built['permissions'] = array_keys(array_filter($_SESSION['permissions_map'], function($v){ return (bool)$v; }));
            }

            // rbac cache
            $rbacKey = 'rbac_cache_user_' . $uid;
            if (!empty($_SESSION[$rbacKey]) && is_array($_SESSION[$rbacKey])) {
                if (!empty($_SESSION[$rbacKey]['permissions']) && is_array($_SESSION[$rbacKey]['permissions'])) {
                    $built['permissions'] = array_values(array_unique(array_merge($built['permissions'], $_SESSION[$rbacKey]['permissions'])));
                }
                if (!empty($_SESSION[$rbacKey]['roles']) && is_array($_SESSION[$rbacKey]['roles'])) {
                    $built['roles'] = array_values(array_unique(array_merge($built['roles'], $_SESSION[$rbacKey]['roles'])));
                }
            }

            // fallback to DB for permissions if available
            if (empty($built['permissions']) && ($db instanceof mysqli)) {
                try {
                    // try direct user_permissions
                    $q = "SELECT p.key_name AS perm FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?";
                    if ($s = $db->prepare($q)) {
                        $s->bind_param('i', $uid);
                        $s->execute();
                        $r = $s->get_result();
                        while ($row = $r->fetch_assoc()) $built['permissions'][] = $row['perm'];
                        $s->close();
                    }

                    // via roles (user_roles -> role_permissions)
                    if (empty($built['permissions'])) {
                        $q2 = "SELECT DISTINCT p.key_name AS perm FROM permissions p
                               JOIN role_permissions rp ON rp.permission_id = p.id
                               JOIN user_roles ur ON ur.role_id = rp.role_id
                               WHERE ur.user_id = ?";
                        if ($s2 = $db->prepare($q2)) {
                            $s2->bind_param('i', $uid);
                            $s2->execute();
                            $r2 = $s2->get_result();
                            while ($row = $r2->fetch_assoc()) $built['permissions'][] = $row['perm'];
                            $s2->close();
                        }
                    }
                } catch (Throwable $e) {
                    error_log('get_authenticated_user_with_permissions: permission DB fallback failed: ' . $e->getMessage());
                }
            }

            $built['permissions'] = array_values(array_unique($built['permissions']));

            // if DB present, enrich profile fields & preferred language
            if ($db instanceof mysqli) {
                try {
                    $stmt = $db->prepare("SELECT preferred_language, name, email, avatar, role_id FROM users WHERE id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('i', $uid);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if ($row) {
                            if (!empty($row['preferred_language'])) {
                                $dbLang = preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$row['preferred_language']));
                                if ($dbLang !== '') {
                                    $built['preferred_language'] = $dbLang;
                                    $_SESSION['preferred_language'] = $dbLang;
                                }
                            }
                            if (empty($built['name']) && !empty($row['name'])) $built['name'] = $row['name'];
                            if (empty($built['email']) && !empty($row['email'])) $built['email'] = $row['email'];
                            if (empty($built['avatar']) && !empty($row['avatar'])) $built['avatar'] = $row['avatar'];
                            if (!empty($row['role_id'])) $built['role_id'] = $row['role_id'];
                        }
                    }
                } catch (Throwable $e) {
                    error_log('get_authenticated_user_with_permissions: user enrich failed: ' . $e->getMessage());
                }
            }

            // persist
            $_SESSION['user'] = $built;
            $_SESSION['permissions'] = $built['permissions'];
            if (empty($_SESSION['permissions_map'])) $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);

            return $built;
        }

        return null;
    }
}

// ----------------- Authorization core (init + checks) -----------------
if (!function_exists('auth_init')) {
    /**
     * Ensure permission caches exist. Optionally pass mysqli $db to refresh from DB if needed.
     */
    function auth_init($db = null): void {
        start_session_safe();
        // If map present assume initialized
        if (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) return;

        // If session has permissions list, build map
        if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);
            return;
        }

        // Try rbac cache
        if (!empty($_SESSION['user']['id'])) {
            $k = 'rbac_cache_user_' . (int)$_SESSION['user']['id'];
            if (!empty($_SESSION[$k]) && is_array($_SESSION[$k]) && !empty($_SESSION[$k]['permissions'])) {
                $_SESSION['permissions'] = (array)$_SESSION[$k]['permissions'];
                $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);
                return;
            }
        }

        // Fallback: if DB provided, refresh
        if ($db instanceof mysqli && !empty($_SESSION['user_id'])) {
            refresh_permissions_from_db($db, (int)$_SESSION['user_id']);
            return;
        }

        // Ensure arrays exist
        if (empty($_SESSION['permissions'])) $_SESSION['permissions'] = [];
        if (empty($_SESSION['permissions_map'])) $_SESSION['permissions_map'] = array_fill_keys($_SESSION['permissions'], true);
    }
}

if (!function_exists('refresh_permissions_from_db')) {
    /**
     * Try to populate $_SESSION['permissions'] and map from DB for given user id.
     * Returns true on success.
     */
    function refresh_permissions_from_db(mysqli $db, int $user_id): bool {
        try {
            $perms = [];

            // Try user_permissions
            $q = "SELECT p.key_name AS perm FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?";
            if ($s = $db->prepare($q)) {
                $s->bind_param('i', $user_id);
                $s->execute();
                $r = $s->get_result();
                while ($row = $r->fetch_assoc()) $perms[] = $row['perm'];
                $s->close();
            }

            // If none, try via roles
            if (empty($perms)) {
                $q2 = "SELECT DISTINCT p.key_name AS perm FROM permissions p
                       JOIN role_permissions rp ON rp.permission_id = p.id
                       JOIN user_roles ur ON ur.role_id = rp.role_id
                       WHERE ur.user_id = ?";
                if ($s2 = $db->prepare($q2)) {
                    $s2->bind_param('i', $user_id);
                    $s2->execute();
                    $r2 = $s2->get_result();
                    while ($row = $r2->fetch_assoc()) $perms[] = $row['perm'];
                    $s2->close();
                }
            }

            $perms = array_values(array_unique($perms));
            $_SESSION['permissions'] = $perms;
            $_SESSION['permissions_map'] = array_fill_keys($perms, true);
            return true;
        } catch (Throwable $e) {
            error_log('refresh_permissions_from_db: ' . $e->getMessage());
            return false;
        }
    }
}

// ----------------- Superadmin helper -----------------
if (!function_exists('is_superadmin')) {
    function is_superadmin(): bool {
        start_session_safe();
        // role_id convention
        if (!empty($_SESSION['role_id']) && ((int)$_SESSION['role_id'] === 1)) return true;
        if (!empty($_SESSION['user']['role_id']) && ((int)$_SESSION['user']['role_id'] === 1)) return true;
        // permission override
        if (!empty($_SESSION['permissions_map']['superadmin'])) return true;
        return false;
    }
}

// ----------------- Permission checks -----------------
if (!function_exists('has_permission')) {
    /**
     * Check permissions with support for:
     * - string or array (any/all)
     * - wildcard resource:* matching
     */
    function has_permission($perm, bool $requireAll = false): bool {
        start_session_safe();
        auth_init(get_db_connection());

        // superadmin bypass
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

        // direct map check
        if (!empty($_SESSION['permissions_map'][$p])) return true;

        // support resource:* wildcard
        if (substr($p, -2) === ':*') {
            $prefix = substr($p, 0, -2);
            foreach ($_SESSION['permissions'] as $ex) {
                if (strpos($ex, $prefix . ':') === 0) return true;
            }
            return false;
        }

        // resource:action when resource:* exists
        if (strpos($p, ':') !== false) {
            $parts = explode(':', $p, 2);
            $resource = $parts[0];
            foreach ($_SESSION['permissions'] as $ex) {
                if ($ex === $resource . ':*') return true;
            }
        }

        return false;
    }
}

// ----------------- Enforce permission -----------------
if (!function_exists('require_permission')) {
    /**
     * Enforce permission; deny with rp_deny() if available, otherwise JSON or HTML response.
     * If $redirect true (default) will redirect to login for unauthenticated users.
     */
    function require_permission($perm, bool $redirect = true): bool {
        start_session_safe();
        // ensure user or redirect
        $user = get_authenticated_user_with_permissions();
        if (!$user) {
            if ($redirect) {
                // save redirect and send to login (admin)
                $_SESSION['after_login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
                header('Location: /admin/login.php');
                exit;
            }
            // unauthenticated caller in API context should get 401
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $acceptJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            if ($isAjax || $acceptJson || (isset($_SERVER['REQUEST_URI']) && (function_exists('str_starts_with') ? str_starts_with($_SERVER['REQUEST_URI'], '/api/') : strpos($_SERVER['REQUEST_URI'], '/api/') === 0))) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }
            return false;
        }

        auth_init(get_db_connection());
        if (has_permission($perm)) return true;

        // Deny
        if (function_exists('rp_deny')) {
            rp_deny('غير مصرح', 403);
            exit;
        }

        // Determine API vs HTML
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        $isApi = $isAjax || $acceptJson || (isset($_SERVER['REQUEST_URI']) && (function_exists('str_starts_with') ? str_starts_with($_SERVER['REQUEST_URI'], '/api/') : strpos($_SERVER['REQUEST_URI'], '/api/') === 0));

        http_response_code(403);
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Forbidden - insufficient permissions'], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
            echo '<!doctype html><html lang="ar"><head><meta charset="utf-8"><title>ممنوع</title></head><body><h1>غير مصرح</h1><p>ليس لديك صلاحية الوصول إلى هذه الصفحة.</p></body></html>';
            exit;
        }
    }
}

// ----------------- Convenience wrappers -----------------
if (!function_exists('require_any_permission')) {
    function require_any_permission(array $perms): void {
        require_permission($perms, true);
    }
}
if (!function_exists('require_all_permissions')) {
    function require_all_permissions(array $perms): void {
        require_permission($perms, true);
    }
}

// ----------------- authorize_scope helper -----------------
if (!function_exists('authorize_scope')) {
    /**
     * Decision helper for "scope" parameter (owner vs all).
     * Example:
     *   if ($scope === 'all') { require_permission('drivers:view_all'); fetchAll(); }
     *   else { // owner
     *       // require_permission('drivers:view_own'); fetch own
     *   }
     *
     * Returns true if allowed, false otherwise (does not exit).
     */
    function authorize_scope(string $scope, string $permAll, string $permOwn): bool {
        start_session_safe();
        auth_init(get_db_connection());
        if ($scope === 'all') {
            return has_permission($permAll);
        } else {
            // owner allowed if has own-permission or is superadmin
            if (has_permission($permOwn)) return true;
            if (is_superadmin()) return true;
            return false;
        }
    }
}

// ----------------- Utility: is_logged_in -----------------
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        start_session_safe();
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) return true;
        if (!empty($_SESSION['user_id'])) return true;
        return false;
    }
}