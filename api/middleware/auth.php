<?php
// htdocs/api/middleware/auth.php
// ملف Middleware للمصادقة والتحقق من الصلاحيات
// يتحقق من JWT Token والصلاحيات

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/security.php';

// ===========================================
// AuthMiddleware Class
// ===========================================

class AuthMiddleware {
    
    private static $currentUser = null;
    
    // ===========================================
    // 1️⃣ المصادقة الأساسية (Basic Authentication)
    // ===========================================
    
    /**
     * التحقق من وجود المستخدم وصحة الـ Token
     * 
     * @return array بيانات المستخدم
     */
    public static function authenticate() {
        // الحصول على Token
        $token = JWT::getBearerToken();
        
        if (!$token) {
            Security::logSecurityEvent('auth_failed', 'No token provided');
            Response::unauthorized('Authentication token is required');
        }
        
        // فك تشفير Token
        $payload = JWT::decode($token);
        
        if ($payload === false) {
            Security::logSecurityEvent('auth_failed', 'Invalid or expired token');
            Response::unauthorized('Invalid or expired token');
        }
        
        // التحقق من نوع الـ Token
        if (!isset($payload['type']) || $payload['type'] !== 'access') {
            Security::logSecurityEvent('auth_failed', 'Invalid token type');
            Response::unauthorized('Invalid token type');
        }
        
        // التحقق من وجود user_id
        if (!isset($payload['user_id'])) {
            Security::logSecurityEvent('auth_failed', 'Missing user_id in token');
            Response::unauthorized('Invalid token payload');
        }
        
        $userId = $payload['user_id'];
        
        // جلب بيانات المستخدم من قاعدة البيانات
        $mysqli = connectDB();
        $user = self::getUserFromDatabase($mysqli, $userId);
        
        if (!$user) {
            Security::logSecurityEvent('auth_failed', "User not found: {$userId}");
            Response::unauthorized('User not found');
        }
        
        // التحقق من حالة المستخدم
        if (isset($user['status']) && $user['status'] !== USER_STATUS_ACTIVE) {
            Security::logSecurityEvent('auth_failed', "Inactive user: {$userId}");
            Response::forbidden('Your account is not active. Status: ' . $user['status']);
        }
        
        // حفظ بيانات المستخدم
        self::$currentUser = $user;
        
        // إضافة بيانات المستخدم للـ Request
        $_REQUEST['auth_user'] = $user;
        $_REQUEST['user_id'] = $userId;
        
        // تسجيل النشاط
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            if (function_exists('Utils') || class_exists('Utils')) {
                // attempt to use Utils::log if exists
                if (method_exists('Utils', 'log')) {
                    Utils::log("User authenticated: {$user['email']} (ID: {$userId})", 'AUTH');
                }
            }
        }
        
