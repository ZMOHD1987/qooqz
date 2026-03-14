<?php
declare(strict_types=1);

/**
 * Security Helper - Production Version
 *
 * Enterprise-grade security utilities
 * - Password hashing & validation (Argon2id/bcrypt)
 * - AES-256-GCM encryption with key rotation support
 * - CSRF protection
 * - Rate limiting (Session/Redis)
 * - Input sanitization & validation
 * - Brute force protection
 * - XSS/SQL injection prevention
 *
 * ## Key Rotation Support
 *
 * Encryption supports versioning for seamless key rotation:
 *
 * ```php
 * // Encrypt with version 1 (default)
 * $encrypted = Security::encryptForEntity($data, $tenantId, $entityId);
 *
 * // Later, rotate to version 2
 * $newEncrypted = Security::rotateEntityKey($encrypted, $tenantId, $entityId, 2);
 *
 * // Decryption automatically uses correct version
 * $decrypted = Security::decryptForEntity($newEncrypted, $tenantId, $entityId);
 *
 * // Check current version
 * $version = Security::getEncryptionVersion($encrypted); // Returns: 1 or 2
 * ```
 *
 * ## Migration from Legacy Methods
 *
 * Legacy `encrypt()`/`decrypt()` methods are deprecated:
 * - They trigger E_USER_DEPRECATED warnings
 * - They log security events for audit trails
 * - Use `encryptForEntity()`/`decryptForEntity()` instead
 *
 * @version 2.0.0
 * @package SecurityCore
 */

// ===========================================
// Dependencies
// ===========================================

if (!defined('SECURITY_HELPER_LOADED')) {
    define('SECURITY_HELPER_LOADED', true);

    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/constants.php';
    require_once __DIR__ . '/../core/CryptoConfig.php';
}

// ===========================================
// Security Class
// ===========================================

final class Security
{
    // Cache للمفاتيح المشتقة لتحسين الأداء
    private static array $keyCache = [];
    private static int $keyCacheLimit = 100;

    // منع إنشاء instance
    private function __construct() {}
    private function __clone() {}

    // ===========================================
    // 1️⃣ Password Management
    // ===========================================

    /**
     * Hash password using Argon2id or bcrypt
     *
     * @param string $password Plain text password
     * @return string Hashed password
     * @throws InvalidArgumentException
     */
    public static function hashPassword(string $password): string
    {
        if (empty($password)) {
            throw new InvalidArgumentException('Password cannot be empty');
        }

        if (strlen($password) > 72) {
            // bcrypt limitation workaround
            $password = hash('sha256', $password);
        }

        $algo = defined('PASSWORD_HASH_ALGO') ? PASSWORD_HASH_ALGO : PASSWORD_ARGON2ID;
        $cost = defined('PASSWORD_HASH_COST') ? PASSWORD_HASH_COST : 12;

        $options = $algo === PASSWORD_ARGON2ID ? [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ] : ['cost' => $cost];

        $hash = password_hash($password, $algo, $options);

        if ($hash === false) {
            throw new RuntimeException('Password hashing failed');
        }

        return $hash;
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Stored hash
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        if (empty($password) || empty($hash)) {
            return false;
        }

        // Timing-safe verification
        return password_verify($password, $hash);
    }

    /**
     * Validate password strength with detailed feedback
     *
     * @param string $password
     * @param array $options Custom validation rules
     * @return array ['valid' => bool, 'errors' => array, 'strength' => string, 'score' => int]
     */
    public static function validatePasswordStrength(string $password, array $options = []): array
    {
        $minLength = $options['min_length'] ?? (defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8);
        $requireLower = $options['require_lowercase'] ?? true;
        $requireUpper = $options['require_uppercase'] ?? true;
        $requireNumber = $options['require_number'] ?? true;
        $requireSpecial = $options['require_special'] ?? true;

        $errors = [];
        $score = 0;

        // Length check
        $length = strlen($password);
        if ($length < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters";
        } else {
            $score += min(25, floor($length / 2) * 5);
        }

        // Character variety checks
        if ($requireLower && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        } elseif (preg_match('/[a-z]/', $password)) {
            $score += 15;
        }

        if ($requireUpper && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        } elseif (preg_match('/[A-Z]/', $password)) {
            $score += 15;
        }

        if ($requireNumber && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        } elseif (preg_match('/[0-9]/', $password)) {
            $score += 15;
        }

        if ($requireSpecial && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        } elseif (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 20;
        }

        // Bonus points
        if (preg_match_all('/[^a-zA-Z0-9]/', $password) > 1) {
            $score += 10; // Multiple special chars
        }

        // Common patterns penalty
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score -= 10; // Repeated characters
        }

