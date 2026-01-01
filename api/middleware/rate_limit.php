<?php
// htdocs/api/middleware/rate_limit.php
// ملف Middleware للحماية من الطلبات الكثيرة (Rate Limiting)
// يدعم عدة استراتيجيات وتخزين مؤقت

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/security.php';
require_once __DIR__ . '/../helpers/utils.php';

// ===========================================
// RateLimitMiddleware Class
// ===========================================

class RateLimitMiddleware {
    
    // مخزن مؤقت للـ rate limits
    private static $storage = [];
    
    // ===========================================
    // 1️⃣ تطبيق Rate Limit الأساسي
    // ===========================================
    
    /**
     * تطبيق Rate Limit على الطلب
     * 
     * @param int|null $limit عدد الطلبات المسموح بها
     * @param int|null $window النافذة الزمنية بالثواني
     * @param string|null $identifier المعرف (IP, User ID, etc.)
     * @return void
     */
    public static function apply($limit = null, $window = null, $identifier = null) {
        if (! RATE_LIMIT_ENABLED) {
            return;
        }
        
        $limit = $limit ?? RATE_LIMIT_REQUESTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        $identifier = $identifier ?? self::getIdentifier();
        
        $result = self::check($identifier, $limit, $window);
        
        // إضافة Headers
        self::addHeaders($result['limit'], $result['remaining'], $result['reset_time']);
        
        if (! $result['allowed']) {
            self::logRateLimitExceeded($identifier, $limit, $window);
            Response::tooManyRequests($result['retry_after']);
        }
        
        // تسجيل الطلب
        self:: recordRequest($identifier, $window);
    }
    
