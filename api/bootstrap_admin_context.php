<?php
/**
 * Lightweight Admin Context Bootstrap
 * Compatible with shared/free hosting
 * Fully loads $GLOBALS['ADMIN_DB'] and $GLOBALS['ADMIN_USER']
 * Includes 'is_active' for correct permissions display
 * PDO version only
 */

// ===== SESSION =====
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'cookie_samesite' => 'Lax',
        'name' => 'APP_SESSID'
    ]);
}

// ===== DB CONFIG (PDO) =====
$pdo = null;

// 1. Check if already connected via GLOBALS
if (!empty($GLOBALS['ADMIN_DB']) && $GLOBALS['ADMIN_DB'] instanceof PDO) {
    $pdo = $GLOBALS['ADMIN_DB'];
} 
// 2. Check in CONTAINER
elseif (!empty($GLOBALS['CONTAINER']['pdo']) && $GLOBALS['CONTAINER']['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['CONTAINER']['pdo'];
}
// 3. Try to load from shared config
else {
    $dbFile = __DIR__ . '/shared/config/db.php';
    
    if (is_file($dbFile)) {
        $dbConfig = include $dbFile;
        
        if (!empty($dbConfig['host']) && !empty($dbConfig['dbname']) && 
            !empty($dbConfig['username']) && isset($dbConfig['password'])) {
            
            try {
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
                
                // Set timezone to UTC
                $pdo->exec("SET time_zone = '+00:00'");
                
            } catch (PDOException $e) {
                error_log("Admin bootstrap PDO connection failed: " . $e->getMessage());
                $pdo = null;
            }
        }
    }
}

// ===== CURRENT USER =====
$currentUser = null;

// 1) from session user array (primary source)
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $currentUser = $_SESSION['user'];
    
    // Ensure is_active is boolean
    if (isset($currentUser['is_active'])) {
        $currentUser['is_active'] = !empty($currentUser['is_active']);
    } else {
        $currentUser['is_active'] = true; // Default value
    }
    
    // Load permissions from RBAC if available
    if (!empty($currentUser['id']) && $pdo && class_exists('RBAC')) {
        try {
            $rbac = new RBAC($pdo);
            $currentUser['permissions'] = $rbac->getUserPermissions($currentUser['id']);
            $currentUser['roles'] = $rbac->getUserRoles($currentUser['id']);
            $currentUser['permissions_count'] = count($currentUser['permissions']);
            $currentUser['roles_count'] = count($currentUser['roles']);
            
            // Update session with permissions
            $_SESSION['user'] = $currentUser;
            $_SESSION['permissions'] = $currentUser['permissions'];
            $_SESSION['roles'] = $currentUser['roles'];
            
        } catch (Throwable $e) {
            error_log("Failed to load RBAC permissions: " . $e->getMessage());
        }
    }
}

// 2) from session user_id (fetch from DB) - fallback
elseif (!empty($_SESSION['user_id']) && $pdo) {
    $uid = (int)$_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT id, username, email, role_id, preferred_language, 
                   is_active, timezone, created_at, updated_at
            FROM users 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$uid]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            $currentUser = [
                'id' => (int)$userData['id'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'role_id' => isset($userData['role_id']) ? (int)$userData['role_id'] : null,
                'preferred_language' => $userData['preferred_language'] ?? null,
                'is_active' => !empty($userData['is_active']),
                'timezone' => $userData['timezone'] ?? 'UTC',
                'created_at' => $userData['created_at'],
                'updated_at' => $userData['updated_at']
            ];
            
            // Load permissions from RBAC if available
            if (class_exists('RBAC')) {
                try {
                    $rbac = new RBAC($pdo);
                    $currentUser['permissions'] = $rbac->getUserPermissions($uid);
                    $currentUser['roles'] = $rbac->getUserRoles($uid);
                    $currentUser['permissions_count'] = count($currentUser['permissions']);
                    $currentUser['roles_count'] = count($currentUser['roles']);
                } catch (Throwable $e) {
                    error_log("RBAC failed for user {$uid}: " . $e->getMessage());
                    $currentUser['permissions'] = [];
                    $currentUser['roles'] = [];
                }
            } else {
                $currentUser['permissions'] = [];
                $currentUser['roles'] = [];
            }
            
            // Store in session
            $_SESSION['user'] = $currentUser;
        }
    } catch (Throwable $e) {
        error_log("Failed to fetch user from DB: " . $e->getMessage());
    }
}

// ===== GLOBAL EXPORT =====
$GLOBALS['ADMIN_DB']   = $pdo;
$GLOBALS['ADMIN_USER'] = $currentUser;

// Optional: expose for direct use
$ADMIN_DB   = $pdo;
$ADMIN_USER = $currentUser;

// ===== HELPER FUNCTIONS =====

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return !empty($GLOBALS['ADMIN_USER']);
}

/**
 * Check if current user is active
 */
function is_user_active(): bool {
    return !empty($GLOBALS['ADMIN_USER']['is_active']);
}

/**
 * Get current user ID
 */
function current_user_id(): ?int {
    return $GLOBALS['ADMIN_USER']['id'] ?? null;
}

/**
 * Get current username
 */
function current_username(): ?string {
    return $GLOBALS['ADMIN_USER']['username'] ?? null;
}

/**
 * Get current user role ID
 */
function current_user_role(): ?int {
    return $GLOBALS['ADMIN_USER']['role_id'] ?? null;
}

/**
 * Check if current user has a specific permission
 */
function has_permission(string $permission): bool {
    if (!empty($GLOBALS['ADMIN_USER']['role_id']) && $GLOBALS['ADMIN_USER']['role_id'] === 1) {
        return true; // Super admin has all permissions
    }
    
    return !empty($GLOBALS['ADMIN_USER']['permissions']) && 
           in_array($permission, $GLOBALS['ADMIN_USER']['permissions'], true);
}

/**
 * Require login - redirect if not logged in
 */
function require_login(string $redirectTo = '/admin/login.php'): void {
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Require permission - redirect if user doesn't have permission
 */
function require_permission(string $permission, string $redirectTo = '/admin/403.php'): void {
    require_login();
    
    if (!has_permission($permission)) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Get PDO connection
 */
function get_pdo(): ?PDO {
    return $GLOBALS['ADMIN_DB'] ?? null;
}

/**
 * Get current user permissions
 */
function get_user_permissions(): array {
    return $GLOBALS['ADMIN_USER']['permissions'] ?? [];
}

/**
 * Get current user roles
 */
function get_user_roles(): array {
    return $GLOBALS['ADMIN_USER']['roles'] ?? [];
}

// ===== DEBUG INFO (only in development) =====
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && !empty($_GET['debug'])) {
    echo '<pre style="background:#f0f0f0; padding:10px; margin:10px;">';
    echo '=== ADMIN CONTEXT DEBUG ===' . PHP_EOL;
    echo 'PDO Connected: ' . ($pdo ? 'Yes' : 'No') . PHP_EOL;
    echo 'User Logged In: ' . (is_logged_in() ? 'Yes' : 'No') . PHP_EOL;
    echo 'User ID: ' . current_user_id() . PHP_EOL;
    echo 'User Role: ' . current_user_role() . PHP_EOL;
    echo 'Permissions: ' . json_encode(get_user_permissions(), JSON_PRETTY_PRINT) . PHP_EOL;
    echo 'Roles: ' . json_encode(get_user_roles(), JSON_PRETTY_PRINT) . PHP_EOL;
    echo 'Session ID: ' . session_id() . PHP_EOL;
    echo '</pre>';
}
?>