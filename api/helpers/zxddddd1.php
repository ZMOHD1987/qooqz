<?php
// htdocs/api/helpers/auth_helper.php
// Robust auth helper:
// - start_session_safe()
// - get_authenticated_user_with_permissions(): builds user object from session (permissions_map, rbac_cache_user_<id>), optionally falls back to DB
// - require_permission($perm, $redirect = true)
// - is_logged_in()
//
// Saves normalized user snapshot in $_SESSION['user'] and permissions in $_SESSION['permissions']
// and keeps $_SESSION['preferred_language'] in sync with DB when possible.

if (!function_exists('start_session_safe')) {
    function start_session_safe(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
}

if (!function_exists('get_db_connection')) {
    function get_db_connection() {
        global $mysqli;
        return $mysqli ?? null;
    }
}

/**
 * Build or return authenticated user object with permissions.
 *
 * Behavior:
 * - If $_SESSION['user'] exists, use it as base.
 * - Ensure $_SESSION['permissions'] exists (derived from permissions_map or rbac_cache or DB).
 * - Keep $_SESSION['preferred_language'] authoritative for UI; if DB has different value and DB is available, update session to DB value.
 * - Returns null if no authenticated user info.
 */
if (!function_exists('get_authenticated_user_with_permissions')) {
    function get_authenticated_user_with_permissions(): ?array {
        start_session_safe();

        $db = get_db_connection();

        // If full user cached in session, ensure permissions & preferred_language are consistent
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $user = $_SESSION['user'];

            // Ensure permissions array exists
            if (empty($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
                // try to construct from permissions_map
                if (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) {
                    $_SESSION['permissions'] = array_keys(array_filter($_SESSION['permissions_map'], function($v){ return (bool)$v; }));
                } elseif (!empty($_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]) && is_array($_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]) && !empty($_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]['permissions'])) {
                    $_SESSION['permissions'] = (array)$_SESSION['rbac_cache_user_' . ($user['id'] ?? '')]['permissions'];
                } else {
                    $_SESSION['permissions'] = [];
                }
            }

            // Attach permissions to returned user object
            $user['permissions'] = array_values(array_unique($_SESSION['permissions']));

            // Preferred language: prefer authoritative DB value if available;
            // otherwise prefer session preferred_language if set.
            $sessionLang = !empty($_SESSION['preferred_language']) ? preg_replace('/[^a-z0-9_-]/i','',strtolower((string)$_SESSION['preferred_language'])) : null;

            if ($db instanceof mysqli && !empty($user['id'])) {
                // try to fetch preferred_language from DB and if found update session
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
                            // Update session preferred_language if differs
                            if ($sessionLang !== $dbLang) {
                                $_SESSION['preferred_language'] = $dbLang;
                            }
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
            } else {
                if ($sessionLang) $user['preferred_language'] = $sessionLang;
            }

            // ensure roles array exists
            if (empty($user['roles']) || !is_array($user['roles'])) $user['roles'] = $user['roles'] ?? [];

            // save normalized snapshot back to session
            $_SESSION['user'] = $user;
            $_SESSION['permissions'] = $user['permissions'];

            return $user;
        }

        // If only user_id in session, try to build object from session keys
        if (!empty($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
            $built = [
                'id' => $uid,
                'name' => $_SESSION['username'] ?? '',
                'email' => $_SESSION['email'] ?? '',
                'avatar' => $_SESSION['avatar'] ?? '',
                'preferred_language' => $_SESSION['preferred_language'] ?? null,
                'roles' => [],
                'permissions' => []
            ];

            // permissions_map => convert to array
            if (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) {
                $built['permissions'] = array_keys(array_filter($_SESSION['permissions_map'], function($v){ return (bool)$v; }));
            }

            // rbac cache key
            $rbacKey = 'rbac_cache_user_' . $uid;
            if (!empty($_SESSION[$rbacKey]) && is_array($_SESSION[$rbacKey])) {
                if (!empty($_SESSION[$rbacKey]['permissions']) && is_array($_SESSION[$rbacKey]['permissions'])) {
                    $built['permissions'] = array_values(array_unique(array_merge($built['permissions'], $_SESSION[$rbacKey]['permissions'])));
                }
                if (!empty($_SESSION[$rbacKey]['roles']) && is_array($_SESSION[$rbacKey]['roles'])) {
                    $built['roles'] = array_values(array_unique(array_merge($built['roles'], $_SESSION[$rbacKey]['roles'])));
                }
            }

            // if still empty and DB available, fallback to DB
            if (empty($built['permissions']) && ($db instanceof mysqli)) {
                // try direct user_permissions
                $q = "SELECT p.key_name AS perm FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?";
                if ($s = $db->prepare($q)) {
                    $s->bind_param('i', $uid);
                    $s->execute();
                    $r = $s->get_result();
                    while ($row = $r->fetch_assoc()) $built['permissions'][] = $row['perm'];
                    $s->close();
                }
                // via roles
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
            }

            // ensure unique
            $built['permissions'] = array_values(array_unique($built['permissions']));

            // if DB present, try to get authoritative preferred_language and update session
            if ($db instanceof mysqli) {
                $stmt = $db->prepare("SELECT preferred_language, name, email, avatar FROM users WHERE id = ? LIMIT 1");
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
                    }
                }
            }

            // cache in session
            $_SESSION['user'] = $built;
            $_SESSION['permissions'] = $built['permissions'];

            return $built;
        }

        // nothing to return
        return null;
    }
}

if (!function_exists('require_permission')) {
    function require_permission(string $perm, bool $redirect = true): bool {
        start_session_safe();
        $user = get_authenticated_user_with_permissions();
        if (!$user) {
            if ($redirect) {
                $_SESSION['after_login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
                header('Location: /login.php');
                exit;
            }
            return false;
        }
        $perms = $user['permissions'] ?? [];
        if (in_array($perm, $perms, true)) return true;

        if ($redirect) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
            echo "<h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p>";
            exit;
        }
        return false;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        start_session_safe();
        return !empty($_SESSION['user_id']) || !empty($_SESSION['user']);
    }
}
