<?php
// htdocs/api/helpers/CSRF.php
// Simple CSRF helper: generate token, validate token, and render hidden input.
// Usage:
//   $token = CSRF::token();
//   echo CSRF::inputField(); // prints hidden input with token
//   if (!CSRF::validate($_POST['csrf_token'])) { /* reject */ }

class CSRF
{
    // Token session key names
    private const TOKEN_KEY = 'csrf_token';
    private const TIME_KEY  = 'csrf_token_time';

    // Default token lifetime in seconds (1 hour)
    private const DEFAULT_MAX_AGE = 3600;

    // Ensure session started
    private static function ensureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // We don't start session here with special params — session_config.php should be included by caller
            session_start();
        }
    }

    // Return current token (generate if missing or expired when $regenerate=true)
    public static function token(bool $regenerate = false): string
    {
        self::ensureSession();

        if ($regenerate || empty($_SESSION[self::TOKEN_KEY])) {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                // fallback
                $token = bin2hex(openssl_random_pseudo_bytes(32));
            }
            $_SESSION[self::TOKEN_KEY] = $token;
            $_SESSION[self::TIME_KEY]  = time();
            return $token;
        }

        return (string) $_SESSION[self::TOKEN_KEY];
    }

    // Render hidden input field (name can be changed)
    public static function inputField(string $name = 'csrf_token'): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . $token . '">';
    }

    // Validate posted token; returns true if valid, false otherwise.
    // $maxAge seconds default 3600. If $invalidateAfterUse true, token will be removed to prevent reuse.
    public static function validate(?string $token, int $maxAge = self::DEFAULT_MAX_AGE, bool $invalidateAfterUse = false): bool
    {
        self::ensureSession();

        if (empty($token) || empty($_SESSION[self::TOKEN_KEY]) || empty($_SESSION[self::TIME_KEY])) {
            return false;
        }

        $stored = $_SESSION[self::TOKEN_KEY];
        $time   = (int) $_SESSION[self::TIME_KEY];

        // constant-time comparison
        if (!hash_equals($stored, (string)$token)) {
            return false;
        }

        if ($maxAge > 0 && (time() - $time) > $maxAge) {
            return false;
        }

        if ($invalidateAfterUse) {
            unset($_SESSION[self::TOKEN_KEY], $_SESSION[self::TIME_KEY]);
        }

        return true;
    }

    // Convenience: validate and throw exception with message (optional)
    public static function requireValid(?string $token, int $maxAge = self::DEFAULT_MAX_AGE, bool $invalidateAfterUse = false)
    {
        if (!self::validate($token, $maxAge, $invalidateAfterUse)) {
            http_response_code(400);
            throw new RuntimeException('Invalid CSRF token.');
        }
    }
}