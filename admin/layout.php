<?php
// htdocs/admin/layout.php
// Admin master layout — include this at top of admin pages to get header, sidebar, scripts.
// Usage: require_once __DIR__ . '/layout.php'; then output page-specific content into <main id="adminContent"> (or use AJAX loads)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/require_permission.php';
require_login(); // ensure logged in for admin area

// simple helper
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>لوحة الإدارة</title>
  <link rel="stylesheet" href="/admin/assets/css/admin.css">
  <style>
    /* basic modal styling */
    #adminModal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:20000}
    #adminModal .modal-inner{background:#fff;max-width:1100px;width:92%;max-height:85vh;overflow:auto;border-radius:8px;padding:12px;box-sizing:border-box}
    .sidebar { width:240px; float:right; background:#fafafa; height:100vh; overflow:auto; border-left:1px solid #eee; padding:12px }
    .content-area { margin-right:260px; padding:16px }
    .sidebar-list { list-style:none; padding:0; margin:0 }
    .sidebar-link{ display:flex; align-items:center; gap:8px; padding:8px; display:block; color:#222; text-decoration:none }
  </style>
</head>
<body>
  <header style="background:#fff;border-bottom:1px solid #eee;padding:10px 16px;display:flex;justify-content:space-between;align-items:center">
    <div><a href="/admin/dashboard.php">لوحة التحكم</a></div>
    <div>
      مرحبًا، <?php echo h($_SESSION['username'] ?? ($_SESSION['user']['name'] ?? 'مدير')); ?>
      &nbsp;|&nbsp;<a href="/admin/logout.php">تسجيل خروج</a>
    </div>
  </header>

  <aside class="sidebar" role="navigation" aria-label="قائمة الإدارة">
    <?php require_once __DIR__ . '/includes/menu.php'; ?>
  </aside>

  <main class="content-area" id="adminContent" role="main">
    <!-- Content will be loaded here via AJAX, or pages can include this layout and render inline content -->
  </main>

  <!-- Modal for in-page forms -->
  <div id="adminModal" aria-hidden="true">
    <div class="modal-inner" id="adminModalInner"></div>
  </div>

  <script src="/admin/assets/js/image-studio.js" defer></script>
  <script src="/admin/assets/js/admin.js" defer></script>
</body>
</html>