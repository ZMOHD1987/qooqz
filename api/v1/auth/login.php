<?php
declare(strict_types=1);
/**
 * Login API - نظام تسجيل الدخول المتكامل
 * المسار: htdocs/api/users/login.php
 * 
 * المميزات:
 * - لا يعتمد على أي ملفات خارجية
 * - يدعم تسجيل الدخول بـ: البريد الإلكتروني / اسم المستخدم / رقم الهاتف
 * - نظام جلسات آمن
 * - تسجيل محاولات الدخول الفاشلة
 * - حماية من Brute Force
 */

// تعطيل عرض الأخطاء في الإنتاج
ini_set('display_errors', '0');
error_reporting(E_ALL);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    // إعدادات جلسة آمنة
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    
    session_start();
}

// إعداد headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/**
 * دالة الاستجابة JSON
 */
function respond(bool $success, string $message, array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * دالة تسجيل الأخطاء
 */
function logError(string $message, array $context = []): void
{
    $logFile = __DIR__ . '/../../logs/login_errors.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    
    $logEntry = "[{$timestamp}] IP: {$ip} | {$message}{$contextStr}\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * دالة الاتصال بقاعدة البيانات
 */
function getDbConnection(): PDO
{
    // إعدادات قاعدة البيانات (عدّلها حسب إعداداتك)
    $host = 'localhost';
    $dbname = 'your_database';
    $username = 'your_username';
    $password = 'your_password';
    
    // محاولة قراءة من ملف .env إن وجد
    $envFile = __DIR__ . '/../../config/.env';
    if (is_readable($envFile)) {
        $env = parse_ini_file($envFile);
        $host = $env['DB_HOST'] ?? $host;
        $dbname = $env['DB_NAME'] ?? $dbname;
        $username = $env['DB_USER'] ?? $username;
        $password = $env['DB_PASS'] ?? $password;
    }
    
    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];
        
        return new PDO($dsn, $username, $password, $options);
        
    } catch (PDOException $e) {
        logError('Database connection failed: ' . $e->getMessage());
        respond(false, 'خطأ في الاتصال بقاعدة البيانات', [], 500);
    }
}

/**
 * فحص عدد محاولات الدخول الفاشلة (حماية Brute Force)
 */
function checkLoginAttempts(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $maxAttempts = 5; // عدد المحاولات المسموح بها
    $lockoutTime = 900; // مدة الحظر بالثواني (15 دقيقة)
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // تنظيف المحاولات القديمة
    $now = time();
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        fn($timestamp) => ($now - $timestamp) < $lockoutTime
    );
    
    // فحص عدد المحاولات
    $attempts = array_filter(
        $_SESSION['login_attempts'],
        fn($timestamp) => ($now - $timestamp) < $lockoutTime
    );
    
    if (count($attempts) >= $maxAttempts) {
        $remainingTime = ceil(($lockoutTime - ($now - min($attempts))) / 60);
        respond(false, "تم تجاوز الحد المسموح من المحاولات. حاول مرة أخرى بعد {$remainingTime} دقيقة", [], 429);
        return false;
    }
    
    return true;
}

/**
 * تسجيل محاولة دخول فاشلة
 */
function recordFailedAttempt(): void
{
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $_SESSION['login_attempts'][] = time();
}

/**
 * إعادة تعيين محاولات الدخول
 */
function resetLoginAttempts(): void
{
    $_SESSION['login_attempts'] = [];
}

/**
 * البحث عن المستخدم (البريد الإلكتروني / اسم المستخدم / الهاتف)
 */
function findUser(PDO $pdo, string $identifier): ?array
{
    try {
        $sql = "SELECT 
                    u.*,
                    r.name as role_name,
                    r.display_name as role_display_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE (u.email = :identifier 
                    OR u.username = :identifier 
                    OR u.phone = :identifier)
                AND u.is_active = 1
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':identifier', $identifier);
        $stmt->execute();
        
        return $stmt->fetch() ?: null;
        
    } catch (PDOException $e) {
        logError('findUser error: ' . $e->getMessage(), ['identifier' => $identifier]);
        return null;
    }
}

/**
 * جلب صلاحيات المستخدم
 */
function getUserPermissions(PDO $pdo, int $roleId): array
{
    try {
        $sql = "SELECT p.name, p.display_name
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
                AND p.is_active = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
    } catch (PDOException $e) {
        logError('getUserPermissions error: ' . $e->getMessage(), ['role_id' => $roleId]);
        return [];
    }
}

/**
 * تحديث آخر دخول للمستخدم
 */
function updateLastLogin(PDO $pdo, int $userId): void
{
    try {
        $sql = "UPDATE users 
                SET last_login = NOW(), 
                    last_login_ip = :ip 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? null);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
    } catch (PDOException $e) {
        logError('updateLastLogin error: ' . $e->getMessage(), ['user_id' => $userId]);
    }
}

