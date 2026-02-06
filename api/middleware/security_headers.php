<?php
// htdocs/api/middleware/security_headers.php
// Middleware لإضافة Security Headers

class SecurityHeadersMiddleware {
    /**
     * معالجة الطلب
     * 
     * @param array $data
     * @param callable $next
     * @return mixed
     */
    public static function handle($data, callable $next) {
        // إضافة Security Headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // إزالة Server header للأمان
        header_remove('X-Powered-By');
        
        // استمرار للـ middleware التالي
        return $next($data);
    }
}