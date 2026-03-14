<?php
declare(strict_types=1);
/**
 * admin/logout.php
 * Safe and robust logout handler.
 */
session_start();

// فقط POST مسموح
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/login.php');
    exit;
}

// تحقق من CSRF فقط إذا كان الـ token موجودًا في السيشن
$posted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

// إذا كان هناك token في السيشن، تحقق من مطابقته
if ($session_token !== '' && !hash_equals($session_token, $posted_token)) {
    // لا نعطي 400 هنا لأنها قد تكون جلسة قديمة أو منتهية
    // فقط نكمل تسجيل الخروج بأمان
}

// تنظيف الجلسة
$_SESSION = [];

// حذف كوكي الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// حذف كوكيز إضافية شائعة
$extraCookies = ['session_token', 'remember_me', '__test', 'PHPSESSID'];
$host = $_SERVER['HTTP_HOST'] ?? 'mzmz.rf.gd';
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

foreach ($extraCookies as $name) {
    setcookie($name, '', time() - 42000, '/', $host, $secure, false);
    setcookie($name, '', time() - 42000, '/', '.' . $host, $secure, false);
    setcookie($name, '', time() - 42000, '/', '.mzmz.rf.gd', $secure, false);
}

// تدمير الجلسة على السيرفر
session_destroy();

// منع الكاش
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// صفحة إعادة توجيه مع JS لحذف الكوكيز على العميل
$loginUrl = '/admin/login.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>جاري تسجيل الخروج...</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { font-family: system-ui, sans-serif; background:#f8fafc; color:#1e293b; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { text-align:center; padding:30px; background:white; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:400px; }
    h2 { margin:0 0 16px; color:#dc2626; }
    p { margin:8px 0; color:#64748b; }
    .spinner { width:40px; height:40px; border:4px solid #e2e8f0; border-top:4px solid #3b82f6; border-radius:50%; animation:spin 1s linear infinite; margin:20px auto; }
    @keyframes spin { to { transform:rotate(360deg); } }
  </style>
</head>
<body>
  <div class="box">
    <div class="spinner"></div>
    <h2>تم تسجيل الخروج بنجاح</h2>
    <p>يتم إعادة توجيهك إلى صفحة تسجيل الدخول...</p>
  </div>
  <script>
    // حذف الكوكيز على مستوى العميل (غير HttpOnly)
    (function(){
      const names = ['PHPSESSID', 'session_token', 'remember_me', '__test'];
      const domains = [location.hostname, '.mzmz.rf.gd'];
      names.forEach(name => {
        domains.forEach(domain => {
          document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=' + domain;
          document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/admin;domain=' + domain;
        });
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
      });
      setTimeout(() => {
        window.location.replace('<?php echo $loginUrl; ?>');
      }, 800);
    })();
  </script>
</body>
</html>