<?php
/**
 * api/config/cors.php
 * Defensive, complete CORS configuration and auxiliary HTTP headers.
 *
 * - لا يفترض أن الثوابت ENVIRONMENT أو DEBUG أو APP_ENV معرفة مسبقًا.
 * - يقرأ ENVIRONMENT من الثابت إن وُجد أو من APP_ENV أو من المتغير البيئي، مع افتراضي production.
 * - في بيئة development يكون السلوك متساهلاً لتسهيل التطوير.
 * - في بيئة production يتحقق من قائمة $ALLOWED_ORIGINS البيضاء.
 * - يعالج طلبات OPTIONS (preflight) مباشرةً.
 *
 * Usage: require_once __DIR__ . '/cors.php';
 */

// ---------------------------
// 0. حماية ضد التنفيذ المزدوج
// ---------------------------
if (defined('CORS_CONFIG_INCLUDED')) {
    return;
}
define('CORS_CONFIG_INCLUDED', true);

// ---------------------------
// 1. تحميل config.php (آمن)
// ---------------------------
$configPath = __DIR__ . '/config.php';
if (is_readable($configPath)) {
    // استخدام require_once آمن (قد يعرّف APP_ENV, DEBUG, وغيرها)
    @require_once $configPath;
}

// ---------------------------
// 2. تحديد البيئة DEFENSIVELY
// ---------------------------
// ENVIRONMENT قد يكون معرفًا في config.php أو قد يكون APP_ENV أو متغير بيئة
if (!defined('ENVIRONMENT')) {
    if (defined('APP_ENV')) {
        define('ENVIRONMENT', APP_ENV);
    } else {
        $envFromEnv = getenv('APP_ENV') ?: getenv('ENVIRONMENT');
        define('ENVIRONMENT', $envFromEnv !== false && $envFromEnv !== null ? $envFromEnv : 'production');
    }
}

// DEBUG flag detection (قد يكون DEBUG أو DEBUG_MODE)
if (!defined('DEBUG')) {
    if (defined('DEBUG_MODE')) {
        define('DEBUG', (bool) DEBUG_MODE);
    } else {
        $dbg = getenv('DEBUG') ?: getenv('DEBUG_MODE');
        define('DEBUG', $dbg === '1' || $dbg === 'true' || $dbg === 'on' ? true : false);
    }
}

// ---------------------------
// 3. قائمة الأصول المسموح بها (تعديل هنا للنطاقات الإنتاجية)
// ---------------------------
$ALLOWED_ORIGINS = $ALLOWED_ORIGINS ?? (isset($allowedOrigins) ? $allowedOrigins : null);

// إذا لم تُعرّف قائمة من خارجي، نضع قيمة افتراضية (إخطار: تعديل حسب بيئتك)
if (empty($ALLOWED_ORIGINS) || !is_array($ALLOWED_ORIGINS)) {
    if (ENVIRONMENT === 'development' || ENVIRONMENT === 'dev') {
        $ALLOWED_ORIGINS = ['*']; // تطوير: السماح للجميع
    } else {
        // مثال نطاقات افتراضية — عدّلها إلى نطاقات مشروعك أو اتركها فارغة للرفض الافتراضي
        $ALLOWED_ORIGINS = [
            'https://qooqz.com',
            'https://www.qooqz.com',
            'https://admin.qooqz.com',
            'https://vendor.qooqz.com',
            'https://api.qooqz.com'
        ];
    }
}

// ---------------------------
// 4. helper: تسجيل لوق محلي (آمن)
// ---------------------------
$LOG_PATH = __DIR__ . '/../error_debug.log';
function cors_log($msg) {
    global $LOG_PATH;
    @file_put_contents($LOG_PATH, "[".date('c')."] CORS: " . trim($msg) . PHP_EOL, FILE_APPEND);
}

// ---------------------------
// 5. التحقق من أصل الطلب
// ---------------------------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// دالة التحقق مع دعم النجوم والنطاقات الفرعية
function is_origin_allowed(string $origin, array $allowed): bool {
    if (empty($origin)) return false;
    // السماح العام
    if (in_array('*', $allowed, true)) return true;
    // مقارنة مباشرة
    if (in_array($origin, $allowed, true)) return true;
    // السماح بنطاقات فرعية مثل *.example.com
    foreach ($allowed as $a) {
        if (strpos($a, '*.') === 0) {
            $domain = substr($a, 2); // بعد *.
            // نطابق نهاية الـ origin بـ domain
            if (substr_compare($origin, $domain, -strlen($domain)) === 0) return true;
        }
    }
    return false;
}

