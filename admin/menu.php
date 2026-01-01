<?php
// htdocs/admin/categories.php
// عرض وإدارة الفئات (يعتمد على جدول `categories` الحالي)
// يتطلب: require_permission.php الذي يعرف require_login_and_permission()

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/require_permission.php';
require_login_and_permission('manage_categories');

require_once __DIR__ . '/../api/config/db.php';
$mysqli = connectDB();
if (!($mysqli instanceof mysqli)) {
    echo "خطأ داخلي: لا يمكن الاتصال بقاعدة البيانات.";
    exit;
}

$uploadDir = __DIR__ . '/../uploads/categories';
$uploadRel = '../uploads/categories';

// جلب الفئات (مسطحة). نفترض وجود العمود name كما في بنية DB التي أرسلتها
$categories = [];
$stmt = $mysqli->prepare("SELECT id, parent_id, name, slug, image_url, icon_url, sort_order, is_active, is_featured, created_by, created_at FROM categories ORDER BY parent_id ASC, sort_order ASC, id DESC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $categories[] = $r;
    $stmt->close();
}

// بناء خريطة شجرية بسيطة لترتيب العرض الهرمي
function build_tree($items) {
    $map = [];
    foreach ($items as $it) {
        $pid = $it['parent_id'] === null ? 0 : (int)$it['parent_id'];
        $map[$pid][] = $it;
    }
    return $map;
}

function render_rows($map, $parent = 0, $level = 0, $uploadDir = '', $uploadRel = '') {
    $html = '';
    if (empty($map[$parent])) return $html;
    foreach ($map[$parent] as $c) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
        $img = $c['image_url'] ?: $c['icon_url'] ?: '';
        $imgTag = '<span class="small">لا توجد صورة</span>';
        if ($img && file_exists($uploadDir . '/' . $img)) {
            $imgTag = '<img src="' . $uploadRel . rawurlencode($img) . '" class="thumb" alt="">';
        } elseif ($img) {
            $imgTag = '<img src="' . htmlspecialchars($img) . '" class="thumb" alt="">';
        }
        $html .= '<tr>';
        $html .= '<td>' . (int)$c['id'] . '</td>';
        $html .= '<td>' . $imgTag . '</td>';
        $html .= '<td>' . $indent . htmlspecialchars($c['name'] ?: $c['slug']) . '</td>';
        $html .= '<td>' . htmlspecialchars($c['slug']) . '</td>';
        $html .= '<td>' . (!empty($c['is_featured']) ? 'نعم' : 'لا') . '</td>';
        $html .= '<td>' . (!empty($c['is_active']) ? 'نعم' : 'لا') . '</td>';
        $html .= '<td>' . (int)$c['sort_order'] . '</td>';
        $html .= '<td>' . htmlspecialchars($c['created_at']) . '</td>';
        $html .= '<td>';
        $html .= '<a class="btn" href="category_form.php?action=edit&id=' . (int)$c['id'] . '">تعديل</a> ';
        $html .= '<form method="post" action="category_form.php" style="display:inline" onsubmit="return confirm(\'هل أنت متأكد من حذف هذه الفئة؟\');">';
        $html .= '<input type="hidden" name="action" value="delete">';
        $html .= '<input type="hidden" name="id" value="' . (int)$c['id'] . '">';
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? ($_SESSION['csrf_token'] = bin2hex(random_bytes(16)))) . '">';
        $html .= '<button type="submit" class="btn danger">حذف</button>';
        $html .= '</form>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= render_rows($map, (int)$c['id'], $level + 1, $uploadDir, $uploadRel);
    }
    return $html;
}

$tree = build_tree($categories);
?>
<!doctype html>
<html lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>إدارة الفئات</title>
<style>
body{font-family:Arial, sans-serif;direction:rtl;padding:16px;background:#f6f7fb}
.container{max-width:1100px;margin:0 auto;background:#fff;padding:16px;border-radius:8px}
.table{width:100%;border-collapse:collapse;margin-top:12px}
.table th,.table td{border:1px solid #e6e6e6;padding:8px;text-align:right;vertical-align:middle}
.table th{background:#fafafa}
.thumb{width:72px;height:48px;object-fit:cover;border-radius:4px}
.btn{display:inline-block;padding:6px 10px;background:#0d6efd;color:#fff;border-radius:6px;text-decoration:none;border:none;cursor:pointer}
.btn.danger{background:#d9534f}
.small{font-size:.9rem;color:#666}
</style>
</head>
<body>
<div class="container">
  <h1>إدارة الفئات</h1>
  <p>
    <a class="btn" href="category_form.php">إضافة فئة جديدة</a>
    <a class="btn" href="dashboard.php" style="background:#6c757d">العودة للوحة</a>
  </p>

  <?php if (empty($categories)): ?>
    <p class="small">لا توجد فئات حتى الآن.</p>
  <?php else: ?>
    <table class="table" role="table" aria-label="قائمة الفئات">
      <thead>
        <tr>
          <th>ID</th>
          <th>صورة</th>
          <th>الاسم</th>
          <th>slug</th>
          <th>مميز</th>
          <th>نشط</th>
          <th>ترتيب</th>
          <th>تاريخ الإنشاء</th>
          <th>إجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php echo render_rows($tree, 0, 0, $uploadDir, $uploadRel); ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>