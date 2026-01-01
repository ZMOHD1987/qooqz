<?php
// htdocs/admin/login.php
// صفحة تسجيل الدخول للوحة الإدارة
// تحسينات: 
// - تبسيط التحقق من الجلسة والكوكي لتجنب فقدان الجلسة المتكرر.
// - إضافة CSRF token للأمان في النموذج.
// - دعم fallback بدون JavaScript (نموذج POST عادي يتعامل server-side مع تسجيل الدخول).
// - إعادة توجيه server-side بعد نجاح الدخول.
// - تحسين الرسائل والأخطاء لتكون أكثر وضوحًا.
// - إزالة الاعتماد الزائد على cURL/file_get_contents للتحقق الداخلي لتجنب الفشل في الاستضافات المجانية.
// - إضافة تسجيل أخطاء بسيط للـlog.
// Save as UTF-8 without BOM.

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

// ملف log للأخطاء (اختياري، يمكن تعطيله في الإنتاج)
$logFile = __DIR__ . '/../../api/error_debug.log';

// 1) إذا الجلسة موجودة فعلاً، نعتبر المستخدم مسجلاً
if (!empty($_SESSION['user']) && !empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// 2) معالجة تسجيل الدخول من النموذج (POST عادي كـfallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['identifier']) && !empty($_POST['password'])) {
    // تحقق CSRF إذا مفعل
    if (!empty($_SESSION['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $identifier = trim($_POST['identifier']);
        $password = $_POST['password'];

        // استدعاء API تسجيل الدخول (بدون cURL للبساطة، استخدم require إذا كان محلي)
        $loginPath = __DIR__ . '/../../api/users/login.php';
        if (is_readable($loginPath)) {
            // محاكاة POST داخليًا
            $_POST = ['identifier' => $identifier, 'password' => $password]; // override للـlogin script
            ob_start();
            include $loginPath;
            $response = ob_get_clean();
            $json = json_decode($response, true);

            if (!empty($json['success']) && !empty($json['user'])) {
                // إعادة بناء الجلسة
                $_SESSION['user'] = $json['user'];
                $_SESSION['user_id'] = $json['user']['id'];
                $_SESSION['username'] = $json['user']['username'];
                $_SESSION['role_id'] = $json['user']['role_id'];
                $_SESSION['permissions'] = $json['user']['permissions'] ?? [];
                header('Location: dashboard.php');
                exit;
            } else {
                $errorMsg = $json['message'] ?? 'فشل تسجيل الدخول';
            }
        } else {
            $errorMsg = 'خطأ في الاتصال بالنظام.';
        }
    } else {
        $errorMsg = 'رمز الأمان غير صالح.';
    }
} else {
    $errorMsg = '';
}

// توليد CSRF token إذا لم يكن موجودًا
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// عرض الصفحة إذا لم يتم الدخول
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تسجيل دخول المشرف</title>
<style>
body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:2rem; }
.container { max-width:420px; margin:40px auto; background:#fff; padding:1.2rem 1.4rem; border-radius:8px; box-shadow:0 6px 18px rgba(31,41,55,0.06); }
h2 { text-align:center; margin:0 0 1rem 0; }
label { display:block; margin-bottom:.6rem; font-weight:600; color:#333; }
input[type="text"], input[type="password"] { width:100%; padding:.6rem; margin:.25rem 0 .8rem 0; box-sizing:border-box; border:1px solid #ddd; border-radius:6px; }
button { width:100%; padding:.6rem; background:#0d6efd; color:#fff; border:none; border-radius:6px; font-size:1rem; cursor:pointer; }
button:disabled { opacity:.7; cursor:not-allowed; }
.msg { margin-top:.8rem; text-align:center; color:#b00; min-height:1.2em; }
.msg.success { color:#28a745; }
.note { font-size:.85rem; color:#666; margin-top:.6rem; text-align:center; }
</style>
</head>
<body>
  <div class="container">
    <h2>تسجيل دخول الإدارة</h2>
    <?php if ($errorMsg): ?>
      <div class="msg"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
    <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
      <label>البريد الإلكتروني أو اسم المستخدم
        <input type="text" name="identifier" id="identifier" required autofocus>
      </label>
      <label>كلمة المرور
        <input type="password" name="password" id="password" required>
      </label>
      <button type="submit" id="btn">دخول</button>
      <div id="jsMsg" class="msg" role="status" aria-live="polite"></div>
      <div class="note">
        تأكد أنك تستخدم حسابًا له صلاحيات إدارة. بعد تسجيل الدخول سيتم توجيهك للوحة التحكم.
      </div>
    </form>
    <noscript>
      <p style="color:#b00; text-align:center; margin-top:1rem;">
        JavaScript مطلوب لتسجيل الدخول السلس، لكن النموذج يعمل بدونها عبر POST عادي.
      </p>
    </noscript>
  </div>
<script>
(function(){
  const form = document.getElementById('loginForm');
  const jsMsg = document.getElementById('jsMsg');
  const btn = document.getElementById('btn');
  form.addEventListener('submit', async (e) => {
    if (!navigator.onLine) return; // إذا offline، دع POST عادي يعمل
    e.preventDefault();
    jsMsg.textContent = '';
    btn.disabled = true;
    const fd = new FormData(form);
    try {
      const res = await fetch('/api/users/login.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (res.ok && json.success) {
        jsMsg.classList.add('success');
        jsMsg.textContent = 'تم تسجيل الدخول — جارٍ التحويل...';
        setTimeout(() => { window.location.href = 'dashboard.php'; }, 600);
        return;
      }
      jsMsg.textContent = json.message || 'فشل تسجيل الدخول';
    } catch (err) {
      console.error(err);
      jsMsg.textContent = 'خطأ في الاتصال بالخادم. جرب الإرسال العادي.';
      form.submit(); // fallback إلى POST عادي إذا فشل JS
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>
</body>
</html>