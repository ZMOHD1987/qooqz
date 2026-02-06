<?php
declare(strict_types=1);

/**
 * /admin/includes/admin_context.php
 * Global Admin Context - Production Ready v2.0
 * 
 * Supports:
 * - role_permissions (traditional permission system)
 * - resource_permissions (granular resource-level permissions)
 * - Multi-tenant architecture
 * - Session-based authentication
 * - Database-driven roles and permissions
 * 
 * @version 2.0.0
 * @author Production Team
 */

// ════════════════════════════════════════════════════════════
// INITIALIZE ADMIN_UI FROM SESSION (ONCE)
// ════════════════════════════════════════════════════════════
if (!isset($GLOBALS['ADMIN_UI'])) {
    
    // ────────────────────────────────────────────────────────
    // START SESSION
    // ────────────────────────────────────────────────────────
    if (session_status() === PHP_SESSION_NONE) {
        $sessionConfig = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/config/session.php';
        if (file_exists($sessionConfig)) {
            require_once $sessionConfig;
        } else {
            session_start([
                'cookie_secure'   => !empty($_SERVER['HTTPS']),
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }
    
    // ────────────────────────────────────────────────────────
    // LOAD DATABASE CONNECTION
    // ────────────────────────────────────────────────────────
    if (!isset($GLOBALS['ADMIN_DB'])) {
        $dbConfigPath = $_SERVER['DOCUMENT_ROOT'] . '/api/shared/config/db.php';
        if (file_exists($dbConfigPath)) {
            require_once $dbConfigPath;
            
            try {
                if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                    $GLOBALS['ADMIN_DB'] = new PDO(
                        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                        DB_USER,
                        DB_PASS,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ]
                    );
                }
            } catch (PDOException $e) {
                error_log('[admin_context] Database connection failed: ' . $e->getMessage());
            }
        }
    }
    
    $pdo = $GLOBALS['ADMIN_DB'] ?? null;
    
    // ────────────────────────────────────────────────────────
    // GET CURRENT USER FROM SESSION
    // ────────────────────────────────────────────────────────
    $currentUser = $_SESSION['user'] ?? null;
    $userId = 0;
    $tenantId = 1;
    
    // Extract user ID and tenant ID from session
    if (!empty($currentUser) && is_array($currentUser)) {
        $userId = (int)($currentUser['id'] ?? 0);
    } elseif (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }
    
    // Get tenant ID from session
    if (isset($_SESSION['tenant_id'])) {
        $tenantId = (int)$_SESSION['tenant_id'];
    } elseif (!empty($currentUser['tenant_id'])) {
        $tenantId = (int)$currentUser['tenant_id'];
    }
    
    // ────────────────────────────────────────────────────────
    // LOAD USER DATA, ROLES, AND PERMISSIONS FROM DATABASE
    // ────────────────────────────────────────────────────────
    if ($userId > 0 && $pdo instanceof PDO) {
        
        // Check if we need to reload from database
        $needsReload = empty($_SESSION['roles']) 
            || empty($_SESSION['permissions']) 
            || empty($_SESSION['resource_permissions']);
        
        if ($needsReload) {
            error_log('[admin_context] Loading user data and permissions from database for user: ' . $userId);
            
            try {
                // ════════════════════════════════════════════════════════════
                // 1. GET USER DATA
                // ════════════════════════════════════════════════════════════
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $dbUser = $stmt->fetch();
                
                if (!$dbUser) {
                    error_log('[admin_context] User not found in database: ' . $userId);
                    session_destroy();
                    $userId = 0;
                } else {
                    
                    // ════════════════════════════════════════════════════════════
                    // 2. GET TENANT MEMBERSHIP AND ROLE
                    // ════════════════════════════════════════════════════════════
                    $stmt = $pdo->prepare("
                        SELECT tu.*, r.key_name as role_key_name
                        FROM tenant_users tu
                        LEFT JOIN roles r ON r.id = tu.role_id
                        WHERE tu.user_id = ? 
                        AND tu.tenant_id = ?
                        AND tu.is_active = 1
                        LIMIT 1
                    ");
                    $stmt->execute([$userId, $tenantId]);
                    $tenantUser = $stmt->fetch();
                    
                    if (!$tenantUser) {
                        error_log('[admin_context] User not member of tenant or inactive: User=' . $userId . ', Tenant=' . $tenantId);
                        $tenantUser = ['role_id' => null, 'role_key_name' => null];
                    }
                    
                    $roleId = $tenantUser['role_id'] ?? null;
                    $roleKeyName = $tenantUser['role_key_name'] ?? null;
                    
                    // ════════════════════════════════════════════════════════════
                    // 3. INITIALIZE PERMISSION ARRAYS
                    // ════════════════════════════════════════════════════════════
                    $roles = [];
                    $permissions = [];
                    $resourcePermissions = [];
                    $isSuperAdmin = false;
                    
                    if ($roleKeyName) {
                        $roles = [$roleKeyName];
                        $isSuperAdmin = ($roleKeyName === 'super_admin');
                    }
                    
                    // ════════════════════════════════════════════════════════════
                    // 4. LOAD ROLE-BASED PERMISSIONS (role_permissions)
                    // ════════════════════════════════════════════════════════════
                    if ($isSuperAdmin) {
                        // Super admin gets all permissions for tenant
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT key_name 
                            FROM permissions 
                            WHERE tenant_id = ? 
                            ORDER BY key_name
                        ");
                        $stmt->execute([$tenantId]);
                        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                    } elseif ($roleId) {
                        // Load specific role permissions
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT p.key_name
                            FROM permissions p
                            INNER JOIN role_permissions rp ON rp.permission_id = p.id
                            WHERE rp.role_id = ?
                            AND rp.tenant_id = ?
                            ORDER BY p.key_name
                        ");
                        $stmt->execute([$roleId, $tenantId]);
                        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    
                    // ════════════════════════════════════════════════════════════
                    // 5. LOAD RESOURCE PERMISSIONS (resource_permissions)
                    // ════════════════════════════════════════════════════════════
                    if ($roleId) {
                        $stmt = $pdo->prepare("
                            SELECT 
                                rp.resource_type,
                                rp.can_view_all,
                                rp.can_view_own,
                                rp.can_view_tenant,
                                rp.can_create,
                                rp.can_edit_all,
                                rp.can_edit_own,
                                rp.can_delete_all,
                                rp.can_delete_own,
                                p.key_name as permission_key
                            FROM resource_permissions rp
                            LEFT JOIN permissions p ON p.id = rp.permission_id
                            WHERE rp.role_id = ?
                            AND rp.tenant_id = ?
                            ORDER BY rp.resource_type
                        ");
                        $stmt->execute([$roleId, $tenantId]);
                        $resourcePerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Organize by resource type
                        foreach ($resourcePerms as $perm) {
                            $resourceType = $perm['resource_type'];
                            $resourcePermissions[$resourceType] = [
                                'can_view_all' => (bool)$perm['can_view_all'],
                                'can_view_own' => (bool)$perm['can_view_own'],
                                'can_view_tenant' => (bool)$perm['can_view_tenant'],
                                'can_create' => (bool)$perm['can_create'],
                                'can_edit_all' => (bool)$perm['can_edit_all'],
                                'can_edit_own' => (bool)$perm['can_edit_own'],
                                'can_delete_all' => (bool)$perm['can_delete_all'],
                                'can_delete_own' => (bool)$perm['can_delete_own'],
                                'permission_key' => $perm['permission_key'],
                            ];
                        }
                        
                        // Super admin gets full resource permissions
                        if ($isSuperAdmin && empty($resourcePermissions)) {
                            // Grant all resource permissions to super admin
                            $stmt = $pdo->prepare("SELECT DISTINCT resource_type FROM resource_permissions WHERE tenant_id = ?");
                            $stmt->execute([$tenantId]);
                            $resourceTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            foreach ($resourceTypes as $resourceType) {
                                $resourcePermissions[$resourceType] = [
                                    'can_view_all' => true,
                                    'can_view_own' => true,
                                    'can_view_tenant' => true,
                                    'can_create' => true,
                                    'can_edit_all' => true,
                                    'can_edit_own' => true,
                                    'can_delete_all' => true,
                                    'can_delete_own' => true,
                                    'permission_key' => 'super_admin',
                                ];
                            }
                        }
                    }
                    
                    // ════════════════════════════════════════════════════════════
                    // 6. STORE IN SESSION
                    // ════════════════════════════════════════════════════════════
                    $_SESSION['roles'] = $roles;
                    $_SESSION['permissions'] = $permissions;
                    $_SESSION['resource_permissions'] = $resourcePermissions;
                    $_SESSION['tenant_id'] = $tenantId;
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user'] = [
                        'id' => $dbUser['id'],
                        'username' => $dbUser['username'],
                        'email' => $dbUser['email'],
                        'preferred_language' => $dbUser['preferred_language'] ?? 'en',
                        'phone' => $dbUser['phone'] ?? null,
                        'timezone' => $dbUser['timezone'] ?? 'UTC',
                        'is_active' => (bool)($dbUser['is_active'] ?? true),
                    ];
                    
                    $currentUser = $_SESSION['user'];
                    
                    error_log('[admin_context] Loaded: ' . count($roles) . ' roles, ' 
                        . count($permissions) . ' permissions, ' 
                        . count($resourcePermissions) . ' resource permissions');
                }
                
            } catch (Exception $e) {
                error_log('[admin_context] Error loading permissions: ' . $e->getMessage());
                error_log('[admin_context] Stack trace: ' . $e->getTraceAsString());
            }
        }
    }
    
    // ────────────────────────────────────────────────────────
    // BUILD ADMIN_UI CONTEXT
    // ────────────────────────────────────────────────────────
    $hasUser = ($userId > 0) && !empty($currentUser) && is_array($currentUser);
    
    if ($hasUser) {
        $GLOBALS['ADMIN_UI'] = [
            'user' => [
                'id' => $currentUser['id'] ?? 0,
                'username' => $currentUser['username'] ?? 'guest',
                'email' => $currentUser['email'] ?? '',
                'roles' => $_SESSION['roles'] ?? [],
                'permissions' => $_SESSION['permissions'] ?? [],
                'resource_permissions' => $_SESSION['resource_permissions'] ?? [],
                'preferred_language' => $currentUser['preferred_language'] ?? 'en',
                'phone' => $currentUser['phone'] ?? null,
                'timezone' => $currentUser['timezone'] ?? 'UTC',
                'is_active' => $currentUser['is_active'] ?? true,
            ],
            'lang' => $_SESSION['preferred_language'] ?? $currentUser['preferred_language'] ?? 'en',
            'direction' => in_array(
                $_SESSION['preferred_language'] ?? $currentUser['preferred_language'] ?? 'en', 
                ['ar', 'fa', 'he', 'ur']
            ) ? 'rtl' : 'ltr',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'tenant_id' => $tenantId,
            'is_super_admin' => in_array('super_admin', $_SESSION['roles'] ?? [], true),
            'theme' => [
                'color_settings' => [],
                'font_settings' => [],
                'design_settings' => [],
                'button_styles' => [],
                'card_styles' => [],
                'generated_css' => '',
            ],
            'strings' => [],
            'settings' => [],
            'translation_path' => '/languages/admin/',
        ];
        
        // ════════════════════════════════════════════════════════════
        // LOAD THEME FROM DATABASE
        // ════════════════════════════════════════════════════════════
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM themes WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$tenantId]);
                $theme = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($theme) {
                    $themeId = $theme['id'];
                    
                    // Load theme color settings
                    $stmt = $pdo->prepare("SELECT * FROM theme_color_settings WHERE theme_id = ?");
                    $stmt->execute([$themeId]);
                    $GLOBALS['ADMIN_UI']['theme']['color_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Load theme font settings
                    $stmt = $pdo->prepare("SELECT * FROM theme_font_settings WHERE theme_id = ?");
                    $stmt->execute([$themeId]);
                    $GLOBALS['ADMIN_UI']['theme']['font_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Load theme design settings
                    $stmt = $pdo->prepare("SELECT * FROM theme_design_settings WHERE theme_id = ?");
                    $stmt->execute([$themeId]);
                    $GLOBALS['ADMIN_UI']['theme']['design_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Load button styles
                    $stmt = $pdo->prepare("SELECT * FROM theme_button_styles WHERE theme_id = ?");
                    $stmt->execute([$themeId]);
                    $GLOBALS['ADMIN_UI']['theme']['button_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Load card styles
                    $stmt = $pdo->prepare("SELECT * FROM theme_card_styles WHERE theme_id = ?");
                    $stmt->execute([$themeId]);
                    $GLOBALS['ADMIN_UI']['theme']['card_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $GLOBALS['ADMIN_UI']['theme']['generated_css'] = $theme['generated_css'] ?? '';
                }
            } catch (Throwable $e) {
                error_log('[admin_context] Theme load error: ' . $e->getMessage());
            }
        }
        
    } else {
        // ════════════════════════════════════════════════════════════
        // GUEST USER (NOT LOGGED IN)
        // ════════════════════════════════════════════════════════════
        $GLOBALS['ADMIN_UI'] = [
            'user' => [
                'id' => 0,
                'username' => 'guest',
                'email' => '',
                'roles' => [],
                'permissions' => [],
                'resource_permissions' => [],
                'preferred_language' => 'en',
                'phone' => null,
                'timezone' => 'UTC',
                'is_active' => false,
            ],
            'lang' => 'en',
            'direction' => 'ltr',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'tenant_id' => 1,
            'is_super_admin' => false,
            'theme' => [
                'color_settings' => [],
                'font_settings' => [],
                'design_settings' => [],
                'button_styles' => [],
                'card_styles' => [],
                'generated_css' => '',
            ],
            'strings' => [],
            'settings' => [],
            'translation_path' => '/languages/admin/',
        ];
    }
    
    // ────────────────────────────────────────────────────────
    // GENERATE CSRF TOKEN IF MISSING
    // ────────────────────────────────────────────────────────
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
        $GLOBALS['ADMIN_UI']['csrf_token'] = $_SESSION['csrf_token'];
    }
}

// ════════════════════════════════════════════════════════════
// HELPER FUNCTIONS - GENERAL
// ════════════════════════════════════════════════════════════

/**
 * Get entire admin context
 * 
 * @return array Complete admin context
 */
function admin_context(): array {
    return $GLOBALS['ADMIN_UI'] ?? [];
}

/**
 * Get current admin user
 * 
 * @return array User data
 */
function admin_user(): array {
    return $GLOBALS['ADMIN_UI']['user'] ?? [];
}

/**
 * Get user ID
 * 
 * @return int User ID (0 if not logged in)
 */
function admin_user_id(): int {
    return (int)(admin_user()['id'] ?? 0);
}

/**
 * Get username
 * 
 * @return string Username
 */
function admin_username(): string {
    return admin_user()['username'] ?? 'guest';
}

/**
 * Get user email
 * 
 * @return string Email address
 */
function admin_email(): string {
    return admin_user()['email'] ?? '';
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in
 */
function is_admin_logged_in(): bool {
    return admin_user_id() > 0;
}

/**
 * Get admin language
 * 
 * @return string Language code
 */
function admin_lang(): string {
    return $GLOBALS['ADMIN_UI']['lang'] ?? 'en';
}

/**
 * Get admin direction (RTL/LTR)
 * 
 * @return string 'rtl' or 'ltr'
 */
function admin_dir(): string {
    return $GLOBALS['ADMIN_UI']['direction'] ?? 'ltr';
}

/**
 * Get CSRF token
 * 
 * @return string CSRF token
 */
function admin_csrf(): string {
    return $GLOBALS['ADMIN_UI']['csrf_token'] ?? '';
}

/**
 * Get theme configuration
 * 
 * @return array Theme settings
 */
function admin_theme(): array {
    return $GLOBALS['ADMIN_UI']['theme'] ?? [];
}

/**
 * Get translation strings
 * 
 * @return array Translation strings
 */
function admin_strings(): array {
    return $GLOBALS['ADMIN_UI']['strings'] ?? [];
}

/**
 * Get tenant ID
 * 
 * @return int Tenant ID
 */
function admin_tenant_id(): int {
    return (int)($GLOBALS['ADMIN_UI']['tenant_id'] ?? 1);
}

/**
 * Get database connection
 * 
 * @return PDO|null Database connection or null
 */
function admin_db(): ?PDO {
    return $GLOBALS['ADMIN_DB'] ?? null;
}

// ════════════════════════════════════════════════════════════
// HELPER FUNCTIONS - ROLE-BASED PERMISSIONS
// ════════════════════════════════════════════════════════════

/**
 * Get user roles
 * 
 * @return array Array of role key names
 */
function admin_roles(): array {
    return admin_user()['roles'] ?? [];
}

/**
 * Get user permissions (role-based)
 * 
 * @return array Array of permission key names
 */
function admin_permissions(): array {
    return admin_user()['permissions'] ?? [];
}

/**
 * Check if user has a specific permission
 * 
 * @param string $permission Permission key name to check
 * @return bool True if user has permission
 */
function can(string $permission): bool {
    // Super admin has all permissions
    if (is_super_admin()) {
        return true;
    }
    
    return in_array($permission, admin_permissions(), true);
}

/**
 * Check if user has a specific role
 * 
 * @param string $role Role key name to check
 * @return bool True if user has role
 */
function has_role(string $role): bool {
    return in_array($role, admin_roles(), true);
}

/**
 * Check if user is super admin
 * 
 * @return bool True if super admin
 */
function is_super_admin(): bool {
    return $GLOBALS['ADMIN_UI']['is_super_admin'] ?? false;
}

/**
 * Check if user has any of the specified permissions
 * 
 * @param array $permissions Array of permission key names
 * @return bool True if user has at least one permission
 */
function can_any(array $permissions): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $userPermissions = admin_permissions();
    foreach ($permissions as $permission) {
        if (in_array($permission, $userPermissions, true)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if user has all of the specified permissions
 * 
 * @param array $permissions Array of permission key names
 * @return bool True if user has all permissions
 */
function can_all(array $permissions): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $userPermissions = admin_permissions();
    foreach ($permissions as $permission) {
        if (!in_array($permission, $userPermissions, true)) {
            return false;
        }
    }
    
    return true;
}

// ════════════════════════════════════════════════════════════
// HELPER FUNCTIONS - RESOURCE PERMISSIONS
// ════════════════════════════════════════════════════════════

/**
 * Get all resource permissions
 * 
 * @return array Resource permissions indexed by resource type
 */
function admin_resource_permissions(): array {
    return admin_user()['resource_permissions'] ?? [];
}

/**
 * Get permissions for a specific resource type
 * 
 * @param string $resourceType Resource type (e.g., 'posts', 'users', 'products')
 * @return array|null Resource permissions or null if not found
 */
function get_resource_permissions(string $resourceType): ?array {
    $resourcePerms = admin_resource_permissions();
    return $resourcePerms[$resourceType] ?? null;
}

/**
 * Check if user can view all resources of a type
 * 
 * @param string $resourceType Resource type
 * @return bool True if can view all
 */
function can_view_all(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_view_all'] ?? false;
}

/**
 * Check if user can view their own resources
 * 
 * @param string $resourceType Resource type
 * @return bool True if can view own
 */
function can_view_own(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_view_own'] ?? false;
}

/**
 * Check if user can view tenant resources
 * 
 * @param string $resourceType Resource type
 * @return bool True if can view tenant resources
 */
function can_view_tenant(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_view_tenant'] ?? false;
}

/**
 * Check if user can create resources
 * 
 * @param string $resourceType Resource type
 * @return bool True if can create
 */
function can_create(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_create'] ?? false;
}

/**
 * Check if user can edit all resources
 * 
 * @param string $resourceType Resource type
 * @return bool True if can edit all
 */
function can_edit_all(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_edit_all'] ?? false;
}

/**
 * Check if user can edit their own resources
 * 
 * @param string $resourceType Resource type
 * @return bool True if can edit own
 */
function can_edit_own(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_edit_own'] ?? false;
}

/**
 * Check if user can delete all resources
 * 
 * @param string $resourceType Resource type
 * @return bool True if can delete all
 */
function can_delete_all(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_delete_all'] ?? false;
}

/**
 * Check if user can delete their own resources
 * 
 * @param string $resourceType Resource type
 * @return bool True if can delete own
 */
function can_delete_own(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    return $perms['can_delete_own'] ?? false;
}

/**
 * Check if user can view a specific resource
 * 
 * @param string $resourceType Resource type
 * @param int $ownerId Owner user ID of the resource
 * @param int|null $resourceTenantId Tenant ID of the resource (optional)
 * @return bool True if can view the resource
 */
function can_view_resource(string $resourceType, int $ownerId, ?int $resourceTenantId = null): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $currentUserId = admin_user_id();
    $currentTenantId = admin_tenant_id();
    
    // Can view all
    if (can_view_all($resourceType)) {
        return true;
    }
    
    // Can view own and is owner
    if (can_view_own($resourceType) && $ownerId === $currentUserId) {
        return true;
    }
    
    // Can view tenant and belongs to same tenant
    if (can_view_tenant($resourceType) && $resourceTenantId === $currentTenantId) {
        return true;
    }
    
    return false;
}

/**
 * Check if user can edit a specific resource
 * 
 * @param string $resourceType Resource type
 * @param int $ownerId Owner user ID of the resource
 * @return bool True if can edit the resource
 */
function can_edit_resource(string $resourceType, int $ownerId): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $currentUserId = admin_user_id();
    
    // Can edit all
    if (can_edit_all($resourceType)) {
        return true;
    }
    
    // Can edit own and is owner
    if (can_edit_own($resourceType) && $ownerId === $currentUserId) {
        return true;
    }
    
    return false;
}

/**
 * Check if user can delete a specific resource
 * 
 * @param string $resourceType Resource type
 * @param int $ownerId Owner user ID of the resource
 * @return bool True if can delete the resource
 */
function can_delete_resource(string $resourceType, int $ownerId): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $currentUserId = admin_user_id();
    
    // Can delete all
    if (can_delete_all($resourceType)) {
        return true;
    }
    
    // Can delete own and is owner
    if (can_delete_own($resourceType) && $ownerId === $currentUserId) {
        return true;
    }
    
    return false;
}

/**
 * Check if user has any permission for a resource type
 * 
 * @param string $resourceType Resource type
 * @return bool True if has any permission
 */
function has_any_resource_permission(string $resourceType): bool {
    if (is_super_admin()) {
        return true;
    }
    
    $perms = get_resource_permissions($resourceType);
    if (!$perms) {
        return false;
    }
    
    return $perms['can_view_all'] 
        || $perms['can_view_own'] 
        || $perms['can_view_tenant']
        || $perms['can_create']
        || $perms['can_edit_all']
        || $perms['can_edit_own']
        || $perms['can_delete_all']
        || $perms['can_delete_own'];
}

// ════════════════════════════════════════════════════════════
// AUTHORIZATION HELPERS
// ════════════════════════════════════════════════════════════

/**
 * Require user to be logged in or redirect
 * 
 * @param string $redirectUrl URL to redirect to if not logged in
 * @return void
 */
function require_login(string $redirectUrl = '/admin/login.php'): void {
    if (!is_admin_logged_in()) {
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Require user to have a specific permission or die with 403
 * 
 * @param string $permission Permission key name
 * @param string $message Optional error message
 * @return void
 */
function require_permission(string $permission, string $message = 'Access denied'): void {
    if (!can($permission)) {
        http_response_code(403);
        die($message);
    }
}

/**
 * Require user to have a specific role or die with 403
 * 
 * @param string $role Role key name
 * @param string $message Optional error message
 * @return void
 */
function require_role(string $role, string $message = 'Access denied'): void {
    if (!has_role($role)) {
        http_response_code(403);
        die($message);
    }
}

/**
 * Require user to have resource permission or die with 403
 * 
 * @param string $resourceType Resource type
 * @param string $action Action type: 'view_all', 'view_own', 'create', 'edit_all', 'edit_own', 'delete_all', 'delete_own'
 * @param string $message Optional error message
 * @return void
 */
function require_resource_permission(string $resourceType, string $action, string $message = 'Access denied'): void {
    $hasPermission = false;
    
    switch ($action) {
        case 'view_all':
            $hasPermission = can_view_all($resourceType);
            break;
        case 'view_own':
            $hasPermission = can_view_own($resourceType);
            break;
        case 'view_tenant':
            $hasPermission = can_view_tenant($resourceType);
            break;
        case 'create':
            $hasPermission = can_create($resourceType);
            break;
        case 'edit_all':
            $hasPermission = can_edit_all($resourceType);
            break;
        case 'edit_own':
            $hasPermission = can_edit_own($resourceType);
            break;
        case 'delete_all':
            $hasPermission = can_delete_all($resourceType);
            break;
        case 'delete_own':
            $hasPermission = can_delete_own($resourceType);
            break;
    }
    
    if (!$hasPermission) {
        http_response_code(403);
        die($message);
    }
}

// ════════════════════════════════════════════════════════════
// LOG INITIALIZATION
// ════════════════════════════════════════════════════════════
error_log('[admin_context] ════════════════════════════════════════════');
error_log('[admin_context] Initialized for user: ' . admin_username() . ' (ID: ' . admin_user_id() . ')');
error_log('[admin_context] Tenant: ' . admin_tenant_id());
error_log('[admin_context] Roles: ' . (empty(admin_roles()) ? 'none' : implode(', ', admin_roles())));
error_log('[admin_context] Permissions: ' . count(admin_permissions()));
error_log('[admin_context] Resource Permissions: ' . count(admin_resource_permissions()));
error_log('[admin_context] Super Admin: ' . (is_super_admin() ? 'YES' : 'NO'));
error_log('[admin_context] ════════════════════════════════════════════');