        return $user;
    }
    
    // ===========================================
    // 2️⃣ التحقق من الصلاحيات حسب نوع المستخدم
    // ===========================================
    
    /**
     * التحقق من أن المستخدم لديه صلاحية معينة
     * 
     * @param array $allowedRoles أنواع المستخدمين المسموح لهم
     * @return array بيانات المستخدم
     */
    public static function requireRole($allowedRoles = []) {
        // المصادقة أولاً
        $user = self::authenticate();
        
        // إذا لم تُحدد صلاحيات، السماح للكل
        if (empty($allowedRoles)) {
            return $user;
        }
        
        // التحقق من الصلاحية
        if (!in_array($user['user_type'], $allowedRoles)) {
            Security::logSecurityEvent(
                'authorization_failed',
                "User {$user['id']} ({$user['user_type']}) tried to access restricted resource"
            );
            
            Response::forbidden(
                'You do not have permission to access this resource. Required role: ' . 
                implode(', ', $allowedRoles)
            );
        }
        
        return $user;
    }
    
    public static function requireCustomer() {
        return self::requireRole([USER_TYPE_CUSTOMER]);
    }
    
    public static function requireVendor() {
        return self::requireRole([USER_TYPE_VENDOR]);
    }
    
    public static function requireAdmin() {
        return self::requireRole([USER_TYPE_ADMIN, USER_TYPE_SUPER_ADMIN]);
    }
    
    public static function requireSuperAdmin() {
        return self::requireRole([USER_TYPE_SUPER_ADMIN]);
    }
    
    public static function requireSupport() {
        return self::requireRole([USER_TYPE_SUPPORT, USER_TYPE_ADMIN, USER_TYPE_SUPER_ADMIN]);
    }
    
    // ===========================================
    // 3️⃣ التحقق من ملكية المورد
    // ===========================================
    
    public static function requireOwnership($resourceOwnerId) {
        $user = self::authenticate();
        
        // المدير يستطيع الوصول لكل شيء
        if (in_array($user['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUPER_ADMIN])) {
            return $user;
        }
        
        // التحقق من الملكية
        if ($user['id'] != $resourceOwnerId) {
            Security::logSecurityEvent(
                'ownership_violation',
                "User {$user['id']} tried to access resource owned by {$resourceOwnerId}"
            );
            
            Response::forbidden('You do not have permission to access this resource');
        }
        
        return $user;
    }
    
    public static function requireVendorOwnership($vendorId) {
        $user = self::requireVendor();
        
        // المدير يستطيع الوصول لكل شيء
        if (in_array($user['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUPER_ADMIN])) {
            return $user;
        }
        
        // جلب vendor_id الخاص بالمستخدم
        $mysqli = connectDB();
        $stmt = $mysqli->prepare("SELECT id FROM vendors WHERE user_id = ? AND status = ?");
        $activeStatus = VENDOR_STATUS_ACTIVE;
        $stmt->bind_param('is', $user['id'], $activeStatus);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            Response::forbidden('Vendor account not found or not active');
        }
        
        $vendor = $result->fetch_assoc();
        $stmt->close();
        
        if ($vendor['id'] != $vendorId) {
            Security::logSecurityEvent(
                'vendor_ownership_violation',
                "Vendor {$vendor['id']} tried to access resource owned by vendor {$vendorId}"
            );
            
            Response::forbidden('You do not have permission to access this vendor resource');
        }
        
        return $user;
    }
    
    // ===========================================
    // 4️⃣ مصادقة اختيارية (Optional Auth)
    // ===========================================
    
    public static function authenticateOptional() {
        $token = JWT::getBearerToken();
        
        if (!$token) {
            return null;
        }
        
        $payload = JWT::decode($token);
        
        if ($payload === false) {
            return null;
        }
        
        if (!isset($payload['user_id'])) {
            return null;
        }
        
        $mysqli = connectDB();
        $user = self::getUserFromDatabase($mysqli, $payload['user_id']);
        
        if ($user && isset($user['status']) && $user['status'] === USER_STATUS_ACTIVE) {
            self::$currentUser = $user;
            $_REQUEST['auth_user'] = $user;
            $_REQUEST['user_id'] = $user['id'];
            return $user;
        }
        
        return null;
    }
    
    // ===========================================
    // 5️⃣ التحقق من حساب محقق
    // ===========================================
    
    public static function requireVerified() {
        $user = self::authenticate();
        
        if (empty($user['is_verified'])) {
            Response::forbidden('Your account is not verified. Please verify your email/phone first.');
        }
        
        return $user;
    }
    
    // ===========================================
    // 6️⃣ Rate Limiting Middleware
    // ===========================================
    
    public static function applyRateLimit($limit = null, $window = null) {
        if (!defined('RATE_LIMIT_ENABLED') || ! RATE_LIMIT_ENABLED) {
            return;
        }
        
        $ip = Security::getRealIP();
        $result = Security::checkRateLimit($ip, $limit, $window);
        
        // إضافة Headers
        header('X-RateLimit-Limit: ' . ($limit ?? RATE_LIMIT_REQUESTS));
        header('X-RateLimit-Remaining: ' .  $result['remaining']);
        header('X-RateLimit-Reset:  ' . $result['reset_time']);
        
        if (! $result['allowed']) {
            Security::logSecurityEvent('rate_limit_exceeded', "IP: {$ip}");
            Response::tooManyRequests($result['retry_after']);
        }
    }
    
    // ===========================================
    // 7️⃣ الحصول على المستخدم الحالي
    // ===========================================
    
    public static function getCurrentUser() {
        return self::$currentUser;
    }
    
    public static function getCurrentUserId() {
        return self::$currentUser['id'] ?? null;
    }
    
    public static function getCurrentUserType() {
        return self::$currentUser['user_type'] ?? null;
    }
    
    public static function isAuthenticated() {
        return self::$currentUser !== null;
    }
    
    public static function isAdmin() {
        if (!self::$currentUser) {
            return false;
        }
        
        return in_array(
            self::$currentUser['user_type'],
            [USER_TYPE_ADMIN, USER_TYPE_SUPER_ADMIN]
        );
    }
    
    public static function isVendor() {
        if (!self::$currentUser) {
            return false;
        }
        
        return self::$currentUser['user_type'] === USER_TYPE_VENDOR;
    }
    
    public static function isCustomer() {
        if (!self::$currentUser) {
            return false;
        }
        
        return self::$currentUser['user_type'] === USER_TYPE_CUSTOMER;
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    private static function getUserFromDatabase($mysqli, $userId) {
        $sql = "SELECT 
                    id, 
                    username, 
                    email, 
                    phone, 
                    user_type, 
                    status, 
                    is_verified,
                    avatar,
                    created_at
                FROM users 
                WHERE id = ?";
        
        $stmt = $mysqli->prepare($sql);
        
        if (! $stmt) {
            if (function_exists('Utils') && method_exists('Utils', 'log')) {
                Utils::log("Database prepare failed: " . $mysqli->error, 'ERROR');
            }
            return null;
        }
        
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user;
    }
    
    public static function updateLastActivity($userId) {
        $mysqli = connectDB();
        
        $sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    public static function logLogin($userId, $ipAddress, $userAgent) {
        $mysqli = connectDB();
        
        $sql = "INSERT INTO user_login_history (user_id, ip_address, user_agent, login_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $mysqli->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param('iss', $userId, $ipAddress, $userAgent);
            $stmt->execute();
            $stmt->close();
        }
        
        Security::logSecurityEvent('login_success', "User ID: {$userId}, IP: {$ipAddress}");
    }
    
    public static function isSessionActive($userId, $token) {
        $mysqli = connectDB();
        
        $sql = "SELECT id FROM user_sessions 
                WHERE user_id = ? 
                AND token = ? 
                AND is_active = 1 
                AND expires_at > NOW()";
        
        $stmt = $mysqli->prepare($sql);
        
        if (! $stmt) {
            return false;
        }
        
        $tokenHash = hash('sha256', $token);
        $stmt->bind_param('is', $userId, $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $active = $result->num_rows > 0;
        $stmt->close();
        
        return $active;
    }
    
    public static function terminateAllSessions($userId) {
        $mysqli = connectDB();
        
        $sql = "UPDATE user_sessions SET is_active = 0 WHERE user_id = ?";
        $stmt = $mysqli->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        
        Security::logSecurityEvent('sessions_terminated', "User ID: {$userId}");
    }
}

// ===========================================
// دوال مساعدة عامة (Global Helper Functions)
// ===========================================

function auth() {
    return AuthMiddleware::getCurrentUser();
}

function authId() {
    return AuthMiddleware::getCurrentUserId();
}

function isAuth() {
    return AuthMiddleware::isAuthenticated();
}

function isAdmin() {
    return AuthMiddleware::isAdmin();
}

function isVendor() {
    return AuthMiddleware::isVendor();
}

function isCustomer() {
    return AuthMiddleware::isCustomer();
}

// ===========================================
// ✅ تم تحميل Auth Middleware بنجاح
// ===========================================
?>