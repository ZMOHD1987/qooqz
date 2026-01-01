<?php
// htdocs/admin/logout.php
// Safe logout + client-side cookie purge fallback.
// 1) Accepts POST with CSRF
// 2) Clears $_SESSION and server-side session cookie
// 3) Sends Set-Cookie headers to expire known cookies for common domains/paths
// 4) Outputs JS page that deletes non-HttpOnly cookies and redirects to login
// Save as UTF-8 without BOM.

session_start();

// Only allow POST logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/login.php');
    exit;
}

// CSRF check
$posted = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if ($sessionToken && !hash_equals($sessionToken, (string)$posted)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid request';
    exit;
}

// Optionally revoke persistent tokens on server-side if you store them (DB).
// Example placeholder (uncomment+implement DB logic if you use tokens):
/*
if (!empty($_COOKIE['session_token'])) {
    $token = $_COOKIE['session_token'];
    // $pdo->prepare("DELETE FROM user_tokens WHERE token = ?")->execute([$token]);
}
*/

// Clear session data
$_SESSION = [];

// Remove session cookie via Set-Cookie for typical domain/path combinations
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // expire the session cookie for current params
    setcookie(session_name(), '', time() - 42000,
        $params["path"] ?? '/', $params["domain"] ?? '', $params["secure"] ?? false, $params["httponly"] ?? true
    );
    // try common host variants
    $host = $_SERVER['HTTP_HOST'] ?? 'mzmz.rf.gd';
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(session_name(), '', time() - 42000, '/', $host, $secure, true);
    setcookie(session_name(), '', time() - 42000, '/', '.' . preg_replace('/^www\./', '', $host), $secure, true);
    setcookie(session_name(), '', time() - 42000, '/', '.mzmz.rf.gd', $secure, true);
    setcookie(session_name(), '', time() - 42000, '/', 'mzmz.rf.gd', $secure, true);
}

// Known persistent cookie names to remove server-side (Set-Cookie)
$cookieNames = ['session_token', 'remember_me', '__test'];
foreach ($cookieNames as $c) {
    // expire for several domain variants/paths
    setcookie($c, '', time() - 42000, '/', $_SERVER['HTTP_HOST'] ?? '', $secure, false);
    setcookie($c, '', time() - 42000, '/', '.' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? ''), $secure, false);
    setcookie($c, '', time() - 42000, '/', '.mzmz.rf.gd', $secure, false);
    setcookie($c, '', time() - 42000, '/', 'mzmz.rf.gd', $secure, false);
}

// Destroy the session data on server
session_destroy();

// Output a small page that runs JS to delete non-HttpOnly cookies and redirect to login.
// This ensures cookies visible in DevTools (and not HttpOnly) are removed immediately.
$loginUrl = '/admin/login.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Logging out…</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { font-family: system-ui, Arial; padding: 20px; background:#fff; color:#222; }
    .box { max-width:520px; margin:40px auto; text-align:center; }
  </style>
</head>
<body>
  <div class="box">
    <h2>تسجيل الخروج…</h2>
    <p>يتم الآن إنهاء الجلسة وحذف ملفات التعريف. سيتم إعادة التوجيه قريباً.</p>
  </div>

  <script>
    (function(){
      // Helper to delete cookie by name for given domain/path variants
      function deleteCookie(name, path, domain) {
        var urlDomain = domain ? ';domain=' + domain : '';
        var urlPath = path ? ';path=' + path : ';path=/';
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT' + urlPath + urlDomain + ';';
      }

      // Names we want to remove on client-side (non-HttpOnly)
      var cookieNames = ['session_token', 'remember_me', '__test', 'PHPSESSID'];

      // Attempt deletion on common path/domain combos
      var host = location.hostname;
      var domains = [host, '.' + host];
      // include the main host variations that the server uses
      domains.push('mzmz.rf.gd');

      cookieNames.forEach(function(name){
        // no domain (default)
        deleteCookie(name, '/', '');
        // host-specific
        domains.forEach(function(d){
          deleteCookie(name, '/', d);
          deleteCookie(name, '/', '.' + d);
        });
        // common path variations
        deleteCookie(name, '/admin', '');
        domains.forEach(function(d){
          deleteCookie(name, '/admin', d);
        });
      });

      // Slight delay then redirect to login
      setTimeout(function(){
        // Final redirect to login page
        window.location.replace('<?php echo $loginUrl; ?>');
      }, 350);
    })();
  </script>
</body>
</html>