/**
 * تسجيل نشاط الدخول في السجل
 */
function logLoginActivity(PDO $pdo, int $userId, bool $success): void
{
    try {
        // التحقق من وجود جدول login_logs
        $checkTable = $pdo->query("SHOW TABLES LIKE 'login_logs'")->fetch();
        
        if (!$checkTable) {
            // إنشاء الجدول إذا لم يكن موجوداً
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS login_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    success TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        $sql = "INSERT INTO login_logs (user_id, ip_address, user_agent, success) 
                VALUES (:user_id, :ip, :user_agent, :success)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $stmt->bindValue(':success', $success ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
        
    } catch (PDOException $e) {
        logError('logLoginActivity error: ' . $e->getMessage());
    }
}

/**
 * إنشاء بيانات الجلسة
 */
function createSession(array $user, array $permissions): array
{
    // تجديد معرف الجلسة للأمان
    session_regenerate_id(true);
    
    // تنظيف بيانات المستخدم (إزالة كلمة المرور)
    unset($user['password_hash']);
    
    // حفظ بيانات المستخدم في الجلسة
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['role_name'] = $user['role_name'] ?? '';
    $_SESSION['permissions'] = $permissions;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['user'] = $user;
    
    // إنشاء توكن للجلسة
    $_SESSION['session_token'] = bin2hex(random_bytes(32));
    
    return [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role_id' => (int)$user['role_id'],
        'role_name' => $user['role_name'] ?? '',
        'role_display_name' => $user['role_display_name'] ?? '',
        'permissions' => $permissions,
        'session_token' => $_SESSION['session_token']
    ];
}

// ========================================
// البرنامج الرئيسي
// ========================================

// قبول POST فقط
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'طريقة الطلب غير مسموحة. استخدم POST', [], 405);
}

// جلب البيانات
$input = [];

// دعم JSON
$rawInput = file_get_contents('php://input');
if ($rawInput) {
    $jsonData = @json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $input = $jsonData;
    }
}

// دعم FormData
if (empty($input) && !empty($_POST)) {
    $input = $_POST;
}

// التحقق من وجود البيانات المطلوبة
if (empty($input['identifier']) || empty($input['password'])) {
    respond(false, 'البريد الإلكتروني/اسم المستخدم وكلمة المرور مطلوبان', [], 400);
}

$identifier = trim($input['identifier']);
$password = $input['password'];

// التحقق من طول البيانات
if (strlen($identifier) < 3 || strlen($identifier) > 100) {
    respond(false, 'البريد الإلكتروني/اسم المستخدم غير صالح', [], 400);
}

if (strlen($password) < 6 || strlen($password) > 100) {
    respond(false, 'كلمة المرور غير صالحة', [], 400);
}

// فحص محاولات الدخول
checkLoginAttempts();

// الاتصال بقاعدة البيانات
$pdo = getDbConnection();

// البحث عن المستخدم
$user = findUser($pdo, $identifier);

if (!$user) {
    recordFailedAttempt();
    logError('Login failed: User not found', ['identifier' => $identifier]);
    respond(false, 'البريد الإلكتروني/اسم المستخدم أو كلمة المرور غير صحيحة', [], 401);
}

// التحقق من كلمة المرور
if (!password_verify($password, $user['password_hash'])) {
    recordFailedAttempt();
    logLoginActivity($pdo, (int)$user['id'], false);
    logError('Login failed: Invalid password', ['user_id' => $user['id'], 'identifier' => $identifier]);
    respond(false, 'البريد الإلكتروني/اسم المستخدم أو كلمة المرور غير صحيحة', [], 401);
}

// التحقق من حالة المستخدم
if ((int)$user['is_active'] !== 1) {
    logError('Login failed: User inactive', ['user_id' => $user['id']]);
    respond(false, 'حسابك غير نشط. تواصل مع الإدارة', [], 403);
}

// جلب صلاحيات المستخدم
$permissions = getUserPermissions($pdo, (int)$user['role_id']);

// تحديث آخر دخول
updateLastLogin($pdo, (int)$user['id']);

// تسجيل نشاط ناجح
logLoginActivity($pdo, (int)$user['id'], true);

// إعادة تعيين محاولات الدخول
resetLoginAttempts();

// إنشاء الجلسة
$sessionData = createSession($user, $permissions);

// تسجيل النجاح
logError('Login successful', ['user_id' => $user['id'], 'username' => $user['username']]);

// الاستجابة
respond(true, 'تم تسجيل الدخول بنجاح', [
    'user' => $sessionData,
    'redirect' => '/admin/dashboard.php'
], 200);
