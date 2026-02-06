<?php
// htdocs/api/helpers/jwt.php
// ملف دوال JWT (JSON Web Token)
// للمصادقة والتحقق من المستخدمين

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';

// ===========================================
// JWT Class
// ===========================================

class JWT {
    
    // ===========================================
    // 1️⃣ إنشاء Token جديد (Encode)
    // ===========================================
    
    /**
     * إنشاء JWT Token
     * 
     * @param array $payload البيانات المراد تشفيرها في الـ Token
     * @param int $expiry مدة صلاحية الـ Token بالثواني (افتراضي:   من config)
     * @return string الـ Token المُشفّر
     */
    public static function encode($payload, $expiry = null) {
        // استخدام المدة الافتراضية إذا لم تُحدد
        if ($expiry === null) {
            $expiry = JWT_EXPIRY;
        }
        
        // Header - معلومات عن نوع الـ Token وخوارزمية التشفير
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'  // HMAC SHA256
        ];
        
        // Payload - البيانات + أوقات الإصدار والانتهاء
        $payload['iat'] = time();                // Issued At - وقت الإصدار
        $payload['exp'] = time() + $expiry;      // Expiration Time - وقت الانتهاء
        $payload['jti'] = self::generateJTI();   // JWT ID - معرف فريد
        
        // تشفير Header و Payload بصيغة Base64URL
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        // إنشاء التوقيع (Signature)
        $signature = hash_hmac(
            'SHA256',
            "$headerEncoded.$payloadEncoded",
            JWT_SECRET,
            true
        );
        
        $signatureEncoded = self::base64UrlEncode($signature);
        