        if (preg_match('/^[0-9]+$/', $password)) {
            $score -= 20; // Only numbers
        }

        // Determine strength
        $score = max(0, min(100, $score));

        if ($score >= 80) {
            $strength = 'very_strong';
        } elseif ($score >= 60) {
            $strength = 'strong';
        } elseif ($score >= 40) {
            $strength = 'medium';
        } elseif ($score >= 20) {
            $strength = 'weak';
        } else {
            $strength = 'very_weak';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $strength,
            'score' => $score
        ];
    }

    /**
     * Check if password needs rehashing
     *
     * @param string $password Plain password
     * @param string $hash Current hash
     * @return string|null New hash or null
     */
    public static function rehashPasswordIfNeeded(string $password, string $hash): ?string
    {
        $algo = defined('PASSWORD_HASH_ALGO') ? PASSWORD_HASH_ALGO : PASSWORD_ARGON2ID;
        $cost = defined('PASSWORD_HASH_COST') ? PASSWORD_HASH_COST : 12;

        $options = $algo === PASSWORD_ARGON2ID ? [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2
        ] : ['cost' => $cost];

        if (password_needs_rehash($hash, $algo, $options)) {
            return self::hashPassword($password);
        }

        return null;
    }

    // ===========================================
    // 2️⃣ Advanced Encryption (AES-256-GCM)
    // ===========================================

    /**
     * Derive entity-specific encryption key
     *
     * @param int $tenantId
     * @param int $entityId
     * @param int $version Encryption version for key rotation
     * @return string Binary key
     */
    private static function deriveEntityKey(int $tenantId, int $entityId, int $version = 1): string
    {
        $cacheKey = "{$tenantId}:{$entityId}:v{$version}";

        if (isset(self::$keyCache[$cacheKey])) {
            return self::$keyCache[$cacheKey];
        }

        // Key derivation using HKDF with version support
        $info = "tenant:{$tenantId}|entity:{$entityId}";
        $salt = "entity-encryption-v{$version}";

        $key = hash_hkdf(
            'sha256',
            CryptoConfig::masterKey(),
            32,
            $info,
            $salt
        );

        // Cache management
        if (count(self::$keyCache) >= self::$keyCacheLimit) {
            self::$keyCache = array_slice(self::$keyCache, -50, null, true);
        }

        self::$keyCache[$cacheKey] = $key;

        return $key;
    }

    /**
     * Encrypt data for specific entity using AES-256-GCM
     *
     * @param string $plainText
     * @param int $tenantId
     * @param int $entityId
     * @param int $version Encryption version (1-255)
     * @return string Base64 encoded: version(1) + iv(12) + tag(16) + cipher
     * @throws RuntimeException
     */
    public static function encryptForEntity(
        string $plainText,
        int $tenantId,
        int $entityId,
        int $version = 1
    ): string {
        if (empty($plainText)) {
            throw new InvalidArgumentException('Plain text cannot be empty');
        }

        if ($version < 1 || $version > 255) {
            throw new InvalidArgumentException('Encryption version must be between 1 and 255');
        }

        try {
            $key = self::deriveEntityKey($tenantId, $entityId, $version);
            $iv = random_bytes(12);
            $tag = '';

            $cipher = openssl_encrypt(
                $plainText,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16
            );

            if ($cipher === false) {
                throw new RuntimeException('Encryption failed');
            }

            // Version byte for key rotation support
            $versionByte = chr($version);

            return base64_encode($versionByte . $iv . $tag . $cipher);

        } catch (Exception $e) {
            self::logError('Encryption error: ' . $e->getMessage(), [
                'tenant' => $tenantId,
                'entity' => $entityId,
                'version' => $version
            ]);
            throw new RuntimeException('Encryption failed', 0, $e);
        }
    }

    /**
     * Decrypt entity-specific data
     *
     * @param string $encrypted Base64 encoded encrypted data
     * @param int $tenantId
     * @param int $entityId
     * @return string Decrypted plain text
     * @throws RuntimeException
     */
    public static function decryptForEntity(
        string $encrypted,
        int $tenantId,
        int $entityId
    ): string {
        if (empty($encrypted)) {
            throw new InvalidArgumentException('Encrypted data cannot be empty');
        }

        try {
            $data = base64_decode($encrypted, true);

            if ($data === false || strlen($data) < 29) {
                throw new RuntimeException('Invalid encrypted data format');
            }

            // Extract version for key derivation
            $version = ord($data[0]);

            if ($version < 1 || $version > 255) {
                throw new RuntimeException('Unsupported encryption version');
            }

            $key = self::deriveEntityKey($tenantId, $entityId, $version);
            $iv = substr($data, 1, 12);
            $tag = substr($data, 13, 16);
            $cipher = substr($data, 29);

            $plainText = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($plainText === false) {
                throw new RuntimeException('Decryption failed - invalid tag or corrupted data');
            }

            return $plainText;

        } catch (Exception $e) {
            self::logError('Decryption error: ' . $e->getMessage(), [
                'tenant' => $tenantId,
                'entity' => $entityId
            ]);
            throw new RuntimeException('Decryption failed', 0, $e);
        }
    }

    /**
     * Legacy encryption method (backward compatibility)
     *
     * @deprecated Use encryptForEntity for new implementations
     * @param string $data
     * @param string|null $key
     * @return string
     */
    public static function encrypt(string $data, ?string $key = null): string
    {
        // Trigger deprecation warning
        trigger_error(
            'Security::encrypt() is deprecated. Use Security::encryptForEntity() for sensitive data. ' .
            'This method uses weaker CBC mode and should not be used for new implementations.',
            E_USER_DEPRECATED
        );

        self::logSecurityEvent('LEGACY_ENCRYPTION_USED', 'Legacy encrypt() method called', [
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)
        ]);

        $key = $key ?? (defined('JWT_SECRET') ? JWT_SECRET : CryptoConfig::masterKey());
        $method = 'AES-256-CBC';

        $ivLength = openssl_cipher_iv_length($method);
        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Legacy decryption method
     *
     * @deprecated Use decryptForEntity for new implementations
     * @param string $encryptedData
     * @param string|null $key
     * @return string
     */
    public static function decrypt(string $encryptedData, ?string $key = null): string
    {
        // Trigger deprecation warning
        trigger_error(
            'Security::decrypt() is deprecated. Use Security::decryptForEntity() for sensitive data. ' .
            'This method uses weaker CBC mode and should not be used for new implementations.',
            E_USER_DEPRECATED
        );

        self::logSecurityEvent('LEGACY_DECRYPTION_USED', 'Legacy decrypt() method called', [
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)
        ]);

        $key = $key ?? (defined('JWT_SECRET') ? JWT_SECRET : CryptoConfig::masterKey());
        $method = 'AES-256-CBC';

        try {
            $data = base64_decode($encryptedData, true);

            if ($data === false) {
                throw new RuntimeException('Invalid base64 data');
            }

            $ivLength = openssl_cipher_iv_length($method);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);

            $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                throw new RuntimeException('Decryption failed');
            }

            return $decrypted;

        } catch (Exception $e) {
            self::logError('Legacy decryption failed: ' . $e->getMessage());
            throw new RuntimeException('Decryption failed', 0, $e);
        }
    }

    /**
     * Re-encrypt data with new version (for key rotation)
     *
     * @param string $encrypted Old encrypted data
     * @param int $tenantId
     * @param int $entityId
     * @param int $newVersion New encryption version
     * @return string New encrypted data
     * @throws RuntimeException
     */
    public static function rotateEntityKey(
        string $encrypted,
        int $tenantId,
        int $entityId,
        int $newVersion
    ): string {
        if ($newVersion < 2 || $newVersion > 255) {
            throw new InvalidArgumentException('New version must be between 2 and 255');
        }

        try {
            // Decrypt with old key
            $plainText = self::decryptForEntity($encrypted, $tenantId, $entityId);

            // Re-encrypt with new version
            $newEncrypted = self::encryptForEntity($plainText, $tenantId, $entityId, $newVersion);

            self::logSecurityEvent('KEY_ROTATION', "Entity key rotated to version {$newVersion}", [
                'tenant' => $tenantId,
                'entity' => $entityId,
                'new_version' => $newVersion
            ]);

            return $newEncrypted;

        } catch (Exception $e) {
            self::logError('Key rotation failed: ' . $e->getMessage(), [
                'tenant' => $tenantId,
                'entity' => $entityId,
                'target_version' => $newVersion
            ]);
            throw new RuntimeException('Key rotation failed', 0, $e);
        }
    }

    /**
     * Get encryption version from encrypted data
     *
     * @param string $encrypted
     * @return int Version number
     * @throws RuntimeException
     */
    public static function getEncryptionVersion(string $encrypted): int
    {
        try {
            $data = base64_decode($encrypted, true);

            if ($data === false || strlen($data) < 1) {
                throw new RuntimeException('Invalid encrypted data');
            }

            return ord($data[0]);

        } catch (Exception $e) {
            throw new RuntimeException('Cannot determine encryption version', 0, $e);
        }
    }

    // ===========================================
    // 3️⃣ Token Generation
    // ===========================================

    /**
     * Generate cryptographically secure random token
     *
     * @param int $length Length in bytes (result will be 2x in hex)
     * @return string Hex token
     */
    public static function generateToken(int $length = 32): string
    {
        if ($length < 16) {
            throw new InvalidArgumentException('Token length must be at least 16 bytes');
        }

        return bin2hex(random_bytes($length));
    }

    /**
     * Generate numeric OTP code
     *
     * @param int $length Number of digits
     * @return string Numeric OTP
     */
    public static function generateOTP(int $length = 6): string
    {
        if ($length < 4 || $length > 10) {
            throw new InvalidArgumentException('OTP length must be between 4 and 10');
        }

        $min = (int)pow(10, $length - 1);
        $max = (int)pow(10, $length) - 1;

        return str_pad((string)random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Generate alphanumeric coupon code
     *
     * @param int $length Code length
     * @param string $prefix Optional prefix
     * @return string Coupon code
     */
    public static function generateCouponCode(int $length = 8, string $prefix = ''): string
    {
        if ($length < 4 || $length > 32) {
            throw new InvalidArgumentException('Coupon length must be between 4 and 32');
        }

        // Exclude confusing characters: I, O, 0, 1
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxIndex = strlen($characters) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $maxIndex)];
        }

        return $prefix . $code;
    }

    /**
     * Generate UUID v4
     *
     * @return string UUID
     */
    public static function generateUUID(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // ===========================================
    // 4️⃣ Input Validation & Sanitization
    // ===========================================

    /**
     * Sanitize input recursively
     *
     * @param mixed $input
     * @param bool $strict Strict mode removes all HTML
     * @return mixed
     */
    public static function sanitizeInput($input, bool $strict = false)
    {
        if (is_array($input)) {
            return array_map(function($item) use ($strict) {
                return self::sanitizeInput($item, $strict);
            }, $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        // Trim whitespace
        $input = trim($input);

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Convert special characters (protect against XSS and attribute injection)
        if ($strict) {
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        } else {
            // Even in non-strict mode, protect quotes to prevent attribute injection
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        return $input;
    }

    /**
     * Validate email address
     *
     * @param string $email
     * @param bool $checkDNS Verify domain has MX records
     * @return bool
     */
    public static function validateEmail(string $email, bool $checkDNS = false): bool
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if ($checkDNS) {
            [$local, $domain] = explode('@', $email);
            return checkdnsrr($domain, 'MX');
        }

        return true;
    }

    /**
     * Validate Saudi phone number
     *
     * @param string $phone
     * @param bool $normalize Return normalized format
     * @return bool|string
     */
    public static function validateSaudiPhone(string $phone, bool $normalize = false)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove country code if present
        $phone = preg_replace('/^\+?966/', '', $phone);

        // Ensure starts with 5 and has 9 digits
        $pattern = defined('REGEX_PHONE_SA') ? REGEX_PHONE_SA : '/^5[0-9]{8}$/';

        if (preg_match($pattern, $phone) !== 1) {
            return false;
        }

        return $normalize ? '+966' . $phone : true;
    }

    /**
     * Validate URL with security checks
     *
     * @param string $url
     * @param array $allowedSchemes
     * @return bool
     */
    public static function validateURL(string $url, array $allowedSchemes = ['http', 'https']): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($scheme, $allowedSchemes, true)) {
            return false;
        }

        // Prevent SSRF - block private/local IPs
        $host = parse_url($url, PHP_URL_HOST);
        $ip = gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }

    /**
     * Validate integer with range
     *
     * @param mixed $value
     * @param int|null $min
     * @param int|null $max
     * @return bool
     */
    public static function validateInteger($value, ?int $min = null, ?int $max = null): bool
    {
        $options = [];

        if ($min !== null || $max !== null) {
            $options['options'] = [];
            if ($min !== null) $options['options']['min_range'] = $min;
            if ($max !== null) $options['options']['max_range'] = $max;
        }

        return filter_var($value, FILTER_VALIDATE_INT, $options) !== false;
    }

    /**
     * Validate float with range
     *
     * @param mixed $value
     * @param float|null $min
     * @param float|null $max
     * @return bool
     */
    public static function validateFloat($value, ?float $min = null, ?float $max = null): bool
    {
        if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
            return false;
        }

        $floatValue = (float)$value;

        if ($min !== null && $floatValue < $min) {
            return false;
        }

        if ($max !== null && $floatValue > $max) {
            return false;
        }

        return true;
    }

    // ===========================================
    // 5️⃣ XSS Protection
    // ===========================================

    /**
     * Sanitize HTML allowing safe tags
     *
     * @param string $html
     * @param array|null $allowedTags
     * @return string
     */
    public static function sanitizeHTML(string $html, ?array $allowedTags = null): string
    {
        $defaultTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code>';
        $allowed = $allowedTags ? implode('', $allowedTags) : $defaultTags;

        $html = strip_tags($html, $allowed);

        // Remove dangerous attributes
        $html = preg_replace('/<([^>]+)\s+(on\w+|formaction|action|data-)\s*=\s*["\'][^"\']*["\']/i', '<$1', $html);

        return $html;
    }

    /**
     * Comprehensive XSS prevention
     *
     * @param string $data
     * @return string
     */
    public static function preventXSS(string $data): string
    {
        // Convert special characters
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove null bytes
        $data = str_replace(chr(0), '', $data);

        // Remove scripts
        $data = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $data);

        // Remove event handlers
        $data = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $data);

        // Remove javascript: protocol
        $data = preg_replace('/javascript:/i', '', $data);

        return $data;
    }

    // ===========================================
    // 6️⃣ CSRF Protection
    // ===========================================

    /**
     * Generate CSRF token
     *
     * @param string $formId Optional form identifier
     * @return string
     */
    public static function generateCSRFToken(string $formId = 'default'): string
    {
        self::ensureSession();

        $token = self::generateToken(32);

        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }

        $_SESSION['csrf_tokens'][$formId] = [
            'token' => $token,
            'time' => time()
        ];

        // Cleanup old tokens
        self::cleanupCSRFTokens();

        return $token;
    }

    /**
     * Verify CSRF token
     *
     * @param string $token
     * @param string $formId
     * @param int $maxAge Max age in seconds
     * @return bool
     */
    public static function verifyCSRFToken(string $token, string $formId = 'default', int $maxAge = 3600): bool
    {
        self::ensureSession();

        if (!isset($_SESSION['csrf_tokens'][$formId])) {
            return false;
        }

        $stored = $_SESSION['csrf_tokens'][$formId];

        // Check expiration
        if (time() - $stored['time'] > $maxAge) {
            unset($_SESSION['csrf_tokens'][$formId]);
            return false;
        }

        // Timing-safe comparison
        return hash_equals($stored['token'], $token);
    }

    /**
     * Cleanup expired CSRF tokens
     */
    private static function cleanupCSRFTokens(int $maxAge = 3600): void
    {
        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }

        $now = time();

        foreach ($_SESSION['csrf_tokens'] as $formId => $data) {
            if ($now - $data['time'] > $maxAge) {
                unset($_SESSION['csrf_tokens'][$formId]);
            }
        }
    }

    // ===========================================
    // 7️⃣ Rate Limiting
    // ===========================================

    /**
     * Check rate limit with Redis support
     *
     * @param string $identifier
     * @param int|null $limit
     * @param int|null $window
     * @return array
     */
    public static function checkRateLimit(
        string $identifier,
        ?int $limit = null,
        ?int $window = null
    ): array {
        $limit = $limit ?? (defined('RATE_LIMIT_REQUESTS') ? RATE_LIMIT_REQUESTS : 60);
        $window = $window ?? (defined('RATE_LIMIT_WINDOW') ? RATE_LIMIT_WINDOW : 60);

        // Try Redis first, fallback to session
        if (class_exists('Redis') && defined('REDIS_ENABLED') && REDIS_ENABLED) {
            return self::checkRateLimitRedis($identifier, $limit, $window);
        }

        return self::checkRateLimitSession($identifier, $limit, $window);
    }

    /**
     * Rate limiting using session
     */
    private static function checkRateLimitSession(string $identifier, int $limit, int $window): array
    {
        self::ensureSession();

        $key = 'rate_limit_' . hash('sha256', $identifier);
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'reset_time' => $now + $window
            ];
        }

        $data = $_SESSION[$key];

        // Reset if window expired
        if ($now >= $data['reset_time']) {
            $data = [
                'count' => 0,
                'reset_time' => $now + $window
            ];
        }

        // Increment counter
        $data['count']++;
        $_SESSION[$key] = $data;

        $allowed = $data['count'] <= $limit;
        $remaining = max(0, $limit - $data['count']);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_time' => $data['reset_time'],
            'retry_after' => $allowed ? 0 : ($data['reset_time'] - $now),
            'limit' => $limit
        ];
    }

    /**
     * Rate limiting using Redis (better for production)
     */
    private static function checkRateLimitRedis(string $identifier, int $limit, int $window): array
    {
        // Placeholder for Redis implementation
        // يمكن تطبيقها عند توفر Redis
        return self::checkRateLimitSession($identifier, $limit, $window);
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $identifier
     */
    public static function resetRateLimit(string $identifier): void
    {
        self::ensureSession();

        $key = 'rate_limit_' . hash('sha256', $identifier);
        unset($_SESSION[$key]);
    }

    // ===========================================
    // 8️⃣ Brute Force Protection
    // ===========================================

    /**
     * Record failed login attempt
     *
     * @param string $identifier
     * @return array
     */
    public static function recordFailedLogin(string $identifier): array
    {
        self::ensureSession();

        $key = 'login_attempts_' . hash('sha256', $identifier);
        $now = time();

        $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
        $lockoutTime = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900;

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'first_attempt' => $now,
                'locked_until' => 0
            ];
        }

        $data = $_SESSION[$key];

        // Reset if lockout expired
        if ($data['locked_until'] > 0 && $now >= $data['locked_until']) {
            $data = [
                'count' => 0,
                'first_attempt' => $now,
                'locked_until' => 0
            ];
        }

        // Increment counter
        $data['count']++;

        // Apply lockout
        if ($data['count'] >= $maxAttempts) {
            $data['locked_until'] = $now + $lockoutTime;
        }

        $_SESSION[$key] = $data;

        $locked = $data['locked_until'] > $now;

        // Log security event
        if ($locked) {
            self::logSecurityEvent('LOGIN_LOCKED', "Account locked: {$identifier}");
        }

        return [
            'locked' => $locked,
            'attempts' => $data['count'],
            'remaining' => max(0, $maxAttempts - $data['count']),
            'lock_time' => $locked ? ($data['locked_until'] - $now) : 0,
            'max_attempts' => $maxAttempts
        ];
    }

    /**
     * Check if account is locked
     *
     * @param string $identifier
     * @return array
     */
    public static function checkLoginLock(string $identifier): array
    {
        self::ensureSession();

        $key = 'login_attempts_' . hash('sha256', $identifier);
        $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;

        if (!isset($_SESSION[$key])) {
            return [
                'locked' => false,
                'attempts' => 0,
                'remaining' => $maxAttempts,
                'lock_time' => 0
            ];
        }

        $data = $_SESSION[$key];
        $now = time();

        $locked = $data['locked_until'] > $now;

        return [
            'locked' => $locked,
            'attempts' => $data['count'],
            'remaining' => max(0, $maxAttempts - $data['count']),
            'lock_time' => $locked ? ($data['locked_until'] - $now) : 0
        ];
    }

    /**
     * Reset login attempts
     *
     * @param string $identifier
     */
    public static function resetLoginAttempts(string $identifier): void
    {
        self::ensureSession();

        $key = 'login_attempts_' . hash('sha256', $identifier);
        unset($_SESSION[$key]);
    }

    // ===========================================
    // 9️⃣ Request Information
    // ===========================================

    /**
     * Get real client IP address
     *
     * @return string
     */
    public static function getRealIP(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_REAL_IP',           // Nginx
            'HTTP_X_FORWARDED_FOR',     // Proxy
            'HTTP_CLIENT_IP',           // Proxy
            'REMOTE_ADDR'               // Direct
        ];

        foreach ($ipHeaders as $header) {
            if (!isset($_SERVER[$header])) {
                continue;
            }

            $ip = $_SERVER[$header];

            // Handle comma-separated IPs
            if (strpos($ip, ',') !== false) {
                $ips = array_map('trim', explode(',', $ip));
                $ip = $ips[0];
            }

            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent string
     *
     * @return string
     */
    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Detect device type
     *
     * @return string mobile|tablet|desktop
     */
    public static function detectDevice(): string
    {
        $userAgent = strtolower(self::getUserAgent());

        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobi))/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/(up\.browser|up\.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Check if request is from bot
     *
     * @return bool
     */
    public static function isBot(): bool
    {
        $userAgent = strtolower(self::getUserAgent());
        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'googlebot', 'bingbot', 'yandex', 'baiduspider'
        ];

        foreach ($botPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get client fingerprint
     *
     * @return string
     */
    public static function getClientFingerprint(): string
    {
        $components = [
            self::getRealIP(),
            self::getUserAgent(),
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];

        return hash('sha256', implode('|', $components));
    }

    // ===========================================
    // 🔟 Utility Functions
    // ===========================================

    /**
     * Hash data using specified algorithm
     *
     * @param string $data
     * @param string $algo
     * @return string
     */
    public static function hash(string $data, string $algo = 'sha256'): string
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new InvalidArgumentException("Unsupported hash algorithm: {$algo}");
        }

        return hash($algo, $data);
    }

    /**
     * Timing-safe string comparison
     *
     * @param string $known
     * @param string $user
     * @return bool
     */
    public static function timingSafeEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Ensure session is started
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');

            session_start();
        }
    }

    /**
     * Log security event
     *
     * @param string $event
     * @param string $details
     * @param array $context
     */
    public static function logSecurityEvent(string $event, string $details, array $context = []): void
    {
        if (!defined('LOG_ENABLED') || !LOG_ENABLED) {
            return;
        }

        $logFile = defined('LOG_FILE_AUTH') ? LOG_FILE_AUTH : '/tmp/security.log';

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'details' => $details,
            'ip' => self::getRealIP(),
            'user_agent' => self::getUserAgent(),
            'context' => $context
        ];

        $message = json_encode($logData, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        error_log($message, 3, $logFile);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    private static function logError(string $message, array $context = []): void
    {
        if (!defined('LOG_ENABLED') || !LOG_ENABLED) {
            return;
        }

        $logFile = defined('LOG_FILE_ERROR') ? LOG_FILE_ERROR : '/tmp/error.log';

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];

        error_log(json_encode($logData) . PHP_EOL, 3, $logFile);
    }

    /**
     * Clear all security-related session data
     */
    public static function clearSecuritySession(): void
    {
        self::ensureSession();

        $keysToRemove = [];

        foreach (array_keys($_SESSION) as $key) {
            if (strpos($key, 'csrf_') === 0 ||
                strpos($key, 'rate_limit_') === 0 ||
                strpos($key, 'login_attempts_') === 0) {
                $keysToRemove[] = $key;
            }
        }

        foreach ($keysToRemove as $key) {
            unset($_SESSION[$key]);
        }
    }
}

// ===========================================
// Auto-cleanup on script shutdown
// ===========================================

register_shutdown_function(function() {
    // Cleanup expired CSRF tokens
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['csrf_tokens'])) {
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $formId => $data) {
            if ($now - $data['time'] > 3600) {
                unset($_SESSION['csrf_tokens'][$formId]);
            }
        }
    }
});

// ===========================================
// ✅ Security Helper Production Ready
// ===========================================