    /**
     * التحقق من Rate Limit بدون تسجيل الطلب
     * 
     * @param string $identifier
     * @param int $limit
     * @param int $window
     * @return array
     */
    public static function check($identifier, $limit, $window) {
        $key = self::getStorageKey($identifier);
        $now = time();
        
        // جلب البيانات
        $data = self::getData($key);
        
        if (! $data || $now >= $data['reset_time']) {
            // بداية نافذة جديدة
            $data = [
                'count' => 0,
                'reset_time' => $now + $window,
                'first_request' => $now
            ];
        }
        
        $allowed = $data['count'] < $limit;
        $remaining = max(0, $limit - $data['count']);
        $retryAfter = $allowed ? 0 : ($data['reset_time'] - $now);
        
        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => $remaining,
            'reset_time' => $data['reset_time'],
            'retry_after' => $retryAfter,
            'current_count' => $data['count']
        ];
    }
    
    /**
     * تسجيل طلب جديد
     * 
     * @param string $identifier
     * @param int $window
     * @return void
     */
    private static function recordRequest($identifier, $window) {
        $key = self::getStorageKey($identifier);
        $now = time();
        
        $data = self::getData($key);
        
        if (!$data || $now >= $data['reset_time']) {
            $data = [
                'count' => 1,
                'reset_time' => $now + $window,
                'first_request' => $now
            ];
        } else {
            $data['count']++;
        }
        
        self::setData($key, $data, $window);
    }
    
    // ===========================================
    // 2️⃣ Rate Limits مخصصة حسب النوع
    // ===========================================
    
    /**
     * Rate Limit لتسجيل الدخول
     * 5 محاولات كل 15 دقيقة
     */
    public static function forLogin($identifier = null) {
        $identifier = $identifier ?? self::getIdentifier();
        self::apply(5, 900, 'login: ' . $identifier); // 15 minutes
    }
    
    /**
     * Rate Limit للتسجيل
     * 3 محاولات كل ساعة
     */
    public static function forRegister($identifier = null) {
        $identifier = $identifier ?? self::getIdentifier();
        self::apply(3, 3600, 'register:' .  $identifier); // 1 hour
    }
    
    /**
     * Rate Limit لإرسال OTP
     * 5 محاولات كل 10 دقائق
     */
    public static function forOTP($identifier = null) {
        $identifier = $identifier ?? self::getIdentifier();
        self::apply(5, 600, 'otp:' . $identifier); // 10 minutes
    }
    
    /**
     * Rate Limit لإعادة تعيين كلمة المرور
     * 3 محاولات كل ساعة
     */
    public static function forPasswordReset($identifier = null) {
        $identifier = $identifier ??  self::getIdentifier();
        self::apply(3, 3600, 'password_reset:' . $identifier); // 1 hour
    }
    
    /**
     * Rate Limit للبحث
     * 60 طلب كل دقيقة
     */
    public static function forSearch($identifier = null) {
        $identifier = $identifier ?? self::getIdentifier();
        self::apply(60, 60, 'search:' . $identifier); // 1 minute
    }
    
    /**
     * Rate Limit لإنشاء الطلبات
     * 10 طلبات كل ساعة
     */
    public static function forOrderCreation($identifier = null) {
        $identifier = $identifier ?? self::getIdentifier();
        self::apply(10, 3600, 'order: ' . $identifier); // 1 hour
    }
    
    /**
     * Rate Limit للتقييمات
     * 5 تقييمات كل ساعة
     */
    public static function forReviews($identifier = null) {
        $identifier = $identifier ??  self::getIdentifier();
        self::apply(5, 3600, 'review:' . $identifier); // 1 hour
    }
    
    /**
     * Rate Limit لرفع الملفات
     * 20 ملف كل ساعة
     */
    public static function forUpload($identifier = null) {
        $identifier = $identifier ?? self::getIdentifier();
        self::apply(20, 3600, 'upload:' . $identifier); // 1 hour
    }
    
    /**
     * Rate Limit للـ API العام
     * 100 طلب كل دقيقة
     */
    public static function forAPI($identifier = null) {
        $identifier = $identifier ?? self:: getIdentifier();
        self::apply(100, 60, 'api:' . $identifier); // 1 minute
    }
    
    /**
     * Rate Limit صارم (للعمليات الحساسة)
     * 3 محاولات كل 5 دقائق
     */
    public static function strict($identifier = null) {
        $identifier = $identifier ?? self:: getIdentifier();
        self::apply(3, 300, 'strict:' . $identifier); // 5 minutes
    }
    
    /**
     * Rate Limit مرن (للعمليات العادية)
     * 200 طلب كل دقيقة
     */
    public static function relaxed($identifier = null) {
        $identifier = $identifier ?? self::getIdentifier();
        self::apply(200, 60, 'relaxed:' . $identifier); // 1 minute
    }
    
    // ===========================================
    // 3️⃣ Rate Limit حسب المستخدم
    // ===========================================
    
    /**
     * Rate Limit حسب نوع المستخدم
     * 
     * @param array|null $limits ['customer' => [100, 60], 'vendor' => [200, 60]]
     */
    public static function byUserType($limits = null) {
        if (!RATE_LIMIT_ENABLED) {
            return;
        }
        
        // الحدود الافتراضية
        $defaultLimits = [
            USER_TYPE_CUSTOMER => [100, 60],      // 100 requests per minute
            USER_TYPE_VENDOR => [200, 60],        // 200 requests per minute
            USER_TYPE_ADMIN => [500, 60],         // 500 requests per minute
            USER_TYPE_SUPER_ADMIN => [1000, 60],  // 1000 requests per minute
            'guest' => [30, 60]                    // 30 requests per minute
        ];
        
        $limits = $limits ?? $defaultLimits;
        
        // تحديد نوع المستخدم
        $userType = 'guest';
        $identifier = Security::getRealIP();
        
        // إذا كان مصادق عليه
        $token = JWT::getBearerToken();
        if ($token) {
            $payload = JWT::decode($token);
            if ($payload && isset($payload['user_id'], $payload['user_type'])) {
                $userType = $payload['user_type'];
                $identifier = 'user:' . $payload['user_id'];
            }
        }
        
        // الحصول على الحدود
        $userLimits = $limits[$userType] ?? $limits['guest'];
        list($limit, $window) = $userLimits;
        
        self::apply($limit, $window, $identifier);
    }
    
    // ===========================================
    // 4️⃣ Sliding Window Algorithm
    // ===========================================
    
    /**
     * تطبيق Sliding Window Rate Limit
     * أكثر دقة من Fixed Window
     * 
     * @param int $limit
     * @param int $window
     * @param string|null $identifier
     */
    public static function slidingWindow($limit, $window, $identifier = null) {
        if (!RATE_LIMIT_ENABLED) {
            return;
        }
        
        $identifier = $identifier ?? self::getIdentifier();
        $key = self::getStorageKey('sliding: ' . $identifier);
        $now = time();
        
        // جلب سجل الطلبات
        $requests = self::getData($key) ??  [];
        
        // إزالة الطلبات القديمة
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        $count = count($requests);
        $allowed = $count < $limit;
        $remaining = max(0, $limit - $count);
        
        // حساب وقت إعادة التعيين
        $oldestRequest = ! empty($requests) ? min($requests) : $now;
        $resetTime = $oldestRequest + $window;
        $retryAfter = $allowed ? 0 : ($resetTime - $now);
        
        // إضافة Headers
        self::addHeaders($limit, $remaining, $resetTime);
        
        if (!$allowed) {
            self::logRateLimitExceeded($identifier, $limit, $window);
            Response::tooManyRequests($retryAfter);
        }
        
        // تسجيل الطلب الجديد
        $requests[] = $now;
        self::setData($key, $requests, $window);
    }
    
    // ===========================================
    // 5️⃣ Token Bucket Algorithm
    // ===========================================
    
    /**
     * تطبيق Token Bucket Rate Limit
     * يسمح بـ bursts قصيرة
     * 
     * @param int $capacity عدد الـ tokens الأقصى
     * @param float $refillRate معدل الملء (tokens per second)
     * @param string|null $identifier
     */
    public static function tokenBucket($capacity, $refillRate, $identifier = null) {
        if (!RATE_LIMIT_ENABLED) {
            return;
        }
        
        $identifier = $identifier ?? self::getIdentifier();
        $key = self::getStorageKey('bucket:' . $identifier);
        $now = microtime(true);
        
        $bucket = self::getData($key);
        
        if (!$bucket) {
            $bucket = [
                'tokens' => $capacity,
                'last_refill' => $now
            ];
        } else {
            // إعادة ملء الـ tokens
            $timePassed = $now - $bucket['last_refill'];
            $tokensToAdd = $timePassed * $refillRate;
            
            $bucket['tokens'] = min($capacity, $bucket['tokens'] + $tokensToAdd);
            $bucket['last_refill'] = $now;
        }
        
        $allowed = $bucket['tokens'] >= 1;
        
        if ($allowed) {
            $bucket['tokens'] -= 1;
            self::setData($key, $bucket, 3600); // حفظ لمدة ساعة
            
            $remaining = floor($bucket['tokens']);
            self::addHeaders($capacity, $remaining, null);
        } else {
            $timeToRefill = (1 - $bucket['tokens']) / $refillRate;
            self::logRateLimitExceeded($identifier, $capacity, 1);
            Response::tooManyRequests(ceil($timeToRefill));
        }
    }
    
    // ===========================================
    // 6️⃣ إدارة التخزين (Storage Management)
    // ===========================================
    
    /**
     * الحصول على مفتاح التخزين
     * 
     * @param string $identifier
     * @return string
     */
    private static function getStorageKey($identifier) {
        return 'rate_limit:' . md5($identifier);
    }
    
    /**
     * الحصول على البيانات من التخزين
     * 
     * @param string $key
     * @return array|null
     */
    private static function getData($key) {
        // محاولة الحصول من الذاكرة أولاً
        if (isset(self::$storage[$key])) {
            $data = self::$storage[$key];
            
            // التحقق من انتهاء الصلاحية
            if (isset($data['expires_at']) && time() < $data['expires_at']) {
                return $data['value'];
            } else {
                unset(self::$storage[$key]);
                return null;
            }
        }
        
        // محاولة الحصول من Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION[$key])) {
            $data = $_SESSION[$key];
            
            if (isset($data['expires_at']) && time() < $data['expires_at']) {
                // حفظ في الذاكرة للوصول السريع
                self::$storage[$key] = $data;
                return $data['value'];
            } else {
                unset($_SESSION[$key]);
                return null;
            }
        }
        
        // محاولة الحصول من قاعدة البيانات (للتطبيقات الكبيرة)
        // يمكن تفعيلها لاحقاً
        
        return null;
    }
    
    /**
     * حفظ البيانات في التخزين
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time To Live بالثواني
     */
    private static function setData($key, $value, $ttl) {
        $expiresAt = time() + $ttl;
        
        $data = [
            'value' => $value,
            'expires_at' => $expiresAt
        ];
        
        // حفظ في الذاكرة
        self::$storage[$key] = $data;
        
        // حفظ في Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION[$key] = $data;
    }
    
    /**
     * حذف البيانات من التخزين
     * 
     * @param string $key
     */
    private static function deleteData($key) {
        unset(self::$storage[$key]);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION[$key]);
    }
    
    // ===========================================
    // 7️⃣ دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * الحصول على المعرف (IP أو User ID)
     * 
     * @return string
     */
    private static function getIdentifier() {
        // محاولة الحصول على User ID
        $token = JWT::getBearerToken();
        if ($token) {
            $payload = JWT::decode($token);
            if ($payload && isset($payload['user_id'])) {
                return 'user:' . $payload['user_id'];
            }
        }
        
        // استخدام IP كبديل
        return 'ip:' . Security::getRealIP();
    }
    
    /**
     * إضافة Rate Limit Headers
     * 
     * @param int $limit
     * @param int $remaining
     * @param int|null $resetTime
     */
    private static function addHeaders($limit, $remaining, $resetTime) {
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $remaining);
        
        if ($resetTime !== null) {
            header('X-RateLimit-Reset: ' . $resetTime);
        }
    }
    
    /**
     * تسجيل تجاوز Rate Limit
     * 
     * @param string $identifier
     * @param int $limit
     * @param int $window
     */
    private static function logRateLimitExceeded($identifier, $limit, $window) {
        $message = sprintf(
            "Rate limit exceeded: %s (Limit: %d requests in %d seconds)",
            $identifier,
            $limit,
            $window
        );
        
        Security::logSecurityEvent('rate_limit_exceeded', $message);
        
        if (DEBUG_MODE) {
            Utils::log($message, 'WARNING');
        }
    }
    
    /**
     * إعادة تعيين Rate Limit لمعرف محدد
     * 
     * @param string $identifier
     * @param string|null $prefix
     */
    public static function reset($identifier, $prefix = null) {
        $key = self::getStorageKey(($prefix ?  $prefix . ':' : '') . $identifier);
        self::deleteData($key);
        
        Utils::log("Rate limit reset for:  {$identifier}", 'INFO');
    }
    
    /**
     * الحصول على معلومات Rate Limit الحالية
     * 
     * @param string|null $identifier
     * @param int|null $limit
     * @param int|null $window
     * @return array
     */
    public static function getStatus($identifier = null, $limit = null, $window = null) {
        $identifier = $identifier ?? self::getIdentifier();
        $limit = $limit ?? RATE_LIMIT_REQUESTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        
        return self::check($identifier, $limit, $window);
    }
    
    /**
     * تنظيف البيانات المنتهية
     */
    public static function cleanup() {
        $now = time();
        
        // تنظيف من الذاكرة
        foreach (self::$storage as $key => $data) {
            if (isset($data['expires_at']) && $now >= $data['expires_at']) {
                unset(self::$storage[$key]);
            }
        }
        
        // تنظيف من Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        foreach ($_SESSION as $key => $data) {
            if (strpos($key, 'rate_limit:') === 0) {
                if (isset($data['expires_at']) && $now >= $data['expires_at']) {
                    unset($_SESSION[$key]);
                }
            }
        }
        
        Utils::log("Rate limit data cleaned up", 'INFO');
    }
}

// ===========================================
// دوال مساعدة عامة (Global Helper Functions)
// ===========================================

/**
 * تطبيق Rate Limit سريع
 * 
 * @param int|null $limit
 * @param int|null $window
 */
function rateLimit($limit = null, $window = null) {
    RateLimitMiddleware::apply($limit, $window);
}

/**
 * الحصول على حالة Rate Limit
 * 
 * @return array
 */
function rateLimitStatus() {
    return RateLimitMiddleware::getStatus();
}

// ===========================================
// ✅ تم تحميل Rate Limit Middleware بنجاح
// ===========================================

?>