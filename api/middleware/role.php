<?php
// htdocs/api/middleware/role.php
// ملف Middleware للتحقق من الصلاحيات التفصيلية
// يدعم RBAC (Role-Based Access Control) المتقدم
// تم التعديل لدعم PDO

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/auth.php';

// ===========================================
// RoleMiddleware Class
// ===========================================

class RoleMiddleware {
    
    // Cache للصلاحيات
    private static $permissionsCache = [];
    private static ?PDO $pdo = null;
    
    /**
     * تعيين PDO instance
     * 
     * @param PDO $pdo
     */
    public static function setPDO(PDO $pdo) {
        self::$pdo = $pdo;
    }
    
    // ===========================================
    // 1️⃣ التحقق من صلاحية محددة
    // ===========================================
    
    /**
     * التحقق من أن المستخدم لديه صلاحية معينة
     * 
     * @param string $module اسم الموديول (products, orders, users, etc.)
     * @param string $action العملية (create, read, update, delete)
     * @return array بيانات المستخدم
     */
    public static function requirePermission($module, $action) {
        $user = AuthMiddleware::authenticate();
        
        // Super Admin لديه كل الصلاحيات
        if ($user['user_type'] === USER_TYPE_SUPER_ADMIN) {
            return $user;
        }
        
        // التحقق من الصلاحية
        if (!self::hasPermission($user['user_type'], $module, $action)) {
            Security::logSecurityEvent(
                'permission_denied',
                "User {$user['id']} ({$user['user_type']}) tried to {$action} on {$module}"
            );
            
            Response::forbidden(
                "You do not have permission to {$action} {$module}"
            );
        }
        
        return $user;
    }
    
    /**
     * التحقق من صلاحية القراءة
     * 
     * @param string $module
     * @return array
     */
    public static function canRead($module) {
        return self::requirePermission($module, 'read');
    }
    
    /**
     * التحقق من صلاحية الإنشاء
     * 
     * @param string $module
     * @return array
     */
    public static function canCreate($module) {
        return self::requirePermission($module, 'create');
    }
    
    /**
     * التحقق من صلاحية التحديث
     * 
     * @param string $module
     * @return array
     */
    public static function canUpdate($module) {
        return self::requirePermission($module, 'update');
    }
    
    /**
     * التحقق من صلاحية الحذف
     * 
     * @param string $module
     * @return array
     */
    public static function canDelete($module) {
        return self::requirePermission($module, 'delete');
    }
    
    // ===========================================
    // 2️⃣ التحقق من عدة صلاحيات
    // ===========================================
    
    /**
     * التحقق من ص��احيات متعددة (يجب أن يملك الكل)
     * 
     * @param array $permissions [['module' => 'products', 'action' => 'create'], ...]
     * @return array
     */
    public static function requireAllPermissions($permissions) {
        $user = AuthMiddleware::authenticate();
        
        // Super Admin لديه كل الصلاحيات
        if ($user['user_type'] === USER_TYPE_SUPER_ADMIN) {
            return $user;
        }
        
        $missingPermissions = [];
        
        foreach ($permissions as $perm) {
            if (!self::hasPermission($user['user_type'], $perm['module'], $perm['action'])) {
                $missingPermissions[] = "{$perm['action']} on {$perm['module']}";
            }
        }
        
        if (! empty($missingPermissions)) {
            Security::logSecurityEvent(
                'multiple_permissions_denied',
                "User {$user['id']} missing:  " . implode(', ', $missingPermissions)
            );
            
            Response::forbidden(
                'Missing required permissions: ' . implode(', ', $missingPermissions)
            );
        }
        
        return $user;
    }
    
    /**
     * التحقق من صلاحيات متعددة (يكفي واحدة)
     * 
     * @param array $permissions
     * @return array
     */
    public static function requireAnyPermission($permissions) {
        $user = AuthMiddleware:: authenticate();
        
        // Super Admin لديه كل الصلاحيات
        if ($user['user_type'] === USER_TYPE_SUPER_ADMIN) {
            return $user;
        }
        
        foreach ($permissions as $perm) {
            if (self::hasPermission($user['user_type'], $perm['module'], $perm['action'])) {
                return $user;
            }
        }
        
        Security::logSecurityEvent(
            'no_permission_matched',
            "User {$user['id']} has none of the required permissions"
        );
        
        Response::forbidden('You do not have any of the required permissions');
    }
    
