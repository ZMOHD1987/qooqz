<?php
// htdocs/api/helpers/security.php
// ملف دوال الأمان (Security Helper)
// يشمل: التشفير، التحقق، الحماية من الهجمات
// تم التعديل لدعم PDO بدلاً من mysqli

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// ===========================================
// Security Class
// ===========================================

class Security {
    
    // ===========================================
    // 1️⃣ تشفير كلمة المرور (Password Hashing)
    // ===========================================
    
    /**
     * تشفير كلمة المرور
     * 
     * @param string $password
     * @return string
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_HASH_ALGO, [
            'cost' => PASSWORD_HASH_COST
        ]);
    }
    
    /**
     * التحقق من كلمة المرور
     * 
     * @param string $password كلمة المرور المدخلة
     * @param string $hash الهاش المحفوظ
     * @return bool
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * التحقق من قوة كلمة المرور
     * 
     * @param string $password
     * @return array ['valid' => bool, 'errors' => array, 'strength' => string]
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        $strength = 'weak';
        
        // الطول الأدنى
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters";
        }
        
        // التحقق من وجود حرف صغير
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // التحقق من وجود حرف كبير
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // التحقق من وجود رقم
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        // التحقق من وجود رمز خاص
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        // حساب قوة كلمة المرور
        if (empty($errors)) {
            $length = strlen($password);
            if ($length >= 12) {
                $strength = 'strong';
            } elseif ($length >= 10) {
                $strength = 'medium';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $strength
        ];
    }
    
    /**
     * إعادة تشفير كلمة المرور إذا لزم
     * 
     * @param string $password
     * @param string $hash
     * @return string|null هاش جديد أو null
     */
    public static function rehashPasswordIfNeeded($password, $hash) {
        if (password_needs_rehash($hash, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST])) {
            return self::hashPassword($password);
        }
        return null;
    }
    
    // ===========================================
    // 2️⃣ التشفير والفك (Encryption/Decryption)
    // ===========================================
    
    /**
     * تشفير بيانات
     * 
     * @param string $data
     * @param string|null $key مفتاح التشفير (افتراضي من config)
     * @return string
     */
    public static function encrypt($data, $key = null) {
        $key = $key ?? JWT_SECRET;
        $method = 'AES-256-CBC';
        
        // إنشاء IV عشوائي
        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        // التشفير
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        // دمج IV مع البيانات المشفرة
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * فك تشفير بيانات
     * 
     * @param string $encryptedData
     * @param string|null $key
     * @return string|false
     */
    public static function decrypt($encryptedData, $key = null) {
        $key = $key ?? JWT_SECRET;
        $method = 'AES-256-CBC';
        
        try {
            // فك الترميز
            $data = base64_decode($encryptedData);
            
            // استخراج IV
            $ivLength = openssl_cipher_iv_length($method);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            // فك التشفير
            return openssl_decrypt($encrypted, $method, $key, 0, $iv);
            
        } catch (Exception $e) {
            self::logError('Decryption failed: ' .  $e->getMessage());
            return false;
        }
    }
    
    // ===========================================
    // 3️⃣ توليد Tokens عشوائية
    // ===========================================
    
    /**
     * إنشاء token عشوائي آمن
     * 
     * @param int $length الطول (بالبايتات)
     * @return string
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * إنشاء رمز OTP عشوائي
     * 
     * @param int $length عدد الأرقام
     * @return string
     */
    public static function generateOTP($length = 6) {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;
        return str_pad(random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * إنشاء كود كوبون عشوائي
     * 
     * @param int $length
     * @return string
     */
    public static function generateCouponCode($length = 8) {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // استثناء I, O, 0, 1
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $code;
    }
    
    // ===========================================
    // 4️⃣ التحقق من البيانات المدخلة (Input Validation)
    // ===========================================
    
    /**
     * تنظيف النص من HTML و JavaScript
     * 
     * @param string $input
     * @return string
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        // إزالة المسافات الزائدة
        $input = trim($input);
        
        // إزالة الـ slashes
        $input = stripslashes($input);
        
        // تحويل الرموز الخاصة
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    /**
     * التحقق من البريد الإلكتروني
     * 
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * التحقق من رقم الجوال السعودي
     * 
     * @param string $phone
     * @return bool
     */
    public static function validateSaudiPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return preg_match(REGEX_PHONE_SA, $phone) === 1;
    }
    
    /**
     * التحقق من URL
     * 
     * @param string $url
     * @return bool
     */
    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * التحقق من رقم صحيح
     * 
     * @param mixed $value
     * @return bool
     */
    public static function validateInteger($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * التحقق من رقم عشري
     * 
     * @param mixed $value
     * @return bool
     */
    public static function validateFloat($value) {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
    
    // ===========================================
    // 5️⃣ الحماية من SQL Injection
    // ===========================================
    
    /**
     * تنظيف النص لاستخدامه في SQL (باستخدام PDO)
     * 
     * @param PDO $pdo
     * @param string $string
     * @return string
     */
    public static function escapeSQLString($pdo, $string) {
        return $pdo->quote($string);
    }
    
    // ===========================================
    // 6️⃣ الحماية من XSS (Cross-Site Scripting)
    // ===========================================
    
    /**
     * تنظيف HTML من السكربتات الضارة
     * 
     * @param string $html
     * @return string
     */
    public static function sanitizeHTML($html) {
        // إزالة جميع الوسوم عدا المسموح بها
        $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        return strip_tags($html, $allowedTags);
    }
    
    /**
     * تنظيف شامل من XSS
     * 
     * @param string $data
     * @return string
     */
    public static function preventXSS($data) {
        // تحويل جميع الرموز الخ��صة
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // إزالة null bytes
        $data = str_replace(chr(0), '', $data);
        
        // إزالة السكربتات
        $data = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $data);
        
        return $data;
    }
    
    // ===========================================
    // 7️⃣ الحماية من CSRF (Cross-Site Request Forgery)
    // ===========================================
    
    /**
     * إنشاء CSRF Token
     * 
     * @return string
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = self::generateToken(32);
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * التحقق من CSRF Token
     * 
     * @param string $token
     * @param int $maxAge أقصى عمر للـ token بالثواني (افتراضي:  ساعة)
     * @return bool
     */
    public static function verifyCSRFToken($token, $maxAge = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // التحقق من انتهاء الصلاحية
        if (time() - $_SESSION['csrf_token_time'] > $maxAge) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        // مقارنة آمنة
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // ===========================================
    // 8️⃣ Rate Limiting (الحماية من الطلبات الكثيرة)
    // ===========================================
    
    /**
     * التحقق من Rate Limit
     * 
     * @param string $identifier معرف (IP, User ID, etc.)
     * @param int $limit عدد الطلبات المسموح بها
     * @param int $window النافذة الزمنية بالثواني
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public static function checkRateLimit($identifier, $limit = null, $window = null) {
        $limit = $limit ?? RATE_LIMIT_REQUESTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        // جلب البيانات المحفوظة
        if (! isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'reset_time' => $now + $window
            ];
        }
        
        $data = $_SESSION[$key];
        
        // إعادة تعيين إذا انتهت النافذة الزمنية
        if ($now >= $data['reset_time']) {
            $data = [
                'count' => 0,
                'reset_time' => $now + $window
            ];
        }
        
        // زيادة العداد
        $data['count']++;
        $_SESSION[$key] = $data;
        
        $allowed = $data['count'] <= $limit;
        $remaining = max(0, $limit - $data['count']);
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_time' => $data['reset_time'],
            'retry_after' => $allowed ? 0 : ($data['reset_time'] - $now)
        ];
    }
    
    /**
     * إعادة تعيين Rate Limit
     * 
     * @param string $identifier
     */
    public static function resetRateLimit($identifier) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        unset($_SESSION[$key]);
    }
    
    // ===========================================
    // 9️⃣ الحصول على معلومات الطلب (Request Info)
    // ===========================================
    
    /**
     * الحصول على IP الحقيقي للمستخدم
     * 
     * @return string
     */
    public static function getRealIP() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // إذا كان هناك عدة IPs، خذ الأول
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // التحقق من صحة IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * الحصول على User Agent
     * 
     * @return string
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * اكتشاف نوع الجهاز
     * 
     * @return string mobile, tablet, desktop
     */
    public static function detectDevice() {
        $userAgent = self::getUserAgent();
        
        if (preg_match('/(tablet|ipad|playbook)|(android(?!. *(mobi|opera mini)))/i', $userAgent)) {
            return 'tablet';
        }
        
        if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $userAgent)) {
            return 'mobile';
        }
        
        return 'desktop';
    }
    
    /**
     * التحقق من Bot
     * 
     * @return bool
     */
    public static function isBot() {
        $userAgent = strtolower(self::getUserAgent());
        $bots = ['bot', 'crawl', 'spider', 'slurp', 'mediapartners'];
        
        foreach ($bots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    // ===========================================
    // 🔟 الحماية من Brute Force
    // ===========================================
    
    /**
     * تسجيل محاولة تسجيل دخول فاشلة
     * 
     * @param string $identifier (email, username, IP)
     * @return array ['locked' => bool, 'attempts' => int, 'lock_time' => int]
     */
    public static function recordFailedLogin($identifier) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($identifier);
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => $now,
                'locked_until' => 0
            ];
        }
        
        $data = $_SESSION[$key];
        
        // إعادة تعيين إذا مر وقت الحظر
        if ($data['locked_until'] > 0 && $now >= $data['locked_until']) {
            $data = [
                'count' => 0,
                'first_attempt' => $now,
                'locked_until' => 0
            ];
        }
        
        // زيادة العداد
        $data['count']++;
        
        // حظر إذا تجاوز الحد
        if ($data['count'] >= MAX_LOGIN_ATTEMPTS) {
            $data['locked_until'] = $now + LOGIN_LOCKOUT_TIME;
        }
        
        $_SESSION[$key] = $data;
        
        return [
            'locked' => $data['locked_until'] > $now,
            'attempts' => $data['count'],
            'remaining' => max(0, MAX_LOGIN_ATTEMPTS - $data['count']),
            'lock_time' => $data['locked_until'] > $now ? ($data['locked_until'] - $now) : 0
        ];
    }
    
    /**
     * التحقق من حالة الحظر
     * 
     * @param string $identifier
     * @return array
     */
    public static function checkLoginLock($identifier) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return [
                'locked' => false,
                'attempts' => 0,
                'remaining' => MAX_LOGIN_ATTEMPTS,
                'lock_time' => 0
            ];
        }
        
        $data = $_SESSION[$key];
        $now = time();
        
        $locked = $data['locked_until'] > $now;
        
        return [
            'locked' => $locked,
            'attempts' => $data['count'],
            'remaining' => max(0, MAX_LOGIN_ATTEMPTS - $data['count']),
            'lock_time' => $locked ? ($data['locked_until'] - $now) : 0
        ];
    }
    
    /**
     * إعادة تعيين محاولات تسجيل الدخول
     * 
     * @param string $identifier
     */
    public static function resetLoginAttempts($identifier) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($identifier);
        unset($_SESSION[$key]);
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * إنشاء Hash آمن لأي بيانات
     * 
     * @param string $data
     * @param string $algo الخوارزمية (sha256, sha512, etc.)
     * @return string
     */
    public static function hash($data, $algo = 'sha256') {
        return hash($algo, $data);
    }
    
    /**
     * مقارنة آمنة للنصوص (حماية من timing attacks)
     * 
     * @param string $known
     * @param string $user
     * @return bool
     */
    public static function timingSafeEquals($known, $user) {
        return hash_equals($known, $user);
    }
    
    /**
     * تسجيل حدث أمني
     * 
     * @param string $event
     * @param string $details
     */
    public static function logSecurityEvent($event, $details) {
        if (LOG_ENABLED) {
            $ip = self::getRealIP();
            $userAgent = self::getUserAgent();
            
            $message = sprintf(
                "[%s] Security Event: %s | IP: %s | Details: %s | UA: %s\n",
                date('Y-m-d H:i:s'),
                $event,
                $ip,
                $details,
                $userAgent
            );
            
            error_log($message, 3, LOG_FILE_AUTH);
        }
    }
    
    /**
     * تسجيل خطأ
     * 
     * @param string $message
     */
    private static function logError($message) {
        if (LOG_ENABLED) {
            error_log("[Security Error] " . $message, 3, LOG_FILE_ERROR);
        }
    }
}

// ===========================================
// ✅ تم تحميل Security Helper بنجاح
// ===========================================

?>