<?php
declare(strict_types=1);

/**
 * /admin/includes/admin_context.php
 * Global Admin Context - Single source of truth
 * Updated for: roles, permissions, role_permissions, resource_permissions
 */

// ════════════════════════════════════════════════════════════
// INITIALIZE ADMIN_UI FROM SESSION (ONCE)
// ════════════════════════════════════════════════════════════
if (!isset($GLOBALS['ADMIN_UI'])) {
    
    // Start session if needed
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
    
    // ════════════════════════════════════════════════════════════
    // LOAD DATABASE CONNECTION
    // ════════════════════════════════════════════════════════════
    
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
    
    // ════════════════════════════════════════════════════════════
    // BUILD FROM SESSION OR DATABASE
    // ════════════════════════════════════════════════════════════
    
    $currentUser = $_SESSION['user'] ?? null;
    $hasUser = !empty($currentUser) && is_array($currentUser);
    $userId = $currentUser['id'] ?? ($_SESSION['user_id'] ?? 0);
    
    // If user logged in but roles/permissions not loaded, load from DB
    if ($userId > 0 && $pdo instanceof PDO) {
        
        // Check if roles/permissions need to be loaded
        $needsReload = empty($_SESSION['roles']) || empty($_SESSION['permissions']);
        
        if ($needsReload) {
            error_log('[admin_context] Loading roles and permissions from database for user: ' . $userId);
            
            try {
                // Get user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $dbUser = $stmt->fetch();
                
                if ($dbUser && $dbUser['role_id']) {
                    
                    // Get role
                    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
                    $stmt->execute([$dbUser['role_id']]);
                    $role = $stmt->fetch();
                    
                    $roles = [];
                    $permissions = [];
                    $isSuperAdmin = false;
                    
                    if ($role) {
                        $roleKeyName = $role['key_name'];
                        $roles = [$roleKeyName];
                        $isSuperAdmin = ($roleKeyName === 'super_admin');
                        
                        // Get permissions
                        if ($isSuperAdmin) {
                            // Super admin gets all permissions
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT key_name 
                                FROM permissions 
                                WHERE tenant_id = ? 
                                ORDER BY key_name
                            ");
                            $stmt->execute([$dbUser['tenant_id']]);
                            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                        } else {
                            // Load role permissions
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT p.key_name
                                FROM permissions p
                                INNER JOIN role_permissions rp ON rp.permission_id = p.id
                                WHERE rp.role_id = ?
                                AND rp.tenant_id = ?
                                ORDER BY p.key_name
                            ");
                            $stmt->execute([$dbUser['role_id'], $dbUser['tenant_id']]);
                            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        }
                    }
                    
                    // Store in session
                    $_SESSION['roles'] = $roles;
                    $_SESSION['permissions'] = $permissions;
                    $_SESSION['user'] = [
                        'id' => $dbUser['id'],
                        'username' => $dbUser['username'],
                        'email' => $dbUser['email'],
                        'role_id' => $dbUser['role_id'],
                        'tenant_id' => $dbUser['tenant_id'],
                        'preferred_language' => $dbUser['preferred_language'] ?? 'en',
                        'avatar' => '/admin/assets/img/default-avatar.png',
                    ];
                    
                    $currentUser = $_SESSION['user'];
                    $hasUser = true;
                    
                    error_log('[admin_context] Loaded ' . count($roles) . ' roles and ' . count($permissions) . ' permissions');
                }
                
            } catch (Exception $e) {
                error_log('[admin_context] Error loading roles/permissions: ' . $e->getMessage());
            }
        }
    }
    
    // ════════════════════════════════════════════════════════════
    // BUILD ADMIN_UI PAYLOAD
    // ════════════════════════════════════════════════════════════
    
    if ($hasUser) {
        $GLOBALS['ADMIN_UI'] = [
            'user' => [
                'id' => $currentUser['id'] ?? 0,
                'username' => $currentUser['username'] ?? 'guest',
                'email' => $currentUser['email'] ?? '',
                'roles' => $_SESSION['roles'] ?? [],
                'permissions' => $_SESSION['permissions'] ?? [],
                'avatar' => $currentUser['avatar'] ?? '/admin/assets/img/default-avatar.png',
                'preferred_language' => $currentUser['preferred_language'] ?? 'en',
                'role_id' => $currentUser['role_id'] ?? null,
                'tenant_id' => $currentUser['tenant_id'] ?? 1,
            ],
            'lang' => $_SESSION['preferred_language'] ?? $currentUser['preferred_language'] ?? 'en',
            'direction' => in_array($_SESSION['preferred_language'] ?? 'en', ['ar','fa','he','ur']) ? 'rtl' : 'ltr',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'tenant_id' => $_SESSION['tenant_id'] ?? $currentUser['tenant_id'] ?? 1,
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
        
        // Load theme from database
        if ($pdo instanceof PDO) {
            try {
                $tenantId = $GLOBALS['ADMIN_UI']['tenant_id'];
                $stmt = $pdo->prepare("SELECT * FROM themes WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$tenantId]);
                $theme = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($theme) {
                    $stmt = $pdo->prepare("SELECT * FROM theme_color_settings WHERE theme_id = ?");
                    $stmt->execute([$theme['id']]);
                    $GLOBALS['ADMIN_UI']['theme']['color_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("SELECT * FROM theme_font_settings WHERE theme_id = ?");
                    $stmt->execute([$theme['id']]);
                    $GLOBALS['ADMIN_UI']['theme']['font_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("SELECT * FROM theme_design_settings WHERE theme_id = ?");
                    $stmt->execute([$theme['id']]);
                    $GLOBALS['ADMIN_UI']['theme']['design_settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("SELECT * FROM theme_button_styles WHERE theme_id = ?");
                    $stmt->execute([$theme['id']]);
                    $GLOBALS['ADMIN_UI']['theme']['button_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("SELECT * FROM theme_card_styles WHERE theme_id = ?");
                    $stmt->execute([$theme['id']]);
                    $GLOBALS['ADMIN_UI']['theme']['card_styles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $GLOBALS['ADMIN_UI']['theme']['generated_css'] = $theme['generated_css'] ?? '';
                }
            } catch (Throwable $e) {
                error_log('[admin_context] Theme load error: ' . $e->getMessage());
            }
        }
        
    } else {
        // Guest user
        $GLOBALS['ADMIN_UI'] = [
            'user' => [
                'id' => 0,
                'username' => 'guest',
                'email' => '',
                'roles' => [],
                'permissions' => [],
                'avatar' => '/admin/assets/img/default-avatar.png',
                'preferred_language' => 'en',
                'role_id' => null,
                'tenant_id' => 1,
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
    
    // Generate CSRF if missing
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
// HELPER FUNCTIONS
// ════════════════════════════════════════════════════════════

/**
 * Get entire admin context
 */
function admin_context(): array {
    return $GLOBALS['ADMIN_UI'] ?? [];
}

/**
 * Get current admin user
 */
function admin_user(): array {
    return $GLOBALS['ADMIN_UI']['user'] ?? [];
}

/**
 * Get user roles
 */
function admin_roles(): array {
    return admin_user()['roles'] ?? [];
}

/**
 * Get user permissions
 */
function admin_permissions(): array {
    return admin_user()['permissions'] ?? [];
}

/**
 * Check if user has permission
 */
function can(string $permission): bool {
    return in_array($permission, admin_permissions(), true)
        || in_array('super_admin', admin_roles(), true);
}

/**
 * Check if user has role
 */
function has_role(string $role): bool {
    return in_array($role, admin_roles(), true);
}

/**
 * Check if user is super admin
 */
function is_super_admin(): bool {
    return $GLOBALS['ADMIN_UI']['is_super_admin'] ?? false;
}

/**
 * Get admin language
 */
function admin_lang(): string {
    return $GLOBALS['ADMIN_UI']['lang'] ?? 'en';
}

/**
 * Get admin direction
 */
function admin_dir(): string {
    return $GLOBALS['ADMIN_UI']['direction'] ?? 'ltr';
}

/**
 * Get CSRF token
 */
function admin_csrf(): string {
    return $GLOBALS['ADMIN_UI']['csrf_token'] ?? '';
}

/**
 * Get theme
 */
function admin_theme(): array {
    return $GLOBALS['ADMIN_UI']['theme'] ?? [];
}

/**
 * Get user ID
 */
function admin_user_id(): int {
    return (int)(admin_user()['id'] ?? 0);
}

/**
 * Get username
 */
function admin_username(): string {
    return admin_user()['username'] ?? 'guest';
}

/**
 * Check if user is logged in
 */
function is_admin_logged_in(): bool {
    return admin_user_id() > 0;
}

/**
 * Get translation strings
 */
function admin_strings(): array {
    return $GLOBALS['ADMIN_UI']['strings'] ?? [];
}

/**
 * Get tenant ID
 */
function admin_tenant_id(): int {
    return (int)($GLOBALS['ADMIN_UI']['tenant_id'] ?? 1);
}

/**
 * Get database connection
 */
function admin_db(): ?PDO {
    return $GLOBALS['ADMIN_DB'] ?? null;
}

// ════════════════════════════════════════════════════════════
// LOG INITIALIZATION
// ════════════════════════════════════════════════════════════
error_log('[admin_context] Initialized for user: ' . admin_username() . ' (ID: ' . admin_user_id() . ')');
error_log('[admin_context] Roles: ' . implode(', ', admin_roles()));
error_log('[admin_context] Permissions: ' . count(admin_permissions()));