    // ===========================================
    // 3️⃣ صلاحيات خاصة بالموديولات
    // ===========================================
    
    /**
     * صلاحيات المنتجات
     */
    public static function canManageProducts() {
        return self::requireAnyPermission([
            ['module' => 'products', 'action' => 'create'],
            ['module' => 'products', 'action' => 'update'],
            ['module' => 'products', 'action' => 'delete']
        ]);
    }
    
    /**
     * صلاحيات الطلبات
     */
    public static function canManageOrders() {
        return self::requireAnyPermission([
            ['module' => 'orders', 'action' => 'update'],
            ['module' => 'orders', 'action' => 'delete']
        ]);
    }
    
    /**
     * صلاحيات المستخدمين
     */
    public static function canManageUsers() {
        return self::requireAnyPermission([
            ['module' => 'users', 'action' => 'create'],
            ['module' => 'users', 'action' => 'update'],
            ['module' => 'users', 'action' => 'delete']
        ]);
    }
    
    /**
     * صلاحيات التجار
     */
    public static function canManageVendors() {
        return self::requireAnyPermission([
            ['module' => 'vendors', 'action' => 'update'],
            ['module' => 'vendors', 'action' => 'delete']
        ]);
    }
    
    /**
     * صلاحيات التصنيفات
     */
    public static function canManageCategories() {
        return self::requireAnyPermission([
            ['module' => 'categories', 'action' => 'create'],
            ['module' => 'categories', 'action' => 'update'],
            ['module' => 'categories', 'action' => 'delete']
        ]);
    }
    
    /**
     * صلاحيات الكوبونات
     */
    public static function canManageCoupons() {
        return self::requireAnyPermission([
            ['module' => 'coupons', 'action' => 'create'],
            ['module' => 'coupons', 'action' => 'update'],
            ['module' => 'coupons', 'action' => 'delete']
        ]);
    }
    
    /**
     * صلاحيات التقارير
     */
    public static function canViewReports() {
        return self::requirePermission('reports', 'read');
    }
    
    /**
     * صلاحيات الإعدادات
     */
    public static function canManageSettings() {
        return self::requireAllPermissions([
            ['module' => 'settings', 'action' => 'read'],
            ['module' => 'settings', 'action' => 'update']
        ]);
    }
    
    // ===========================================
    // 4️⃣ فحص الصلاحيات (Check Permissions)
    // ===========================================
    