        // الـ Token النهائي:   header. payload.signature
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }
    
    // ===========================================
    // 2️⃣ فك تشفير Token (Decode)
    // ===========================================
    
    /**
     * فك تشفير والتحقق من صحة JWT Token
     * 
     * @param string $token الـ Token المراد فك تشفيره
     * @return array|false البيانات المُشفرة أو false إذا كان Token غير صالح
     */
    public static function decode($token) {
        try {
            // تقسيم الـ Token إلى 3 أجزاء
            $parts = explode('.', $token);
            
            // التحقق من أن الـ Token يحتوي على 3 أجزاء
            if (count($parts) !== 3) {
                self::logError('Invalid token format:   must have 3 parts');
                return false;
            }
            
            list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
            
            // فك تشفير Header و Payload
            $header = json_decode(self::base64UrlDecode($headerEncoded), true);
            $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
            
            // التحقق من نجاح فك التشفير
            if ($header === null || $payload === null) {
                self::logError('Failed to decode token parts');
                return false;
            }
            
            // التحقق من الخوارزمية
            if (! isset($header['alg']) || $header['alg'] !== 'HS256') {
                self::logError('Invalid algorithm:  ' . ($header['alg'] ?? 'none'));
                return false;
            }
            
            // التحقق من التوقيع
            $signature = self::base64UrlDecode($signatureEncoded);
            $expectedSignature = hash_hmac(
                'SHA256',
                "$headerEncoded.$payloadEncoded",
                JWT_SECRET,
                true
            );
            
            // مقارنة آمنة للتوقيعات (حماية من timing attacks)
            if (!hash_equals($signature, $expectedSignature)) {
                self:: logError('Invalid signature');
                return false;
            }
            
            // التحقق من انتهاء الصلاحية
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                self::logError('Token expired at:   ' . date('Y-m-d H:i:s', $payload['exp']));
                return false;
            }
            
            // التحقق من وقت الإصدار (لا يمكن استخدام Token من المستقبل)
            if (isset($payload['iat']) && $payload['iat'] > time()) {
                self::logError('Token issued in future');
                return false;
            }
            
            // التحقق من وجود JTI في القائمة السوداء
            if (isset($payload['jti']) && self::isJTIRevoked($payload['jti'])) {
                self::logError('Token JTI is revoked');
                return false;
            }
            
            // كل شيء صحيح، إرجاع البيانات
            return $payload;
            
        } catch (Exception $e) {
            self::logError('Exception during decode: ' . $e->getMessage());
            return false;
        }
    }
    
    // ===========================================
    // 3️⃣ استخراج Token من Authorization Header
    // ===========================================
    
    /**
     * استخراج Bearer Token من الـ Headers
     * 
     * @return string|null الـ Token أو null إذا لم يوجد
     */
    public static function getBearerToken() {
        $headers = self::getAuthorizationHeaders();
        
        if (!empty($headers)) {
            // البحث عن Bearer Token
            if (preg_match('/Bearer\s+(\S+)/i', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    // ===========================================
    // 4️⃣ الحصول على Authorization Headers
    // ===========================================
    
    /**
     * الحصول على Authorization header من الطلب
     * 
     * @return string|null
     */
    private static function getAuthorizationHeaders() {
        $headers = null;
        
        // Apache
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        }
        // Apache mod_rewrite
        elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        // Nginx or PHP-CGI
        elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // قد يكون الاسم بأحرف كبيرة أو صغيرة
            $requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
            if (isset($requestHeaders['authorization'])) {
                $headers = trim($requestHeaders['authorization']);
            }
        }
        
        return $headers;
    }
    
    // ===========================================
    // 5️⃣ إنشاء Refresh Token
    // ===========================================
    
    /**
     * إنشاء Refresh Token (مدة صلاحية أطول)
     * 
     * @param int $userId معرف المستخدم
     * @return string
     */
    public static function createRefreshToken($userId) {
        $payload = [
            'user_id' => $userId,
            'type' => 'refresh',
            'random' => bin2hex(random_bytes(16))
        ];
        
        return self::encode($payload, REFRESH_TOKEN_EXPIRY);
    }
    
    // ===========================================
    // 6️⃣ التحقق من Refresh Token
    // ===========================================
    
    /**
     * التحقق من صحة Refresh Token
     * 
     * @param string $token
     * @return array|false
     */
    public static function verifyRefreshToken($token) {
        $payload = self::decode($token);
        
        if ($payload === false) {
            return false;
        }
        
        // التحقق من أنه refresh token
        if (! isset($payload['type']) || $payload['type'] !== 'refresh') {
            self::logError('Not a refresh token');
            return false;
        }
        
        return $payload;
    }
    
    // ===========================================
    // 7️⃣ إنشاء Access Token من User Data
    // ===========================================
    
    /**
     * إنشاء Access Token من بيانات المستخدم
     * 
     * @param array $user بيانات المستخدم
     * @return string
     */
    public static function createAccessToken($user) {
        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'username' => $user['username'] ?? null,
            'type' => 'access'
        ];
        
        return self::encode($payload, JWT_EXPIRY);
    }
    
    // ===========================================
    // 8️⃣ إنشاء OTP Token (رمز التحقق)
    // ===========================================
    
    /**
     * إنشاء OTP Token لرمز التحقق
     * 
     * @param string $identifier المعرف (email أو phone)
     * @param string $otp رمز التحقق
     * @return string
     */
    public static function createOTPToken($identifier, $otp) {
        $payload = [
            'identifier' => $identifier,
            'otp' => hash('sha256', $otp), // تشفير OTP
            'type' => 'otp'
        ];
        
        return self::encode($payload, OTP_EXPIRY);
    }
    
    // ===========================================
    // 9️⃣ التحقق من OTP Token
    // ===========================================
    
    /**
     * التحقق من OTP Token
     * 
     * @param string $token
     * @param string $otp رمز التحقق المُدخل
     * @return array|false
     */
    public static function verifyOTPToken($token, $otp) {
        $payload = self::decode($token);
        
        if ($payload === false) {
            return false;
        }
        
        // التحقق من أنه OTP token
        if (! isset($payload['type']) || $payload['type'] !== 'otp') {
            self::logError('Not an OTP token');
            return false;
        }
        
        // التحقق من OTP
        $hashedOTP = hash('sha256', $otp);
        if (!isset($payload['otp']) || !hash_equals($payload['otp'], $hashedOTP)) {
            self::logError('OTP mismatch');
            return false;
        }
        
        return $payload;
    }
    
    // ===========================================
    // 🔟 إنشاء Password Reset Token
    // ===========================================
    
    /**
     * إنشاء Token لإعادة تعيين كلمة المرور
     * 
     * @param int $userId
     * @param string $email
     * @return string
     */
    public static function createPasswordResetToken($userId, $email) {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'type' => 'password_reset',
            'random' => bin2hex(random_bytes(16))
        ];
        
        // مدة صلاحية ساعة واحدة
        return self::encode($payload, 3600);
    }
    
    // ===========================================
    // 1️⃣1️⃣ التحقق من Password Reset Token
    // ===========================================
    
    /**
     * التحقق من Password Reset Token
     * 
     * @param string $token
     * @return array|false
     */
    public static function verifyPasswordResetToken($token) {
        $payload = self::decode($token);
        
        if ($payload === false) {
            return false;
        }
        
        // التحقق من أنه password reset token
        if (!isset($payload['type']) || $payload['type'] !== 'password_reset') {
            self::logError('Not a password reset token');
            return false;
        }
        
        return $payload;
    }
    
    // ===========================================
    // 1️⃣2️⃣ الحصول على User ID من Token
    // ===========================================
    
    /**
     * استخراج User ID من Token
     * 
     * @param string|null $token (إذا لم يُحدد، يُستخرج من Headers)
     * @return int|null
     */
    public static function getUserIdFromToken($token = null) {
        if ($token === null) {
            $token = self::getBearerToken();
        }
        
        if ($token === null) {
            return null;
        }
        
        $payload = self::decode($token);
        
        if ($payload === false) {
            return null;
        }
        
        return $payload['user_id'] ??  null;
    }
    
    // ===========================================
    // 1️⃣3️⃣ الحصول على Payload من Token الحالي
    // ===========================================
    
    /**
     * الحصول على كامل بيانات الـ Payload من Token
     * 
     * @return array|null
     */
    public static function getCurrentPayload() {
        $token = self::getBearerToken();
        
        if ($token === null) {
            return null;
        }
        
        $payload = self::decode($token);
        
        return $payload !== false ? $payload : null;
    }
    
    // ===========================================
    // 1️⃣4️⃣ التحقق من صلاحية Token
    // ===========================================
    
    /**
     * التحقق السريع من أن Token صالح
     * 
     * @param string|null $token
     * @return bool
     */
    public static function isValid($token = null) {
        if ($token === null) {
            $token = self::getBearerToken();
        }
        
        if ($token === null) {
            return false;
        }
        
        return self::decode($token) !== false;
    }
    
    // ===========================================
    // 1️⃣5️⃣ الحصول على الوقت المتبقي لانتهاء Token
    // ===========================================
    
    /**
     * الحصول على الوقت المتبقي بالثواني قبل انتهاء Token
     * 
     * @param string $token
     * @return int|null عدد الثواني، أو null إذا كان Token غير صالح
     */
    public static function getTimeRemaining($token) {
        $payload = self::decode($token);
        
        if ($payload === false || !isset($payload['exp'])) {
            return null;
        }
        
        $remaining = $payload['exp'] - time();
        
        return $remaining > 0 ? $remaining : 0;
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * Base64 URL Encode
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode($data) {
        $base64 = base64_encode($data);
        
        // تحويل Base64 العادي إلى Base64 URL-safe
        $base64 = strtr($base64, '+/', '-_');
        
        // إزالة علامات = في النهاية
        return rtrim($base64, '=');
    }
    
    /**
     * Base64 URL Decode
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode($data) {
        // تحويل Base64 URL-safe إلى Base64 العادي
        $base64 = strtr($data, '-_', '+/');
        
        // إضافة علامات = المفقودة
        $remainder = strlen($base64) % 4;
        if ($remainder) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }
        
        return base64_decode($base64);
    }
    
    /**
     * إنشاء JWT ID فريد
     * 
     * @return string
     */
    private static function generateJTI() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * التحقق من وجود JTI في القائمة السوداء
     * 
     * @param string $jti
     * @return bool
     */
    private static function isJTIRevoked($jti) {
        // افتراض وجود PDO instance عالمي أو تمريره، هنا نفترض global $pdo;
        global $pdo;
        
        if (!$pdo) {
            return false; // أو throw error
        }
        
        $stmt = $pdo->prepare("SELECT id FROM tokens_blacklist WHERE jti = ?");
        $stmt->execute([$jti]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * إضافة JTI إلى القائمة السوداء
     * 
     * @param string $jti
     * @param int $userId
     * @param string $type
     * @return bool
     */
    public static function revokeJTI($jti, $userId, $type) {
        global $pdo;
        
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare("INSERT INTO tokens_blacklist (jti, user_id, type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $jti,
            $userId,
            $type,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    /**
     * تسجيل أخطاء JWT
     * 
     * @param string $message
     */
    private static function logError($message) {
        if (LOG_ENABLED) {
            error_log("[JWT Error] " . $message, 3, LOG_FILE_AUTH);
        }
        
        if (DEBUG_MODE) {
            error_log("[JWT Debug] " .  $message);
        }
    }
    
    /**
     * إنشاء tokens كاملة (access + refresh)
     * 
     * @param array $user بيانات المستخدم
     * @return array ['access_token' => .. ., 'refresh_token' => .. ., 'expires_in' => ...]
     */
    public static function createTokenPair($user) {
        $accessToken = self::createAccessToken($user);
        $refreshToken = self::createRefreshToken($user['id']);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => JWT_EXPIRY,
            'refresh_expires_in' => REFRESH_TOKEN_EXPIRY
        ];
    }
    
    /**
     * تجديد Access Token من Refresh Token
     * 
     * @param string $refreshToken
     * @param PDO $pdo
     * @return array|false
     */
    public static function refreshAccessToken($refreshToken, $pdo) {
        // التحقق من Refresh Token
        $payload = self::verifyRefreshToken($refreshToken);
        
        if ($payload === false) {
            return false;
        }
        
        $userId = $payload['user_id'];
        
        // جلب بيانات المستخدم من قاعدة البيانات مع الـ role
        $stmt = $pdo->prepare("SELECT u.id, u.email, u.username, r.key_name as user_type, u.is_active FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.is_active = 1");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            self::logError('User not found or inactive for refresh token');
            return false;
        }
        
        $user = $result;
        
        // إنشاء Access Token جديد
        $newAccessToken = self::createAccessToken($user);
        
        return [
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => JWT_EXPIRY
        ];
    }
    
    /**
     * التحقق من صلاحيات المستخدم
     * 
     * @param int $userId
     * @param string $permissionKey
     * @param PDO $pdo
     * @return bool
     */
    public static function hasPermission($userId, $permissionKey, $pdo) {
        $stmt = $pdo->prepare("
            SELECT p.id 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN roles r ON rp.role_id = r.id
            JOIN users u ON u.role_id = r.id
            WHERE u.id = ? AND p.key_name = ? AND u.is_active = 1
        ");
        $stmt->execute([$userId, $permissionKey]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * حفظ جلسة المستخدم
     * 
     * @param int $userId
     * @param string $token
     * @param PDO $pdo
     * @return bool
     */
    public static function saveUserSession($userId, $token, $pdo) {
        $expiresAt = date('Y-m-d H:i:s', time() + JWT_EXPIRY);
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, token, user_agent, ip, expires_at) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $userId,
            $token,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $expiresAt
        ]);
    }
    
    /**
     * إلغاء جلسة المستخدم
     * 
     * @param string $token
     * @param PDO $pdo
     * @return bool
     */
    public static function revokeUserSession($token, $pdo) {
        $stmt = $pdo->prepare("UPDATE user_sessions SET revoked = 1 WHERE token = ?");
        return $stmt->execute([$token]);
    }
}

// ===========================================
// ✅ تم تحميل JWT Helper بنجاح
// ===========================================

?>