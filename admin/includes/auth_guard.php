<?php
/**
 * Authentication Guard
 * ملف حماية صفحات الإدارة
 * المسار: htdocs/admin/includes/auth_guard.php
 * 
 * الاستخدام:
 * require_once __DIR__ . '/includes/auth_guard.php';
 * 
 * يجب تضمين هذا الملف في أعلى كل صفحة إدارة محمية
 */

// بدء الجلسة إذا لم تكن بدأت
if (session_status() === PHP_SESSION_NONE) {
    // إعدادات جلسة آمنة
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    
    session_start();
}

/**
 * فحص إذا كان المستخدم مسجل دخول
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']) && 
           !empty($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true;
}

/**
 * فحص صلاحية الجلسة
 */
function isSessionValid(): bool
{
    // فحص انتهاء الصلاحية (ساعتان من عدم النشاط)
    $sessionTimeout = 7200; // ساعتان
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    
    if ($lastActivity > 0 && (time() - $lastActivity) > $sessionTimeout) {
        return false;
    }
    
    return true;
}

/**
 * فحص الصلاحيات
 */
function hasPermission(string $permission): bool
{
    if (empty($_SESSION['permissions'])) {
        return false;
    }
    
    return in_array($permission, $_SESSION['permissions'], true);
}

/**
 * فحص الدور
 */
function hasRole(int $roleId): bool
{
    return !empty($_SESSION['role_id']) && (int)$_SESSION['role_id'] === $roleId;
}

/**
 * فحص إذا كان المستخدم مدير (role_id = 1)
 */
function isAdmin(): bool
{
    return hasRole(1);
}

/**
 * تحديث آخر نشاط
 */
function updateLastActivity(): void
{
    $_SESSION['last_activity'] = time();
}

/**
 * إعادة التوجيه إلى صفحة تسجيل الدخول
 */
function redirectToLogin(string $message = ''): void
{
    // حذف الجلسة المنتهية
    $_SESSION = [];
    session_destroy();
    
    // حفظ رسالة في كوكي مؤقت
    if ($message) {
        setcookie('login_message', $message, time() + 60, '/');
    }
    
    // إعادة التوجيه
    header('Location: /admin/login.php');
    exit;
}

/**
 * جلب معلومات المستخدم الحالي
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role_id' => $_SESSION['role_id'] ?? 0,
        'role_name' => $_SESSION['role_name'] ?? '',
        'permissions' => $_SESSION['permissions'] ?? []
    ];
}

// ========================================
// الحماية التلقائية
// ========================================

// فحص تسجيل الدخول
if (!isLoggedIn()) {
    redirectToLogin('يجب تسجيل الدخول للوصول إلى هذه الصفحة');
}

// فحص صلاحية الجلسة
if (!isSessionValid()) {
    redirectToLogin('انتهت صلاحية الجلسة. سجل دخولك مرة أخرى');
}

// تحديث آخر نشاط
updateLastActivity();

// تعريف متغير عام للمستخدم الحالي
$currentUser = getCurrentUser();

// منع الوصول المباشر لهذا الملف
if (basename($_SERVER['PHP_SELF']) === 'auth_guard.php') {
    http_response_code(403);
    die('Access denied');
}
