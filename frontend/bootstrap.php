<?php
/**
 * htdocs/frontend/bootstrap.php
 * * نسخة محسنة وآمنة بالكامل
 */

/* ===============================
   1. الإعدادات الأولية للبيئة
================================ */
$BASE_DIR = rtrim(__DIR__, '/\\');

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
}
if (!defined('DEBUG')) {
    define('DEBUG', ENVIRONMENT === 'development');
}

// ضبط عرض الأخطاء بناءً على وضع التطوير
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('display_startup_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);

/* ===============================
   2. نظام السجلات (Logging)
================================ */
$FRONT_LOG = $BASE_DIR . '/storage/frontend_log.txt';

if (!function_exists('frontend_log')) {
    function frontend_log($msg): void {
        global $FRONT_LOG;
        try {
            $line = '[' . date('c') . '] ' . (is_string($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
            // التحقق من قابلية الكتابة قبل المحاولة لتجنب أخطاء النظام
            if (is_writable(dirname($FRONT_LOG))) {
                file_put_contents($FRONT_LOG, $line, FILE_APPEND | LOCK_EX);
            }
        } catch (Throwable $e) {
            // صامت في حال فشل السجل تماماً
        }
    }
}

/* ===============================
   3. دالة التضمين الآمنة (Safe Include)
================================ */
if (!function_exists('safe_include')) {
    /**
     * @param string|array $files ملف أو مجموعة ملفات
     * @param bool $required إذا كان الملف ضرورياً لتشغيل النظام
     */
    function safe_include($files, bool $required = true): bool {
        global $BASE_DIR;
        $found = false;
        foreach ((array)$files as $file) {
            $path = $BASE_DIR . '/' . ltrim($file, '/');
            if (is_readable($path)) {
                require_once $path;
                $found = true;
                break; // التوقف عند أول ملف يتم العثور عليه (في حال كانت مصفوفة بدائل)
            }
        }

        if (!$found && $required) {
            frontend_log("CRITICAL: Failed to load required file(s): " . implode(', ', (array)$files));
            if (DEBUG) {
                die("Fatal Error: Missing core file: " . implode(', ', (array)$files));
            } else {
                die("Service temporarily unavailable.");
            }
        }
        return $found;
    }
}

/* ===============================
   4. بدء الجلسة (بشكل آمن)
================================ */
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // التحقق من HTTPS حتى خلف البروكسي (مثل Cloudflare)
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                 ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $is_secure,
        'cookie_samesite' => 'Lax',
    ]);
}

/* ===============================
   5. تحميل الإعدادات (قبل الخدمات)
================================ */
safe_include('config/app.php');
safe_include('config/api.php');
safe_include('config/seo.php');

/* ===============================
   6. تحميل الخدمات الأساسية (Core)
================================ */
safe_include('core/ApiClient.php');
safe_include('core/Auth.php');
safe_include('core/I18n.php');
safe_include('core/Helper.php');

/* ===============================
   7. حاوية الخدمات (Container)
================================ */
$container = [
    'api'          => null,
    'current_user' => null,
    'lang'         => 'ar', // الافتراضي
    'seo'          => []
];

/* ===============================
   8. تهيئة عميل الـ API
================================ */
try {
    // التأكد من أن الثوابت معرفة في ملف config/api.php
    $baseUrl = defined('API_BASE_URL') ? API_BASE_URL : '';
    $timeout = defined('API_TIMEOUT') ? API_TIMEOUT : 10;
    
    if ($baseUrl) {
        $container['api'] = new ApiClient($baseUrl, $timeout);
    }
} catch (Throwable $e) {
    frontend_log('API Client Init Error: ' . $e->getMessage());
}

/* ===============================
   9. معالجة بيانات المستخدم الحالي
================================ */
try {
    // 1. التحقق من وجود الجلسة
    if (!empty($_SESSION['current_user'])) {
        $container['current_user'] = $_SESSION['current_user'];
    } 
    // 2. إذا لم توجد الجلسة ولكن يوجد Token، نجلب البيانات من الـ API
    elseif (!empty($_SESSION['access_token']) && $container['api']) {
        $me = $container['api']->get('/auth/me', [
            'Authorization' => 'Bearer ' . $_SESSION['access_token']
        ]);

        if (!empty($me['id'])) {
            $container['current_user'] = $me;
            $_SESSION['current_user'] = $me; // تخزين مؤقت (Snapshot)
        }
    }
} catch (Throwable $e) {
    frontend_log('User Resolution Error: ' . $e->getMessage());
    // في حال فشل التوكن، يفضل مسح بيانات الجلسة المنتهية
    unset($_SESSION['current_user'], $_SESSION['access_token']);
}

/* ===============================
   10. اللغة والـ SEO
================================ */
// تحديد اللغة (الأولوية: URL -> Session -> User Profile -> Default)
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? ($container['current_user']['preferred_language'] ?? 'ar');
$_SESSION['lang'] = $lang;
$container['lang'] = $lang;

// إعدادات SEO
$container['seo'] = [
    'title'       => defined('SEO_DEFAULT_TITLE') ? SEO_DEFAULT_TITLE : 'Website',
    'description' => defined('SEO_DEFAULT_DESCRIPTION') ? SEO_DEFAULT_DESCRIPTION : '',
    'keywords'    => defined('SEO_DEFAULT_KEYWORDS') ? SEO_DEFAULT_KEYWORDS : ''
];

/* ===============================
   11. تصدير المتغيرات العامة
================================ */
$GLOBALS['FRONT_CONTAINER'] = $container;
$GLOBALS['CURRENT_USER']    = $container['current_user'];

// سجل نجاح التحميل (اختياري، يفضل إيقافه في الإنتاج لتقليل حجم الملف)
if (DEBUG) {
    frontend_log('Frontend bootstrap loaded successfully');
}