// ---------------------------
// 6. تعيين رؤوس CORS الآمنة
// ---------------------------
function set_cors_for_origin(string $origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin', false);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key, X-CSRF-Token, Accept, Accept-Language');
    header('Access-Control-Expose-Headers: Content-Length, Content-Type, X-Total-Count, X-Page-Count, X-Current-Page, X-Per-Page, Authorization, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset', false);
    header('Access-Control-Max-Age: 86400'); // 24h
}

// سياسة التطبيق
if (ENVIRONMENT === 'development' || ENVIRONMENT === 'dev') {
    if ($origin) {
        // إن لم نسمح بالـ '*'، نستخدم القيمة الواردة
        if (is_origin_allowed($origin, $ALLOWED_ORIGINS)) {
            set_cors_for_origin($origin);
        } else {
            // في التطوير، نسمح بأي origin إن لم تُقيّد القوائم
            if (in_array('*', $ALLOWED_ORIGINS, true)) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Credentials: true');
            } else {
                set_cors_for_origin($origin);
            }
        }
    } else {
        // requests from same-origin / server-to-server
        if (in_array('*', $ALLOWED_ORIGINS, true)) header('Access-Control-Allow-Origin: *');
    }
} else {
    // production: التقيّد بالقائمة البيضاء
    if ($origin && is_origin_allowed($origin, $ALLOWED_ORIGINS)) {
        set_cors_for_origin($origin);
    } else {
        // origin غير مسموح — سجل محاولات التطوير فقط
        if ($origin) cors_log("Blocked origin: $origin");
        // لا نعيّن Access-Control-Allow-Origin => المتصفح سيمنع الطلب من origin غير المسموح
    }
}

// ---------------------------
// 7. معالجة طلبات OPTIONS (preflight)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ENVIRONMENT === 'development' && $origin) {
        cors_log("Preflight from: $origin, URI: " . ($_SERVER['REQUEST_URI'] ?? ''));
    }
    // أعد استجابة فارغة ناجحة
    http_response_code(200);
    // إنهاء التنفيذ هنا لمنع متابعة الـ bootstrap أو منطق آخر
    exit(0);
}

// ---------------------------
// 8. رؤوس أمان إضافية (يمكن تعطيل بعضها إذا يسبب مشاكل)
// ---------------------------
header('Content-Type: application/json; charset=utf-8'); // ملائم لواجهات API JSON

// منع clickjacking
header('X-Frame-Options: DENY');
// منع sniffing
header('X-Content-Type-Options: nosniff');
// XSS protective (قد تكون قديمة بعض الشيء لكن لا تضر)
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy بسيط في الإنتاج (تعديل حسب الحاجة)
if (ENVIRONMENT === 'production') {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");
}

// إزالة رؤوس معلومات الخادم إن أمكن
@header_remove('X-Powered-By');

// ---------------------------
// 9. رؤوس الكاش
// ---------------------------
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ---------------------------
// 10. اضافات للمستقبل: helper functions
// ---------------------------
function addCustomHeader(string $name, string $value) {
    header($name . ': ' . $value);
}
function setRateLimitHeaders(int $limit, int $remaining, int $reset) {
    header('X-RateLimit-Limit: ' . $limit);
    header('X-RateLimit-Remaining: ' . $remaining);
    header('X-RateLimit-Reset: ' . $reset);
}
function setPaginationHeaders(int $total, int $page, int $perPage) {
    $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 0;
    header('X-Total-Count: ' . $total);
    header('X-Page-Count: ' . $totalPages);
    header('X-Current-Page: ' . $page);
    header('X-Per-Page: ' . $perPage);
}

// ---------------------------
// 11. تسجيل طلبات التطوير إن رغبت
// ---------------------------
if (ENVIRONMENT === 'development' && DEBUG) {
    cors_log("ENVIRONMENT=" . ENVIRONMENT . " Origin={$origin} RequestURI=" . ($_SERVER['REQUEST_URI'] ?? ''));
}

// انتهى ملف cors.php