    /**
     * التحقق من صلاحية بدون throw exception
     * 
     * @param string $userType
     * @param string $module
     * @param string $action
     * @return bool
     */
    public static function hasPermission($userType, $module, $action) {
        // التحقق من الـ cache
        $cacheKey = "{$userType}:{$module}:{$action}";
        
        if (isset(self::$permissionsCache[$cacheKey])) {
            return self::$permissionsCache[$cacheKey];
        }
        
        if (!self::$pdo) return false;
        
        try {
            $stmt = self::$pdo->prepare("SELECT can_{$action} FROM role_permissions WHERE role = ? AND module = ?");
            $stmt->execute([$userType, $module]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $hasPermission = $row ? (bool)$row["can_{$action}"] : false;
        } catch (PDOException $e) {
            Utils::log("Permission check failed: " . $e->getMessage(), 'ERROR');
            $hasPermission = false;
        }
        
        // حفظ في الـ cache
        self::$permissionsCache[$cacheKey] = $hasPermission;
        
        return $hasPermission;
    }
    
    /**
     * الحصول على جميع صلاحيات دور معين
     * 
     * @param string $userType
     * @return array
     */
    public static function getRolePermissions($userType) {
        if (!self::$pdo) return [];
        
        try {
            $stmt = self::$pdo->prepare("
                SELECT module, can_create, can_read, can_update, can_delete 
                FROM role_permissions WHERE role = ?
            ");
            $stmt->execute([$userType]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $permissions = [];
            foreach ($rows as $row) {
                $permissions[$row['module']] = [
                    'create' => (bool)$row['can_create'],
                    'read' => (bool)$row['can_read'],
                    'update' => (bool)$row['can_update'],
                    'delete' => (bool)$row['can_delete']
                ];
            }
            
            return $permissions;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * الحصول على صلاحيات المستخدم الحالي
     * 
     * @return array
     */
    public static function getCurrentUserPermissions() {
        $user = AuthMiddleware::getCurrentUser();
        
        if (! $user) {
            return [];
        }
        
        if ($user['user_type'] === USER_TYPE_SUPER_ADMIN) {
            return self::getAllPermissions();
        }
        
        return self::getRolePermissions($user['user_type']);
    }
    
    /**
     * الحصول على جميع الصلاحيات (للـ Super Admin)
     * 
     * @return array
     */
    private static function getAllPermissions() {
        return [
            'users' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'vendors' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'products' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'categories' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'orders' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'coupons' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'reports' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'settings' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'reviews' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
            'support' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true]
        ];
    }
    
    // ===========================================
    // 5️⃣ إدارة الصلاحيات (Manage Permissions)
    // ===========================================
    
    /**
     * تحديث صلاحية
     * 
     * @param string $role
     * @param string $module
     * @param string $action
     * @param bool $value
     * @return bool
     */
    public static function updatePermission($role, $module, $action, $value) {
        // يجب أن يكون المستخدم Super Admin
        $user = AuthMiddleware::requireSuperAdmin();
        
        if (!self::$pdo) return false;
        
        try {
            $column = "can_{$action}";
            $stmt = self::$pdo->prepare("UPDATE role_permissions SET {$column} = ? WHERE role = ? AND module = ?");
            $stmt->execute([$value ? 1 : 0, $role, $module]);
            $success = $stmt->rowCount() > 0;
            
            if ($success) {
                // مسح الـ cache
                $cacheKey = "{$role}:{$module}:{$action}";
                unset(self::$permissionsCache[$cacheKey]);
                
                Security::logSecurityEvent(
                    'permission_updated',
                    "Role:  {$role}, Module: {$module}, Action: {$action}, Value: {$value}"
                );
            }
            
            return $success;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * إنشاء صلاحيات لدور جديد
     * 
     * @param string $role
     * @param array $modules
     * @return bool
     */
    public static function createRolePermissions($role, $modules = []) {
        $user = AuthMiddleware::requireSuperAdmin();
        
        if (!self::$pdo) return false;
        
        // الموديولات الافتراضية
        if (empty($modules)) {
            $modules = [
                'users', 'vendors', 'products', 'categories', 
                'orders', 'coupons', 'reports', 'settings', 
                'reviews', 'support'
            ];
        }
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO role_permissions (role, module, can_create, can_read, can_update, can_delete) 
                VALUES (?, ?, 0, 1, 0, 0)
                ON DUPLICATE KEY UPDATE role = role
            ");
            
            $success = true;
            foreach ($modules as $module) {
                $stmt->execute([$role, $module]);
                $success = $success && $stmt->rowCount() > 0;
            }
            
            if ($success) {
                Security::logSecurityEvent('role_created', "Role: {$role}");
            }
            
            return $success;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * نسخ صلاحيات من دور لآخر
     * 
     * @param string $fromRole
     * @param string $toRole
     * @return bool
     */
    public static function copyPermissions($fromRole, $toRole) {
        $user = AuthMiddleware::requireSuperAdmin();
        
        $permissions = self::getRolePermissions($fromRole);
        
        if (empty($permissions) || !self::$pdo) {
            return false;
        }
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO role_permissions (role, module, can_create, can_read, can_update, can_delete) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    can_create = VALUES(can_create),
                    can_read = VALUES(can_read),
                    can_update = VALUES(can_update),
                    can_delete = VALUES(can_delete)
            ");
            
            $success = true;
            foreach ($permissions as $module => $perms) {
                $stmt->execute([
                    $toRole,
                    $module,
                    $perms['create'] ? 1 : 0,
                    $perms['read'] ? 1 : 0,
                    $perms['update'] ? 1 : 0,
                    $perms['delete'] ? 1 : 0
                ]);
                $success = $success && $stmt->rowCount() > 0;
            }
            
            if ($success) {
                Security::logSecurityEvent(
                    'permissions_copied',
                    "From:  {$fromRole}, To: {$toRole}"
                );
            }
            
            return $success;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // ===========================================
    // 6️⃣ تهيئة الصلاحيات الافتراضية
    // ===========================================
    
    /**
     * تهيئة صلاحيات افتراضية لجميع الأدوار
     * 
     * @return bool
     */
    public static function initializeDefaultPermissions() {
        if (!self::$pdo) return false;
        
        $defaultPermissions = [
            // Super Admin - كل شيء
            USER_TYPE_SUPER_ADMIN => [
                'users' => [1, 1, 1, 1],
                'vendors' => [1, 1, 1, 1],
                'products' => [1, 1, 1, 1],
                'categories' => [1, 1, 1, 1],
                'orders' => [1, 1, 1, 1],
                'coupons' => [1, 1, 1, 1],
                'reports' => [1, 1, 1, 1],
                'settings' => [1, 1, 1, 1],
                'reviews' => [1, 1, 1, 1],
                'support' => [1, 1, 1, 1]
            ],
            
            // Admin - معظم الصلاحيات
            USER_TYPE_ADMIN => [
                'users' => [1, 1, 1, 0],
                'vendors' => [1, 1, 1, 0],
                'products' => [1, 1, 1, 1],
                'categories' => [1, 1, 1, 1],
                'orders' => [0, 1, 1, 0],
                'coupons' => [1, 1, 1, 1],
                'reports' => [0, 1, 0, 0],
                'settings' => [0, 1, 1, 0],
                'reviews' => [0, 1, 1, 1],
                'support' => [1, 1, 1, 0]
            ],
            
            // Vendor - صلاحيات التاجر
            USER_TYPE_VENDOR => [
                'products' => [1, 1, 1, 1],
                'orders' => [0, 1, 1, 0],
                'reviews' => [0, 1, 0, 0],
                'reports' => [0, 1, 0, 0]
            ],
            
            // Customer - صلاحيات العميل
            USER_TYPE_CUSTOMER => [
                'products' => [0, 1, 0, 0],
                'orders' => [1, 1, 0, 0],
                'reviews' => [1, 1, 1, 1],
                'support' => [1, 1, 0, 0]
            ],
            
            // Support - صلاحيات الدعم
            USER_TYPE_SUPPORT => [
                'users' => [0, 1, 0, 0],
                'orders' => [0, 1, 1, 0],
                'support' => [1, 1, 1, 0],
                'reviews' => [0, 1, 1, 0]
            ],
            
            // Moderator - صلاحيات المشرف
            USER_TYPE_MODERATOR => [
                'products' => [0, 1, 1, 0],
                'reviews' => [0, 1, 1, 1],
                'support' => [0, 1, 1, 0]
            ]
        ];
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO role_permissions (role, module, can_create, can_read, can_update, can_delete) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    can_create = VALUES(can_create),
                    can_read = VALUES(can_read),
                    can_update = VALUES(can_update),
                    can_delete = VALUES(can_delete)
            ");
            
            $success = true;
            
            foreach ($defaultPermissions as $role => $modules) {
                foreach ($modules as $module => $perms) {
                    $stmt->execute([$role, $module, $perms[0], $perms[1], $perms[2], $perms[3]]);
                    $success = $success && $stmt->rowCount() > 0;
                }
            }
            
            if ($success) {
                Utils::log("Default permissions initialized successfully", 'INFO');
            }
            
            return $success;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// ===========================================
// دوال مساعدة عامة (Global Helper Functions)
// ===========================================

/**
 * التحقق من صلاحية
 * 
 * @param string $module
 * @param string $action
 * @return bool
 */
function can($module, $action) {
    $user = AuthMiddleware:: getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    if ($user['user_type'] === USER_TYPE_SUPER_ADMIN) {
        return true;
    }
    
    return RoleMiddleware::hasPermission($user['user_type'], $module, $action);
}

/**
 * التحقق من صلاحية القراءة
 * 
 * @param string $module
 * @return bool
 */
function canRead($module) {
    return can($module, 'read');
}

/**
 * التحقق من صلاحية الإنشاء
 * 
 * @param string $module
 * @return bool
 */
function canCreate($module) {
    return can($module, 'create');
}

/**
 * التحقق من صلاحية التحديث
 * 
 * @param string $module
 * @return bool
 */
function canUpdate($module) {
    return can($module, 'update');
}

/**
 * التحقق من صلاحية الحذف
 * 
 * @param string $module
 * @return bool
 */
function canDelete($module) {
    return can($module, 'delete');
}

// ===========================================
// ✅ تم تحميل Role Middleware بنجاح
// ===========================================

?>